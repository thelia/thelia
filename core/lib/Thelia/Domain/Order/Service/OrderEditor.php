<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEditEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Invoice\InvoiceRefAllocator;
use Thelia\Domain\Order\DTO\AddedOrderLine;
use Thelia\Domain\Order\DTO\OrderEdit;
use Thelia\Domain\Order\Enum\OrderEditRefusal;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Exception\OrderEditRefusedException;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Domain\Shipping\Enum\PostageTaxStrategy;
use Thelia\Domain\Shipping\Service\PostageTaxBreakdownCalculator;
use Thelia\Domain\Taxation\Service\ExemptedVatCalculator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderVersionTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderPostageTax;
use Thelia\Model\OrderPostageTaxQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductAttributeCombinationQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderShipmentItemQuery;
use Thelia\Model\OrderVersionQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

final readonly class OrderEditor
{
    private const AMOUNT_PATTERN = '/^\d{1,10}(\.\d{1,6})?$/D';

    private const QUANTITY_TOLERANCE = 0.000001;

    public function __construct(
        private OrderTransactionManager $transactionManager,
        private OrderProductFactory $orderProductFactory,
        private TaxProvider $taxProvider,
        private TranslationProvider $translationProvider,
        private VirtualProductHandler $virtualProductHandler,
        private StockDecrementer $stockDecrementer,
        private InvoiceRefAllocator $invoiceRefAllocator,
        private EditableOrderStatuses $editableStatuses,
        private PostageTaxBreakdownCalculator $postageTaxBreakdownCalculator,
        private ExemptedVatCalculator $exemptedVatCalculator,
        private OrderHistoryRecorder $historyRecorder,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function stateOf(Order $order): string
    {
        $lines = [];

        foreach (OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find() as $line) {
            $lines[] = [$line->getId(), $this->amount($line->getQuantity()), $this->amount($line->getPrice()), $this->amount($line->getPromoPrice()), (int) $line->getWasInPromo()];
        }

        return hash('sha256', json_encode([$lines, $this->amount($order->getDiscount()), $this->amount($order->getPostage()), $this->amount($order->getPostageTax())], \JSON_THROW_ON_ERROR));
    }

    private function amount(string|float|int|null $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    public function edit(Order $order, OrderEdit $edit): void
    {
        if ($edit->isEmpty()) {
            throw new OrderEditRefusedException(OrderEditRefusal::NothingToEdit);
        }

        $connection = $this->transactionManager->begin();

        try {
            $this->lock($order, $connection);

            $this->assertEditable($order, $edit);

            $lines = $this->linesOf($order);
            $this->assertOperations($lines, $edit);

            $this->dispatcher->dispatch(new OrderEditEvent($order, $edit), TheliaEvents::ORDER_BEFORE_EDIT);

            $summary = $this->apply($order, $lines, $edit, $connection);

            Order::forgetCachedTotals((int) $order->getId());
            $this->assertDiscountIsCovered($order);

            $this->historyRecorder->record((int) $order->getId(), OrderHistoryEventType::ORDER_EDITED->value, $summary);

            $this->transactionManager->commit($connection);
        } catch (\Throwable $throwable) {
            $this->transactionManager->rollback($connection);
            Order::forgetCachedTotals((int) $order->getId());

            throw $throwable;
        }

        try {
            $this->dispatcher->dispatch(new OrderEditEvent($order, $edit), TheliaEvents::ORDER_AFTER_EDIT);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->err('A listener of the edit of order {order} failed: {ex}', ['order' => $order->getId(), 'ex' => $throwable]);
        }
    }

    private function lock(Order $order, ConnectionInterface $connection): void
    {
        $statement = $connection->prepare('SELECT `id` FROM `order` WHERE `id` = :id FOR UPDATE');
        $statement->bindValue(':id', $order->getId(), \PDO::PARAM_INT);
        $statement->execute();

        OrderProductTableMap::clearInstancePool();
        $order->reload(false, $connection);
    }

    private function assertEditable(Order $order, OrderEdit $edit): void
    {
        if (!$this->editableStatuses->allowsEditing($order)) {
            throw new OrderEditRefusedException(OrderEditRefusal::StatusNotEditable, (string) $order->getOrderStatus()?->getCode());
        }

        if ([] !== $edit->removedLines && $order->isPaid(false)) {
            throw new OrderEditRefusedException(OrderEditRefusal::PaidOrderLineRemoval);
        }

        if ($this->invoiceRefAllocator->isEnabled() && '' !== (string) $order->getInvoiceRef()) {
            throw new OrderEditRefusedException(OrderEditRefusal::InvoiceIssued, (string) $order->getInvoiceRef());
        }

        if (null !== $edit->expectedState && !hash_equals($this->stateOf($order), $edit->expectedState)) {
            throw new OrderEditRefusedException(OrderEditRefusal::StaleState);
        }
    }

    /**
     * @return array<int, OrderProduct>
     */
    private function linesOf(Order $order): array
    {
        $lines = [];

        foreach (OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find() as $line) {
            $lines[(int) $line->getId()] = $line;
        }

        return $lines;
    }

    /**
     * @param array<int, OrderProduct> $lines
     */
    private function assertOperations(array $lines, OrderEdit $edit): void
    {
        $touched = [...$edit->removedLines, ...array_keys($edit->quantities), ...array_keys($edit->unitPrices)];

        foreach ($touched as $lineId) {
            $line = $lines[$lineId] ?? throw new OrderEditRefusedException(OrderEditRefusal::UnknownLine, (string) $lineId);

            if ($line->isServiceLine()) {
                throw new OrderEditRefusedException(OrderEditRefusal::ServiceLine, (string) $lineId);
            }

            if ($this->isShippedOrReturned($line)) {
                throw new OrderEditRefusedException(OrderEditRefusal::LineInUse, (string) $lineId);
            }
        }

        foreach ($edit->removedLines as $lineId) {
            if (isset($edit->quantities[$lineId]) || isset($edit->unitPrices[$lineId])) {
                throw new OrderEditRefusedException(OrderEditRefusal::UnknownLine, 'a removed line cannot also be changed: '.$lineId);
            }
        }

        foreach ([...array_values($edit->quantities), ...array_map(static fn (AddedOrderLine $added): float => $added->quantity, $edit->addedLines)] as $quantity) {
            if (!is_finite($quantity) || $quantity <= 0) {
                throw new OrderEditRefusedException(OrderEditRefusal::InvalidQuantity);
            }
        }

        foreach ([...array_values($edit->unitPrices), ...array_filter(array_map(static fn (AddedOrderLine $added): ?string => $added->unitPrice, $edit->addedLines), static fn (?string $price): bool => null !== $price)] as $price) {
            if (1 !== preg_match(self::AMOUNT_PATTERN, $price)) {
                throw new OrderEditRefusedException(OrderEditRefusal::InvalidPrice);
            }
        }

        if (null !== $edit->discount && 1 !== preg_match(self::AMOUNT_PATTERN, $edit->discount)) {
            throw new OrderEditRefusedException(OrderEditRefusal::InvalidDiscount);
        }

        if (null !== $edit->untaxedPostage && 1 !== preg_match(self::AMOUNT_PATTERN, $edit->untaxedPostage)) {
            throw new OrderEditRefusedException(OrderEditRefusal::InvalidPostage);
        }

        $productLines = array_filter($lines, static fn (OrderProduct $line): bool => $line->isProductLine());
        $remaining = \count($productLines) - \count(array_unique($edit->removedLines)) + \count($edit->addedLines);

        if ($remaining < 1) {
            throw new OrderEditRefusedException(OrderEditRefusal::LastLine);
        }
    }

    private function isShippedOrReturned(OrderProduct $line): bool
    {
        return OrderShipmentItemQuery::create()->filterByOrderProductId($line->getId())->count() > 0
            || OrderReturnLineQuery::create()->filterByOrderProductId($line->getId())->count() > 0;
    }

    /**
     * @param array<int, OrderProduct> $lines
     *
     * @return array<string, mixed>
     */
    private function apply(Order $order, array $lines, OrderEdit $edit, ConnectionInterface $connection): array
    {
        $holdsStock = $this->holdsStock($order);
        $saleUnitId = $order->getSaleUnitId();
        $locale = (string) $order->getLang()?->getLocale();
        $country = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())?->getCountry();
        $vatExempted = 1 === (int) OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId())?->getVatExempted();
        $summary = ['added' => [], 'removed' => [], 'quantities' => [], 'prices' => [], 'discount' => null, 'postage' => null];

        foreach (array_unique($edit->removedLines) as $lineId) {
            $line = $lines[$lineId];
            $this->moveStock($order, $line, -(float) $line->getQuantity(), $holdsStock, $saleUnitId, $connection);

            $summary['removed'][] = ['ref' => $line->getProductSaleElementsRef(), 'quantity' => (float) $line->getQuantity()];

            OrderProductTaxQuery::create()->filterByOrderProductId($lineId)->delete($connection);
            OrderProductAttributeCombinationQuery::create()->filterByOrderProductId($lineId)->delete($connection);
            $line->delete($connection);
        }

        foreach ($edit->quantities as $lineId => $quantity) {
            $line = $lines[$lineId];
            $before = (float) $line->getQuantity();

            if (abs($quantity - $before) < self::QUANTITY_TOLERANCE) {
                continue;
            }

            $this->moveStock($order, $line, $quantity - $before, $holdsStock, $saleUnitId, $connection);
            $line->setQuantity($quantity)->save($connection);

            $summary['quantities'][] = ['ref' => $line->getProductSaleElementsRef(), 'from' => $before, 'to' => $quantity];
        }

        foreach ($edit->unitPrices as $lineId => $price) {
            $line = $lines[$lineId];
            $product = ProductQuery::create()->findOneByRef($line->getProductRef())
                ?? throw new OrderEditRefusedException(OrderEditRefusal::UnknownProduct, $line->getProductRef());
            $country ?? throw new TheliaProcessException('The delivery address of the order has no country.');

            $previous = (string) ($line->getWasInPromo() ? $line->getPromoPrice() : $line->getPrice());

            $line->setPrice($price)->setPromoPrice($price)->setWasInPromo(0)->save($connection);
            $this->replaceTaxes($line, $product, $country, $locale, $vatExempted, $connection);

            $summary['prices'][] = ['ref' => $line->getProductSaleElementsRef(), 'from' => $previous, 'to' => $price];
        }

        foreach ($edit->addedLines as $added) {
            $country ?? throw new TheliaProcessException('The delivery address of the order has no country.');
            $summary['added'][] = $this->addLine($order, $added, $country, $locale, $vatExempted, $holdsStock, $saleUnitId, $connection);
        }

        if (null !== $edit->discount && 0 !== bccomp($edit->discount, (string) $order->getDiscount(), 6)) {
            $summary['discount'] = ['from' => (string) $order->getDiscount(), 'to' => $edit->discount];
            $order->setDiscount($edit->discount)->save($connection);
        }

        $linesChanged = [] !== $edit->addedLines || [] !== $edit->removedLines || [] !== $edit->quantities || [] !== $edit->unitPrices;
        $postageFollowsGoods = !$vatExempted && PostageTaxStrategy::SINGLE_RULE !== PostageTaxStrategy::fromShopConfiguration();

        if (null !== $edit->untaxedPostage || ($linesChanged && $postageFollowsGoods)) {
            $country ?? throw new TheliaProcessException('The delivery address of the order has no country.');
            $untaxedPostage = $edit->untaxedPostage ?? $this->amount((float) $order->getPostage() - (float) $order->getPostageTax());
            $summary['postage'] = $this->replacePostage($order, $untaxedPostage, $country, $locale, $vatExempted, $connection);
        }

        if ($vatExempted) {
            $country ?? throw new TheliaProcessException('The delivery address of the order has no country.');
            $this->refreshExemptedVat($order, $country, $locale, $connection);
        }

        return $summary;
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private function replacePostage(Order $order, string $untaxedPostage, Country $country, string $locale, bool $vatExempted, ConnectionInterface $connection): ?array
    {
        $before = (string) $order->getPostage();
        $taxRuleTitle = $order->getPostageTaxRuleTitle();

        $state = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())?->getState();
        $breakdown = $vatExempted ? [] : $this->postageTaxBreakdownCalculator->splitForEditedOrder($order, $country, $state, (float) $untaxedPostage, $locale);

        if ($vatExempted) {
            $taxed = $untaxedPostage;
            $tax = '0';
        } elseif ([] !== $breakdown) {
            $tax = $this->amount(round(array_sum(array_map(static fn ($line): float => $line->amount, $breakdown)), 2));
            $taxed = $this->amount((float) $untaxedPostage + (float) $tax);
        } else {
            $module = $order->getDeliveryModuleInstance();

            if (!method_exists($module, 'buildOrderPostage')) {
                throw new OrderEditRefusedException(OrderEditRefusal::PostageTaxUnknown, (string) $order->getDeliveryModuleTitle());
            }

            $quoted = $module->buildOrderPostage((float) $untaxedPostage, $country, $locale);
            $taxed = $this->amount($quoted->getAmount());
            $tax = $this->amount($quoted->getAmountTax());
            $taxRuleTitle = $quoted->getTaxRuleTitle();
        }

        $order->setPostage($taxed)->setPostageTax($tax)->setPostageTaxRuleTitle($taxRuleTitle)->save($connection);

        OrderPostageTaxQuery::create()->filterByOrderId($order->getId())->delete($connection);

        foreach ($breakdown as $line) {
            (new OrderPostageTax())
                ->setOrderId($order->getId())
                ->setTitle($line->title)
                ->setDescription($line->description)
                ->setUntaxedAmount((string) $line->untaxedAmount)
                ->setAmount((string) $line->amount)
                ->save($connection);
        }

        return 0 === bccomp($before, $taxed, 6) ? null : ['from' => $before, 'to' => $taxed];
    }

    private function refreshExemptedVat(Order $order, Country $country, string $locale, ConnectionInterface $connection): void
    {
        $invoiceAddress = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());

        if (null === $invoiceAddress) {
            return;
        }

        $state = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())?->getState();
        $untaxedPostage = (float) $order->getPostage();
        $postageVat = 0.0;

        if ($untaxedPostage > 0.0) {
            $breakdown = $this->postageTaxBreakdownCalculator->splitForEditedOrder($order, $country, $state, $untaxedPostage, $locale);
            $module = $order->getDeliveryModuleInstance();

            if ([] !== $breakdown) {
                $postageVat = array_sum(array_map(static fn ($line): float => $line->amount, $breakdown));
            } elseif (method_exists($module, 'buildOrderPostage')) {
                $postageVat = (float) $module->buildOrderPostage($untaxedPostage, $country, $locale)->getAmountTax();
            }
        }

        $invoiceAddress
            ->setVatExemptedAmount((string) $this->exemptedVatCalculator->forOrder($order, $country, $state, $postageVat))
            ->save($connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function addLine(Order $order, AddedOrderLine $added, Country $country, string $locale, bool $vatExempted, bool $holdsStock, ?int $saleUnitId, ConnectionInterface $connection): array
    {
        $productSaleElements = ProductSaleElementsQuery::create()->findPk($added->productSaleElementsId);
        $product = $productSaleElements?->getProduct();

        if (null === $productSaleElements || null === $product || 1 !== (int) $product->getVisible()) {
            throw new OrderEditRefusedException(OrderEditRefusal::UnknownProduct, (string) $added->productSaleElementsId);
        }

        $prices = $productSaleElements->getPricesByCurrency($order->getCurrency());
        $promo = 1 === (int) $productSaleElements->getPromo();
        $price = $added->unitPrice ?? (string) $prices->getPrice();
        $promoPrice = $added->unitPrice ?? (string) $prices->getPromoPrice();
        $wasInPromo = null === $added->unitPrice && $promo ? 1 : 0;

        $line = $this->orderProductFactory->createFromCatalog(
            $order,
            $product,
            $productSaleElements,
            $this->translationProvider->getProductTranslation($locale, (int) $product->getId()),
            $this->virtualProductHandler->resolve($this->dispatcher, $order, $product, (int) $productSaleElements->getId()),
            $this->translationProvider->getTaxRuleTranslation($locale, (int) $product->getTaxRuleId()),
            $added->quantity,
            $price,
            $promoPrice,
            $wasInPromo,
            0,
            null,
            $connection,
        );

        $this->replaceTaxes($line, $product, $country, $locale, $vatExempted, $connection);
        $this->orderProductFactory->persistAttributeCombinations($line, $productSaleElements, $locale, $connection);
        $this->moveStock($order, $line, $added->quantity, $holdsStock, $saleUnitId, $connection);

        return ['ref' => $line->getProductSaleElementsRef(), 'quantity' => $added->quantity, 'price' => (string) ($wasInPromo ? $promoPrice : $price)];
    }

    private function replaceTaxes(OrderProduct $line, Product $product, Country $country, string $locale, bool $vatExempted, ConnectionInterface $connection): void
    {
        OrderProductTaxQuery::create()->filterByOrderProductId($line->getId())->delete($connection);

        if ($vatExempted) {
            return;
        }

        foreach ($this->taxProvider->computeTaxesForCartItem($product, $country, (float) $line->getPrice(), (float) $line->getPromoPrice(), $locale) ?? [] as $tax) {
            $tax->setOrderProductId($line->getId());
            $tax->save($connection);
        }
    }

    private function holdsStock(Order $order): bool
    {
        if ($order->isPaid(false)) {
            return true;
        }

        if (!$order->isNotPaid(true)) {
            return false;
        }

        if (OrderVersionQuery::create()->filterById($order->getId())->select(OrderVersionTableMap::COL_STATUS_ID)->distinct()->count() > 1) {
            return false;
        }

        return $order->isStockManagedOnOrderCreation($this->dispatcher);
    }

    private function moveStock(Order $order, OrderProduct $line, float $delta, bool $holdsStock, ?int $saleUnitId, ConnectionInterface $connection): void
    {
        if (!$holdsStock || abs($delta) < self::QUANTITY_TOLERANCE || !$line->isProductLine()) {
            return;
        }

        $product = ProductQuery::create()->findOneByRef($line->getProductRef());

        if (null !== $product && !$this->virtualProductHandler->resolve($this->dispatcher, $order, $product, (int) $line->getProductSaleElementsId())->useStock) {
            return;
        }

        if ($delta < 0) {
            $this->stockDecrementer->increment((int) $line->getProductSaleElementsId(), -$delta, $connection, $saleUnitId);

            return;
        }

        try {
            $this->stockDecrementer->decrement(
                (int) $line->getProductSaleElementsId(),
                $delta,
                guardAvailability: ConfigQuery::checkAvailableStock(),
                allowNegativeStock: (bool) (int) ConfigQuery::read('allow_negative_stock', 0),
                connection: $connection,
                saleUnitId: $saleUnitId,
            );
        } catch (TheliaProcessException $shortage) {
            throw new StockShortageException($line->getProductRef(), previous: $shortage);
        }
    }

    private function assertDiscountIsCovered(Order $order): void
    {
        $order->clearOrderProducts();
        $tax = 0;

        if ((float) $order->getDiscount() > $order->getTotalAmount($tax, false, false) + 0.000001) {
            throw new OrderEditRefusedException(OrderEditRefusal::InvalidDiscount, 'the discount exceeds the order total');
        }
    }
}

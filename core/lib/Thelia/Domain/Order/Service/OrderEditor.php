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
use Thelia\Domain\Catalog\Quantity\QuantityScale;
use Thelia\Domain\Order\DTO\AddedOrderLine;
use Thelia\Domain\Order\DTO\OrderEdit;
use Thelia\Domain\Order\DTO\OrderEditPreview;
use Thelia\Domain\Order\DTO\OrderTotals;
use Thelia\Domain\Order\Enum\OrderEditRefusal;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Exception\OrderEditRefusedException;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Domain\Shipping\DTO\PostageTaxLine;
use Thelia\Domain\Shipping\Service\PostageTaxBreakdownCalculator;
use Thelia\Domain\Taxation\Service\ExemptedVatCalculator;
use Thelia\Domain\Taxation\TaxEngine\Calculator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\Map\OrderPostageTaxTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
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
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

final readonly class OrderEditor
{
    private const AMOUNT_PATTERN = '/^\d{1,10}(\.\d{1,6})?$/D';

    private const QUANTITY_TOLERANCE = 0.000001;

    private const MAX_QUANTITY = 9_999_999.0;

    public function __construct(
        private OrderTransactionManager $transactionManager,
        private OrderProductFactory $orderProductFactory,
        private TaxProvider $taxProvider,
        private TranslationProvider $translationProvider,
        private VirtualProductHandler $virtualProductHandler,
        private StockDecrementer $stockDecrementer,
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

    private function unitPriceOf(OrderProduct $line): string
    {
        $promoPrice = (string) $line->getPromoPrice();

        return $line->getWasInPromo() && '' !== $promoPrice ? $promoPrice : (string) $line->getPrice();
    }

    private function amount(string|float|int|null $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    public function edit(Order $order, OrderEdit $edit): void
    {
        $this->run($order, $edit, false);
    }

    public function simulate(Order $order, OrderEdit $edit): OrderEditPreview
    {
        return $this->run($order, $edit, true) ?? throw new TheliaProcessException('A simulation must give a preview.');
    }

    private function run(Order $order, OrderEdit $edit, bool $simulation): ?OrderEditPreview
    {
        if ($edit->isEmpty()) {
            throw new OrderEditRefusedException(OrderEditRefusal::NothingToEdit);
        }

        $nested = $simulation && $this->transactionManager->isInTransaction();
        $connection = $nested ? $this->transactionManager->savepoint() : $this->transactionManager->begin();

        try {
            $this->lock($order, $connection);

            $this->assertEditable($order, $edit);

            $lines = $this->linesOf($order);
            $edit = $this->withoutUnchangedValues($lines, $edit);
            $this->assertOperations($lines, $edit);

            if ($this->movesNothing($order, $edit)) {
                throw new OrderEditRefusedException(OrderEditRefusal::NothingToEdit);
            }

            if (!$simulation) {
                $this->dispatcher->dispatch(new OrderEditEvent($order, $edit), TheliaEvents::ORDER_BEFORE_EDIT);
            }

            $before = $simulation ? $this->totalsOf($order) : null;
            $summary = $this->apply($order, $lines, $edit, $connection);
            ProductSaleElementsTableMap::clearInstancePool();

            if ($this->changedNothing($summary)) {
                throw new OrderEditRefusedException(OrderEditRefusal::NothingToEdit);
            }

            Order::forgetCachedTotals((int) $order->getId());
            Calculator::forgetOrderTaxFactor($order);
            $this->assertDiscountIsCovered($order);

            if (null !== $before) {
                $preview = new OrderEditPreview($before, $this->totalsOf($order), $summary);

                $nested ? $this->transactionManager->rollbackToSavepoint($connection) : $this->transactionManager->rollback($connection);
                $this->restore($order);

                return $preview;
            }

            $this->historyRecorder->record((int) $order->getId(), OrderHistoryEventType::ORDER_EDITED->value, $summary);

            $this->transactionManager->commit($connection);
        } catch (\Throwable $throwable) {
            $nested ? $this->transactionManager->rollbackToSavepoint($connection) : $this->transactionManager->rollback($connection);

            try {
                $this->restore($order);
            } catch (\Throwable) {
            }

            throw $throwable;
        }

        try {
            $this->dispatcher->dispatch(new OrderEditEvent($order, $edit, $summary), TheliaEvents::ORDER_AFTER_EDIT);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->err('A listener of the edit of order {order} failed: {ex}', ['order' => $order->getId(), 'ex' => $throwable]);
        }

        return null;
    }

    /**
     * @param array<int, OrderProduct> $lines
     */
    private function withoutUnchangedValues(array $lines, OrderEdit $edit): OrderEdit
    {
        $quantities = array_filter(
            $edit->quantities,
            static fn (float $quantity, int $lineId): bool => !isset($lines[$lineId])
                || !is_finite($quantity)
                || abs($quantity - (float) $lines[$lineId]->getQuantity()) >= self::QUANTITY_TOLERANCE,
            \ARRAY_FILTER_USE_BOTH,
        );

        $unitPrices = array_filter(
            $edit->unitPrices,
            fn (string $price, int $lineId): bool => !isset($lines[$lineId])
                || 1 !== preg_match(self::AMOUNT_PATTERN, $price)
                || 0 !== bccomp($price, $this->unitPriceOf($lines[$lineId]), 6),
            \ARRAY_FILTER_USE_BOTH,
        );

        return new OrderEdit($edit->addedLines, $edit->removedLines, $quantities, $unitPrices, $edit->discount, $edit->untaxedPostage, $edit->expectedState);
    }

    private function movesNothing(Order $order, OrderEdit $edit): bool
    {
        if ([] !== $edit->addedLines || [] !== $edit->removedLines || [] !== $edit->quantities || [] !== $edit->unitPrices || null !== $edit->untaxedPostage) {
            return false;
        }

        return null === $edit->discount || 0 === bccomp($edit->discount, (string) $order->getDiscount(), 6);
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function changedNothing(array $summary): bool
    {
        return [] === $summary['added'] && [] === $summary['removed'] && [] === $summary['quantities'] && [] === $summary['prices']
            && null === $summary['discount'] && null === $summary['postage'];
    }

    private function restore(Order $order): void
    {
        Order::forgetCachedTotals((int) $order->getId());
        OrderProductTableMap::clearInstancePool();
        OrderProductTaxTableMap::clearInstancePool();
        OrderPostageTaxTableMap::clearInstancePool();
        OrderAddressTableMap::clearInstancePool();
        ProductSaleElementsTableMap::clearInstancePool();
        Calculator::forgetOrderTaxFactor($order);
        $order->reload(false);
    }

    private function totalsOf(Order $order): OrderTotals
    {
        Order::forgetCachedTotals((int) $order->getId());
        Calculator::forgetOrderTaxFactor($order);
        $order->clearOrderProducts();

        $tax = 0;
        $goods = $order->getTotalAmount($tax, false, false);
        $total = $order->getTotalAmount($tax, true, true);

        return new OrderTotals(
            $this->amount($goods),
            $this->amount($order->getDiscount()),
            $this->amount($order->getPostage()),
            $this->amount($tax),
            $this->amount($total),
        );
    }

    private function lock(Order $order, ConnectionInterface $connection): void
    {
        $order->lockForUpdate($connection);
        $order->reload(false, $connection);
    }

    private function assertEditable(Order $order, OrderEdit $edit): void
    {
        if (!$this->editableStatuses->allowsEditing($order)) {
            throw new OrderEditRefusedException(OrderEditRefusal::StatusNotEditable, (string) $order->getOrderStatus()?->getCode());
        }

        if ('' !== (string) $order->getInvoiceRef()) {
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

            if ($line->isBundleLine() || $line->isBundleComponentLine()) {
                throw new OrderEditRefusedException(OrderEditRefusal::BundleLine, (string) $lineId);
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
            if (!is_finite($quantity) || $quantity <= 0 || $quantity > self::MAX_QUANTITY) {
                throw new OrderEditRefusedException(OrderEditRefusal::InvalidQuantity);
            }
        }

        // A line keeps the decimals it was sold with (frozen on the line): the minimum and the step
        // of the combination are left to the merchant, who edits an order already placed.
        foreach ($edit->quantities as $lineId => $quantity) {
            self::assertFitsTheDecimals($quantity, (int) $lines[$lineId]->getQuantityDecimals(), (string) $lineId);
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
        $exemptedVatBefore = $vatExempted ? $this->exemptedVatOf($order, $country, $locale, null !== $edit->untaxedPostage) : null;

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

            $this->moveStock($order, $line, $quantity - $before, $holdsStock, $saleUnitId, $connection);
            $line->setQuantity($quantity)->save($connection);

            $summary['quantities'][] = ['ref' => $line->getProductSaleElementsRef(), 'from' => $before, 'to' => $quantity];
        }

        foreach ($edit->unitPrices as $lineId => $price) {
            $line = $lines[$lineId];
            $product = ProductQuery::create()->findOneByRef($line->getProductRef())
                ?? throw new OrderEditRefusedException(OrderEditRefusal::UnknownProduct, $line->getProductRef());
            $country ?? throw new TheliaProcessException('The delivery address of the order has no country.');

            $previous = $this->unitPriceOf($line);

            $line->setPromoPrice($price)->setWasInPromo(1)->save($connection);
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

        if (null !== $edit->untaxedPostage) {
            $country ?? throw new TheliaProcessException('The delivery address of the order has no country.');
            $summary['postage'] = $this->replacePostage($order, $edit->untaxedPostage, $country, $locale, $vatExempted, $connection);
        }

        if (null !== $exemptedVatBefore) {
            $this->refreshExemptedVat($order, $exemptedVatBefore, $this->exemptedVatOf($order, $country, $locale, null !== $edit->untaxedPostage), $connection);
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
        $untaxedPostage = $this->amount(round((float) $untaxedPostage, 2));

        $breakdown = [];

        if ($vatExempted) {
            $taxed = $untaxedPostage;
            $tax = '0';
        } else {
            ['breakdown' => $breakdown, 'taxed' => $taxed, 'tax' => $tax, 'taxRuleTitle' => $quotedTitle] = $this->taxPostage($order, $country, $locale, $untaxedPostage)
                ?? throw new OrderEditRefusedException(OrderEditRefusal::PostageTaxUnknown, (string) $order->getDeliveryModuleTitle());
            $taxRuleTitle = $quotedTitle ?? $taxRuleTitle;
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

    /**
     * The one cascade that taxes a postage: the breakdown of a shop that spreads the tax over the goods, nothing for a free
     * postage, else the quote of the delivery module on the delivery country; null when the module cannot quote it.
     *
     * @return array{breakdown: list<PostageTaxLine>, taxed: string, tax: string, taxRuleTitle: ?string}|null
     */
    private function taxPostage(Order $order, Country $country, string $locale, string $untaxedPostage): ?array
    {
        $state = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())?->getState();
        $breakdown = $this->postageTaxBreakdownCalculator->splitForEditedOrder($order, $country, $state, (float) $untaxedPostage, $locale);

        if ([] !== $breakdown) {
            $tax = $this->amount(round(array_sum(array_map(static fn (PostageTaxLine $line): float => $line->amount, $breakdown)), 2));

            return ['breakdown' => $breakdown, 'taxed' => $this->amount((float) $untaxedPostage + (float) $tax), 'tax' => $tax, 'taxRuleTitle' => null];
        }

        if ((float) $untaxedPostage <= 0.0) {
            return ['breakdown' => [], 'taxed' => $this->amount(0), 'tax' => $this->amount(0), 'taxRuleTitle' => null];
        }

        $module = $order->getDeliveryModuleInstance();

        if (!method_exists($module, 'buildOrderPostage')) {
            return null;
        }

        $quoted = $module->buildOrderPostage((float) $untaxedPostage, $country, $locale);

        return ['breakdown' => [], 'taxed' => $this->amount($quoted->getAmount()), 'tax' => $this->amount($quoted->getAmountTax()), 'taxRuleTitle' => $quoted->getTaxRuleTitle()];
    }

    private function exemptedVatOf(Order $order, ?Country $country, string $locale, bool $withPostage): float
    {
        $country ?? throw new TheliaProcessException('The delivery address of the order has no country.');
        $state = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())?->getState();
        $postageVat = $withPostage ? (float) ($this->taxPostage($order, $country, $locale, $this->amount($order->getPostage()))['tax'] ?? 0) : 0.0;

        return $this->exemptedVatCalculator->forOrder($order, $country, $state, 0.0) + $postageVat;
    }

    private function refreshExemptedVat(Order $order, float $before, float $after, ConnectionInterface $connection): void
    {
        $invoiceAddress = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());

        if (null === $invoiceAddress || null === $invoiceAddress->getVatExemptedAmount()) {
            return;
        }

        $invoiceAddress
            ->setVatExemptedAmount($this->amount(round(max(0.0, (float) $invoiceAddress->getVatExemptedAmount() - $before + $after), 2)))
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

        if (1 === (int) $product->getIsBundle()) {
            throw new OrderEditRefusedException(OrderEditRefusal::BundleLine, (string) $added->productSaleElementsId);
        }

        self::assertFitsTheDecimals($added->quantity, (int) $productSaleElements->getQuantityDecimals(), (string) $added->productSaleElementsId);

        $prices = $productSaleElements->getPricesByCurrency($order->getCurrency(), (float) $order->getCustomer()?->getDiscount());
        $promo = 1 === (int) $productSaleElements->getPromo();
        $price = $added->unitPrice ?? (string) $prices->getPrice();
        $promoPrice = $added->unitPrice ?? (string) $prices->getPromoPrice();
        $wasInPromo = null === $added->unitPrice && $promo ? 1 : 0;

        $virtual = $this->virtualProductHandler->resolve($this->dispatcher, $order, $product, (int) $productSaleElements->getId());

        $line = $this->orderProductFactory->createFromCatalog(
            $order,
            $product,
            $productSaleElements,
            $this->translationProvider->getProductTranslation($locale, (int) $product->getId()),
            $virtual,
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
        $this->moveStock($order, $line, $added->quantity, $holdsStock, $saleUnitId, $connection, $virtual->useStock);

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

        return $order->isStockStillGovernedByCreation($this->dispatcher);
    }

    private function moveStock(Order $order, OrderProduct $line, float $delta, bool $holdsStock, ?int $saleUnitId, ConnectionInterface $connection, ?bool $useStock = null): void
    {
        if (!$holdsStock || abs($delta) < self::QUANTITY_TOLERANCE || !$line->isProductLine()) {
            return;
        }

        if (null === $useStock) {
            $product = ProductQuery::create()->findOneByRef($line->getProductRef());
            $useStock = null === $product || $this->virtualProductHandler->resolve($this->dispatcher, $order, $product, (int) $line->getProductSaleElementsId())->useStock;
        }

        if (!$useStock) {
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

    /**
     * @throws OrderEditRefusedException 2.5 of a line sold by the piece, 1.2345 kg of a line sold to the hundredth
     */
    private static function assertFitsTheDecimals(float $quantity, int $decimals, string $detail): void
    {
        if (!QuantityScale::fits($quantity, max(0, min(QuantityScale::MAXIMUM_DECIMALS, $decimals)))) {
            throw new OrderEditRefusedException(OrderEditRefusal::InvalidQuantity, \sprintf('%s: %d decimal(s) at most', $detail, $decimals));
        }
    }
}

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

namespace Thelia\Domain\Order\Edition;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEditEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Domain\Order\Service\OrderProductFactory;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\Service\StockPolicy;
use Thelia\Domain\Order\Service\TaxProvider;
use Thelia\Domain\Order\Service\TranslationProvider;
use Thelia\Domain\Order\Service\VirtualProductHandler;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Map\OrderPostageTaxTableMap;
use Thelia\Model\Map\OrderProductAttributeCombinationTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderPostageTaxQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Changes the lines, the discount and the postage of an order after it was placed, at once.
 *
 * The order stays the archive it is: a line added freezes the catalogue label and price of
 * that moment, a price corrected by hand stays corrected, the taxes of a touched line are
 * computed again for the country of the invoice address. The stock follows the lines when
 * the order holds it, as a change of status would. The totals are read back the way the
 * order computes them, under its rounding rule.
 *
 * An edit is checked whole before anything is written, applied under a lock of the order
 * row, refused when the order changed since it was composed, and undone whole when any
 * part fails. A preview runs the same code and undoes it: the model events of the lines
 * it writes are dispatched, the edit events are not.
 */
final readonly class OrderEditor
{
    private const SAVEPOINT = 'thelia_order_edit';

    /**
     * Above it a quantity or an amount is a typing mistake, and the price columns,
     * DECIMAL(16,6), could no longer hold it once multiplied or taxed.
     */
    private const MAX_NUMBER = 999_999_999.0;

    /**
     * Statuses an order can be edited in, compared through the equivalence of custom ones:
     * an order is edited until it is sent.
     */
    private const EDITABLE_STATUSES = [OrderStatus::CODE_NOT_PAID, OrderStatus::CODE_PAID, OrderStatus::CODE_PROCESSING];

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private OrderProductFactory $orderProductFactory,
        private TranslationProvider $translationProvider,
        private TaxProvider $taxProvider,
        private VirtualProductHandler $virtualProductHandler,
        private StockDecrementer $stockDecrementer,
        private StockPolicy $stockPolicy,
        private OrderHistoryRecorder $history,
    ) {
    }

    /**
     * Why the order cannot be edited, or null when it can.
     */
    public function refusal(Order $order): ?string
    {
        $ref = (string) $order->getRef();

        if ('' !== trim((string) $order->getInvoiceRef())) {
            return self::trans('Order %ref has an invoice (%invoice): changing its lines needs a credit note, not an edit.', ['%ref' => $ref, '%invoice' => (string) $order->getInvoiceRef()]);
        }

        $status = $order->getOrderStatus();

        if (null === $status || !\in_array($status->getEffectiveCode(), self::EDITABLE_STATUSES, true)) {
            return self::trans('Order %ref is %status: an order can only be edited until it is sent.', ['%ref' => $ref, '%status' => (string) $status?->getCode()]);
        }

        if ($order->getVatExempted()) {
            return self::trans('Order %ref is exempt from VAT: its lines cannot be edited, the VAT it was exempted from would no longer match.', ['%ref' => $ref]);
        }

        return null;
    }

    /**
     * What the edit compares to refuse overwriting a change made since it was composed: the
     * status, the invoice, the discount, the postage and every line of the order.
     */
    public function fingerprint(Order $order): string
    {
        $lines = [];

        foreach (OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find() as $line) {
            $taxes = 0.0;

            foreach (OrderProductTaxQuery::create()->filterByOrderProductId($line->getId())->find() as $tax) {
                $taxes += (float) $tax->getAmount() + (float) $tax->getPromoAmount();
            }

            $lines[] = [(int) $line->getId(), self::number($line->getQuantity()), self::number($line->getPrice()), self::number($line->getPromoPrice()), (int) $line->getWasInPromo(), self::number($taxes)];
        }

        // As numbers: an order just written holds "0", the same read back "0.000000".
        return hash('sha256', json_encode([
            (int) $order->getStatusId(),
            (string) $order->getInvoiceRef(),
            self::number($order->getDiscount()),
            self::number($order->getPostage()),
            self::number($order->getPostageTax()),
            $lines,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @throws OrderNotEditableException
     * @throws OrderEditConflictException when the order changed since $fingerprint was read
     * @throws InvalidOrderEditException
     */
    public function apply(Order $order, OrderEdit $edit, string $fingerprint): OrderEditOutcome
    {
        return $this->run($order, $edit, $fingerprint, false);
    }

    /**
     * The totals the edit would give, nothing written.
     *
     * @throws OrderNotEditableException
     * @throws InvalidOrderEditException
     */
    public function preview(Order $order, OrderEdit $edit): OrderEditOutcome
    {
        return $this->run($order, $edit, null, true);
    }

    private function run(Order $order, OrderEdit $edit, ?string $fingerprint, bool $preview): OrderEditOutcome
    {
        $orderId = (int) $order->getId();
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            // A savepoint rather than the transaction alone: it undoes the edit for real even
            // inside a transaction a caller already opened, where a nested rollback undoes
            // nothing until the outermost one.
            $connection->exec('SAVEPOINT '.self::SAVEPOINT);
            $statement = $connection->prepare('SELECT `id` FROM `order` WHERE `id` = :id FOR UPDATE');
            $statement->execute([':id' => $orderId]);
            $order = $this->fresh($orderId, $connection);

            if (null !== $reason = $this->refusal($order)) {
                throw new OrderNotEditableException($reason);
            }

            if (null !== $fingerprint && !hash_equals($this->fingerprint($order), $fingerprint)) {
                throw new OrderEditConflictException(self::trans('Order %ref changed since its edit was opened: open it again to see what changed.', ['%ref' => (string) $order->getRef()]));
            }

            $plan = $this->checked($order, $edit, $connection);
            [$totalBefore, $taxBefore] = $this->totals($order);
            $wasPaid = true === $order->getOrderStatus()?->isPaid(false);

            if (!$preview) {
                $this->dispatcher->dispatch(new OrderEditEvent($order, $edit), TheliaEvents::ORDER_BEFORE_EDIT);
            }

            $changes = $this->write($order, $edit, $plan, $connection);
            $edited = $this->fresh($orderId, $connection);
            [$totalAfter, $taxAfter] = $this->totals($edited);
            $outcome = new OrderEditOutcome($totalBefore, $totalAfter, $taxBefore, $taxAfter, $changes, $wasPaid);

            if ($preview) {
                $this->undo($connection);

                return $outcome;
            }

            if ([] !== $changes) {
                $this->history->record($orderId, OrderHistoryEventType::ORDER_EDITED->value, [
                    'changes' => $changes,
                    'total_before' => number_format($totalBefore, 2, '.', ''),
                    'total_after' => number_format($totalAfter, 2, '.', ''),
                ]);
            }

            $connection->exec('RELEASE SAVEPOINT '.self::SAVEPOINT);
            $connection->commit();
        } catch (\Throwable $failure) {
            $this->undo($connection);

            throw $failure;
        }

        try {
            $this->dispatcher->dispatch(new OrderEditEvent($edited, $edit, $outcome), TheliaEvents::ORDER_AFTER_EDIT);
        } catch (\Throwable $failure) {
            // The edit is committed: a listener failing now cannot take it back, and an error
            // would have the merchant save it again against an order that already changed.
            Tlog::getInstance()->addError(\sprintf('A listener failed after order %s was edited: %s', (string) $edited->getRef(), $failure->getMessage()));
        }

        return $outcome;
    }

    /**
     * Back to the savepoint, then out of the transaction this edit opened without a rollback:
     * a nested rollback would leave the transaction of a caller impossible to commit, while
     * the savepoint already undid everything the edit wrote. Only when the savepoint itself
     * is gone (a deadlock rolls the whole transaction back) is the transaction rolled back.
     */
    private function undo(ConnectionInterface $connection): void
    {
        if ($connection->inTransaction()) {
            try {
                $connection->exec('ROLLBACK TO SAVEPOINT '.self::SAVEPOINT);
                $connection->exec('RELEASE SAVEPOINT '.self::SAVEPOINT);
                $connection->commit();
            } catch (\Throwable) {
                $connection->rollBack();
            }
        }

        $this->forget();
    }

    /**
     * Every line of the edit checked against the order before anything is written.
     *
     * @return array{kept: array<int, array{line: OrderProduct, edit: OrderEditLine}>, removed: list<OrderProduct>, added: list<array{pse: \Thelia\Model\ProductSaleElements, edit: OrderEditLine}>}
     */
    private function checked(Order $order, OrderEdit $edit, ConnectionInterface $connection): array
    {
        $ref = (string) $order->getRef();
        $productLines = [];

        foreach (OrderProductQuery::create()->filterByOrderId($order->getId())->find($connection) as $line) {
            if (!$line->isServiceLine()) {
                $productLines[(int) $line->getId()] = $line;
            }
        }

        $kept = [];
        $added = [];

        foreach ($edit->lines as $lineEdit) {
            if ($lineEdit->quantity <= 0 || !self::isReasonable($lineEdit->quantity)) {
                throw new InvalidOrderEditException(self::trans('Order %ref: a quantity is a positive number.', ['%ref' => $ref]));
            }

            if (null !== $lineEdit->unitPrice && (!is_numeric($lineEdit->unitPrice) || (float) $lineEdit->unitPrice < 0 || !self::isReasonable((float) $lineEdit->unitPrice))) {
                throw new InvalidOrderEditException(self::trans('Order %ref: "%price" is not a price.', ['%ref' => $ref, '%price' => $lineEdit->unitPrice]));
            }

            if ($lineEdit->isAdded()) {
                $pse = ProductSaleElementsQuery::create()->findPk($lineEdit->productSaleElementsId, $connection)
                    ?? throw new InvalidOrderEditException(self::trans('Order %ref: the product to add does not exist.', ['%ref' => $ref]));
                $added[] = ['pse' => $pse, 'edit' => $lineEdit];

                continue;
            }

            $line = $productLines[$lineEdit->orderProductId] ?? throw new InvalidOrderEditException(self::trans('Order %ref has no such line.', ['%ref' => $ref]));
            $kept[(int) $line->getId()] = ['line' => $line, 'edit' => $lineEdit];
        }

        if ([] === $kept && [] === $added) {
            throw new InvalidOrderEditException(self::trans('Order %ref: an order keeps at least one product.', ['%ref' => $ref]));
        }

        $removed = array_values(array_diff_key($productLines, $kept));
        $returned = $this->returnedQuantities(array_keys($productLines), $connection);

        foreach ($productLines as $id => $line) {
            $newQuantity = isset($kept[$id]) ? $kept[$id]['edit']->quantity : 0.0;

            if (($returned[$id] ?? 0.0) > $newQuantity) {
                throw new InvalidOrderEditException(self::trans('Order %ref: %product is in a return, its quantity cannot go below what is returned.', ['%ref' => $ref, '%product' => (string) $line->getProductRef()]));
            }
        }

        return ['kept' => $kept, 'removed' => $removed, 'added' => $added];
    }

    /**
     * @param array{kept: array<int, array{line: OrderProduct, edit: OrderEditLine}>, removed: list<OrderProduct>, added: list<array{pse: \Thelia\Model\ProductSaleElements, edit: OrderEditLine}>} $plan
     *
     * @return list<array<string, string>>
     */
    private function write(Order $order, OrderEdit $edit, array $plan, ConnectionInterface $connection): array
    {
        $changes = [];
        $stockHeld = $this->holdsStock($order);
        $country = $this->invoiceCountry($order);
        $locale = (string) $order->getLang()?->getLocale();

        foreach ($plan['kept'] as ['line' => $line, 'edit' => $lineEdit]) {
            $quantity = (float) $line->getQuantity();

            if ($lineEdit->quantity !== $quantity) {
                $this->moveStock($line, $lineEdit->quantity - $quantity, $stockHeld, $connection);
                $line->setQuantity($lineEdit->quantity);
                $changes[] = ['change' => 'quantity', 'product_ref' => (string) $line->getProductRef(), 'from' => self::quantity($quantity), 'to' => self::quantity($lineEdit->quantity)];
            }

            $current = 1 === (int) $line->getWasInPromo() ? (float) $line->getPromoPrice() : (float) $line->getPrice();

            if (null !== $lineEdit->unitPrice && round((float) $lineEdit->unitPrice, 6) !== round($current, 6)) {
                $this->reprice($line, (float) $lineEdit->unitPrice, $current, $country, $locale, $connection);
                $changes[] = ['change' => 'price', 'product_ref' => (string) $line->getProductRef(), 'from' => self::money($current), 'to' => self::money((float) $lineEdit->unitPrice)];
            }

            $line->save($connection);
        }

        foreach ($plan['removed'] as $line) {
            $this->moveStock($line, -(float) $line->getQuantity(), $stockHeld, $connection);
            $changes[] = ['change' => 'removed', 'product_ref' => (string) $line->getProductRef(), 'quantity' => self::quantity((float) $line->getQuantity())];
            $line->delete($connection);
        }

        foreach ($plan['added'] as ['pse' => $pse, 'edit' => $lineEdit]) {
            $line = $this->addLine($order, $pse, $lineEdit, $stockHeld, $country, $locale, $connection);
            $changes[] = ['change' => 'added', 'product_ref' => (string) $line->getProductRef(), 'quantity' => self::quantity($lineEdit->quantity)];
        }

        if (null !== $edit->discount) {
            $discount = $this->amount($edit->discount, $order);

            if (round($discount, 6) !== round((float) $order->getDiscount(), 6)) {
                $changes[] = ['change' => 'discount', 'from' => self::money((float) $order->getDiscount()), 'to' => self::money($discount)];
                $order->setDiscount(self::number($discount));
            }
        }

        if (null !== $edit->postage) {
            $postage = $this->amount($edit->postage, $order);

            if (round($postage, 6) !== round((float) $order->getPostage(), 6)) {
                $changes[] = ['change' => 'postage', 'from' => self::money((float) $order->getPostage()), 'to' => self::money($postage)];
                $this->setPostage($order, $postage, $connection);
            }
        }

        $order->save($connection);

        return $changes;
    }

    private function addLine(Order $order, \Thelia\Model\ProductSaleElements $pse, OrderEditLine $lineEdit, bool $stockHeld, Country $country, string $locale, ConnectionInterface $connection): OrderProduct
    {
        $product = $pse->getProduct($connection) ?? throw new InvalidOrderEditException(self::trans('Order %ref: the product to add does not exist.', ['%ref' => (string) $order->getRef()]));
        $customerDiscount = (float) ($order->getCustomer()?->getDiscount() ?? 0);
        $prices = $pse->getPricesByCurrency($order->getCurrency(), $customerDiscount);
        $promo = 1 === (int) $pse->getPromo();
        $price = null !== $lineEdit->unitPrice ? (float) $lineEdit->unitPrice : (float) $prices->getPrice();
        $promoPrice = null !== $lineEdit->unitPrice ? (float) $lineEdit->unitPrice : (float) $prices->getPromoPrice();
        $promo = null === $lineEdit->unitPrice && $promo;

        $virtualContext = $this->virtualProductHandler->resolve($this->dispatcher, $order, $product, (int) $pse->getId());

        if ($stockHeld && $virtualContext->useStock) {
            $this->decrement((int) $pse->getId(), $lineEdit->quantity, (string) $product->getRef(), $connection);
        } elseif ($this->stockPolicy->shouldCheckAvailability(ConfigQuery::checkAvailableStock(), $virtualContext->useStock)) {
            $this->assertAvailable($lineEdit->quantity, (float) $pse->getQuantity(), (string) $product->getRef());
        }

        $line = $this->orderProductFactory->createOrderProduct(
            placedOrder: $order,
            product: $product,
            productSaleElements: $pse,
            productI18n: $this->translationProvider->getProductTranslation($locale, (int) $product->getId()),
            cartItem: new AddedLinePrice($lineEdit->quantity, $price, $promoPrice, $promo),
            virtualContext: $virtualContext,
            taxRuleI18n: $this->translationProvider->getTaxRuleTranslation($locale, (int) $product->getTaxRuleId()),
            connection: $connection,
        );

        foreach ($order->getVatExempted() ? [] : $this->taxProvider->computeTaxesForCartItem($product, $country, $price, $promoPrice, $locale) as $tax) {
            $tax->setOrderProductId($line->getId());
            $tax->save($connection);
        }

        $this->orderProductFactory->persistAttributeCombinations($line, $pse, $locale, $connection);

        return $line;
    }

    /**
     * A price corrected by hand: the line is no longer in promotion, and its taxes are those
     * of the new price in the invoice country, or the old ones in proportion when the
     * product is no longer in the catalogue.
     */
    private function reprice(OrderProduct $line, float $price, float $previous, Country $country, string $locale, ConnectionInterface $connection): void
    {
        $taxes = OrderProductTaxQuery::create()->filterByOrderProductId($line->getId())->find($connection);
        $product = ProductSaleElementsQuery::create()->findPk($line->getProductSaleElementsId(), $connection)?->getProduct($connection);

        $wasInPromo = 1 === (int) $line->getWasInPromo();
        $line->setPrice(self::number($price))->setPromoPrice(self::number($price))->setWasInPromo(0);

        if (null === $product) {
            foreach ($taxes as $tax) {
                $amount = $wasInPromo ? (float) $tax->getPromoAmount() : (float) $tax->getAmount();
                $scaled = $previous > 0 ? round($amount * $price / $previous, 6) : 0.0;
                $tax->setAmount(self::number($scaled))->setPromoAmount(self::number($scaled))->save($connection);
            }

            return;
        }

        $taxes->delete($connection);

        foreach ($this->taxProvider->computeTaxesForCartItem($product, $country, $price, $price, $locale) as $tax) {
            $tax->setOrderProductId($line->getId());
            $tax->save($connection);
        }
    }

    /**
     * The postage typed by hand, including tax, split at the rate of the old one. An order
     * that had no postage gets no tax on the new one: nothing says which rate it would be.
     */
    private function setPostage(Order $order, float $postage, ConnectionInterface $connection): void
    {
        $previous = (float) $order->getPostage();
        $factor = $previous > 0 ? $postage / $previous : 0.0;
        $order->setPostage(self::number($postage))->setPostageTax(self::number(round((float) $order->getPostageTax() * $factor, 2)));

        foreach (OrderPostageTaxQuery::create()->filterByOrderId($order->getId())->find($connection) as $share) {
            $share
                ->setUntaxedAmount(self::number(round((float) $share->getUntaxedAmount() * $factor, 6)))
                ->setAmount(self::number(round((float) $share->getAmount() * $factor, 6)))
                ->save($connection);
        }
    }

    /**
     * Whether the stock of the lines is held: once the order is paid, or from its creation
     * when its payment module takes the stock then. A change of status gives it or takes it
     * the same way.
     */
    private function holdsStock(Order $order): bool
    {
        $status = $order->getOrderStatus();

        if (true === $status?->isPaid(false)) {
            return true;
        }

        return true === $status?->isNotPaid(true) && $order->isStockManagedOnOrderCreation($this->dispatcher);
    }

    private function moveStock(OrderProduct $line, float $delta, bool $stockHeld, ConnectionInterface $connection): void
    {
        $pseId = $line->getProductSaleElementsId();

        if (null === $pseId || 1 === (int) $line->getVirtual() || 0.0 === $delta) {
            return;
        }

        if (!$stockHeld) {
            // Nothing taken yet, but a raised quantity must still be one the shop can serve,
            // as a line added is.
            if ($delta > 0 && $this->stockPolicy->shouldCheckAvailability(ConfigQuery::checkAvailableStock(), true)) {
                $available = (float) ProductSaleElementsQuery::create()->findPk($pseId, $connection)?->getQuantity();
                $this->assertAvailable((float) $line->getQuantity() + $delta, $available, (string) $line->getProductRef());
            }

            return;
        }

        if ($delta > 0) {
            $this->decrement((int) $pseId, $delta, (string) $line->getProductRef(), $connection);

            return;
        }

        $this->stockDecrementer->increment((int) $pseId, -$delta, $connection);
    }

    private function decrement(int $pseId, float $quantity, string $productRef, ConnectionInterface $connection): void
    {
        try {
            $this->stockDecrementer->decrement(
                $pseId,
                $quantity,
                guardAvailability: ConfigQuery::checkAvailableStock(),
                allowNegativeStock: (bool) (int) ConfigQuery::read('allow_negative_stock', 0),
                connection: $connection,
            );
        } catch (TheliaProcessException $shortage) {
            throw new InvalidOrderEditException(self::trans('Not enough stock of %product for this quantity.', ['%product' => $productRef]), 0, $shortage);
        }
    }

    private function assertAvailable(float $quantity, float $available, string $productRef): void
    {
        try {
            $this->stockPolicy->assertStockIsAvailable($quantity, $available, $productRef);
        } catch (TheliaProcessException $shortage) {
            throw new InvalidOrderEditException(self::trans('Not enough stock of %product for this quantity.', ['%product' => $productRef]), 0, $shortage);
        }
    }

    /**
     * @param list<int> $orderProductIds
     *
     * @return array<int, float>
     */
    private function returnedQuantities(array $orderProductIds, ConnectionInterface $connection): array
    {
        $returned = [];

        if ([] === $orderProductIds) {
            return $returned;
        }

        foreach (OrderReturnLineQuery::create()->filterByOrderProductId($orderProductIds, Criteria::IN)->find($connection) as $returnLine) {
            $id = (int) $returnLine->getOrderProductId();
            $returned[$id] = ($returned[$id] ?? 0.0) + (float) $returnLine->getQuantity();
        }

        return $returned;
    }

    private function invoiceCountry(Order $order): Country
    {
        return OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId())?->getCountry()
            ?? throw new InvalidOrderEditException(self::trans('Order %ref has no invoice address to tax its lines for.', ['%ref' => (string) $order->getRef()]));
    }

    private function amount(string $amount, Order $order): float
    {
        if (!is_numeric($amount) || (float) $amount < 0 || !self::isReasonable((float) $amount)) {
            throw new InvalidOrderEditException(self::trans('Order %ref: "%price" is not a price.', ['%ref' => (string) $order->getRef(), '%price' => $amount]));
        }

        return round((float) $amount, 2);
    }

    /**
     * @return array{float, float} the total and the tax of the order, as it computes them
     */
    private function totals(Order $order): array
    {
        $tax = 0.0;
        $total = $order->getTotalAmount($tax);

        return [round($total, 2), round((float) $tax, 2)];
    }

    private function fresh(int $orderId, ConnectionInterface $connection): Order
    {
        $this->forget();

        return OrderQuery::create()->findPk($orderId, $connection) ?? throw new InvalidOrderEditException('The order to edit is gone.');
    }

    private function forget(): void
    {
        Order::forgetTotalAmounts();
        OrderTableMap::clearInstancePool();
        OrderProductTableMap::clearInstancePool();
        OrderProductTaxTableMap::clearInstancePool();
        OrderProductAttributeCombinationTableMap::clearInstancePool();
        OrderPostageTaxTableMap::clearInstancePool();
        ProductSaleElementsTableMap::clearInstancePool();
    }

    private static function isReasonable(float $number): bool
    {
        return is_finite($number) && abs($number) <= self::MAX_NUMBER;
    }

    private static function number(float|int|string|null $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    private static function quantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function trans(string $message, array $parameters): string
    {
        try {
            return Translator::getInstance()->trans($message, $parameters);
        } catch (\RuntimeException) {
            return strtr($message, $parameters);
        }
    }
}

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

namespace Thelia\Domain\Order;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Checkout\Service\ConsentAnswerStoreInterface;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Order\Exception\CartAlreadyOrderedException;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Domain\Order\Service\OrderAddressPersister;
use Thelia\Domain\Order\Service\OrderFactory;
use Thelia\Domain\Order\Service\OrderProductFactory;
use Thelia\Domain\Order\Service\OrderRefGeneratorInterface;
use Thelia\Domain\Order\Service\OrderTransactionManager;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\Service\StockPolicy;
use Thelia\Domain\Order\Service\TaxProvider;
use Thelia\Domain\Order\Service\TranslationProvider;
use Thelia\Domain\Order\Service\VirtualProductHandler;
use Thelia\Domain\Shipping\Service\PostageTaxBreakdownCalculator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderConsent;
use Thelia\Model\OrderPostageTax;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

readonly class OrderFacade
{
    public function __construct(
        private OrderTransactionManager $orderTransactionManager,
        private OrderFactory $orderFactory,
        private OrderAddressPersister $orderAddressPersister,
        private TranslationProvider $translationProvider,
        private VirtualProductHandler $virtualProductHandler,
        private StockPolicy $stockPolicy,
        private TaxProvider $taxProvider,
        private OrderProductFactory $orderProductFactory,
        private OrderRefGeneratorInterface $orderRefGenerator,
        private StockDecrementer $stockDecrementer,
        private PostageTaxBreakdownCalculator $postageTaxBreakdownCalculator,
        private ConsentProvider $consentProvider,
        private ConsentAnswerStoreInterface $consentAnswers,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param bool $useOrderDefinedAddresses if true, the delivery and invoice OrderAddresses will be used instead of creating new OrderAdresses using Order::getChoosenXXXAddress()
     * @param bool $recordConsentAnswers     whether the buyer's consent answers get frozen onto the order. Only the
     *                                       checkout tunnel has answers to freeze: an order created outside of it
     *                                       (the back office, the command line) has no buyer at the keyboard, so
     *                                       there is nothing to record and no session to purge afterwards.
     *
     * @throws \Exception
     * @throws PropelException
     */
    public function createOrder(
        EventDispatcherInterface $dispatcher,
        ModelOrder $sessionOrder,
        CurrencyModel $currency,
        LangModel $lang,
        CartModel $cart,
        UserInterface $customer,
        bool $useOrderDefinedAddresses = false,
        bool $recordConsentAnswers = true,
    ): ModelOrder {
        if (null === $customer->getId()) {
            throw new TheliaProcessException('Customer identifier is required');
        }
        if (null === $currency->getId()) {
            throw new TheliaProcessException('Currency identifier is required');
        }
        if (null === $lang->getId()) {
            throw new TheliaProcessException('Language identifier is required');
        }
        if (null === $cart->getId()) {
            throw new TheliaProcessException('Cart identifier is required');
        }

        // Fail fast on predictable stock shortages before creating anything.
        // The authoritative guard remains the conditional UPDATE performed by
        // StockDecrementer inside the transaction.
        $this->assertCartStockIsAvailable($cart);

        $connection = $this->orderTransactionManager->begin();

        try {
            $this->refuseACartThatHasAlreadyBeenOrdered($cart, $connection);

            $placedOrder = $this->orderFactory->createFromSessionOrder($sessionOrder, $currency, $lang, $cart, $customer);

            $taxCountry = $this->orderAddressPersister->prepareOrderAddresses(
                $placedOrder,
                $cart,
                $useOrderDefinedAddresses,
                $connection
            );

            $placedOrder->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId());
            $placedOrder->save($connection);

            $this->persistPostageTaxBreakdown($placedOrder, $cart, $taxCountry, $lang, $connection);

            if ($recordConsentAnswers) {
                $this->persistConsentAcceptances($placedOrder, $lang, $connection);
            }

            $manageStockOnCreation = $placedOrder->isStockManagedOnOrderCreation($dispatcher);

            $cartItems = $cart->getCartItems();

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->getProduct();
                $productSaleElements = $cartItem->getProductSaleElements();

                $productI18n = $this->translationProvider->getProductTranslation($lang->getLocale(), $product->getId());

                // Virtual Products
                $virtualContext = $this->virtualProductHandler->resolve(
                    $dispatcher,
                    $placedOrder,
                    $product,
                    $productSaleElements->getId()
                );

                $checkStock = $this->stockPolicy->shouldCheckAvailability(ConfigQuery::checkAvailableStock(), $virtualContext->useStock);

                if ($this->stockPolicy->shouldDecrementStock($manageStockOnCreation, $virtualContext->useStock)) {
                    try {
                        // Atomic conditional decrement: check and write are a single
                        // statement, so concurrent checkouts cannot oversell.
                        $this->stockDecrementer->decrement(
                            $productSaleElements->getId(),
                            (float) $cartItem->getQuantity(),
                            guardAvailability: $checkStock,
                            allowNegativeStock: (bool) (int) ConfigQuery::read('allow_negative_stock', 0),
                            connection: $connection,
                        );
                    } catch (TheliaProcessException $shortage) {
                        // The decrementer knows a row id and a quantity; only here is
                        // there a product to name, and a caller told "not enough stock"
                        // about a cart of ten lines has nothing to act on. The type is
                        // what a caller maps to an answer: everything else this class
                        // raises is a defect, not a shortage.
                        throw new StockShortageException($product->getRef(), previous: $shortage);
                    }
                } elseif ($checkStock) {
                    // Stock is managed later (e.g. at payment): keep the
                    // advisory availability check on order creation.
                    $this->stockPolicy->assertStockIsAvailable(
                        $cartItem->getQuantity(),
                        $productSaleElements->getQuantity(),
                        $product->getRef(),
                    );
                }

                // Taxes
                $taxRuleI18n = $this->translationProvider->getTaxRuleTranslation($lang->getLocale(), $product->getTaxRuleId());

                $taxDetails = $this->taxProvider->computeTaxesForCartItem(
                    $product,
                    $taxCountry,
                    (float) $cartItem->getPrice(),
                    (float) $cartItem->getPromoPrice(),
                    $lang->getLocale()
                );

                // Create OrderProduct + taxes + attributes
                $orderProduct = $this->orderProductFactory->createOrderProduct(
                    placedOrder: $placedOrder,
                    product: $product,
                    productSaleElements: $productSaleElements,
                    productI18n: $productI18n,
                    cartItem: $cartItem,
                    virtualContext: $virtualContext,
                    taxRuleI18n: $taxRuleI18n,
                    connection: $connection
                );

                /** @var OrderProductTax $tax */
                foreach ($taxDetails as $tax) {
                    $tax->setOrderProductId($orderProduct->getId());
                    $tax->save($connection);
                }

                $this->orderProductFactory->persistAttributeCombinations(
                    $orderProduct,
                    $productSaleElements,
                    $lang->getLocale(),
                    $connection
                );
            }

            // Allocate the ref from the gapless sequence as the very last
            // operation: the counter lock is only held for the commit window,
            // and any earlier failure rolls the increment back with the order.
            $placedOrder
                ->setRef($this->orderRefGenerator->generate($connection))
                ->setDisableVersioning(true)
                ->save($connection);

            $this->orderTransactionManager->commit($connection);

            if ($recordConsentAnswers) {
                // Only once the answers are on the order: dropped before the commit, a
                // rollback would leave the buyer with boxes to tick again and no way to know it.
                $this->consentAnswers->clear();
            }

            return $placedOrder;
        } catch (\Throwable $throwable) {
            $this->orderTransactionManager->rollback($connection);
            throw $throwable;
        }
    }

    /**
     * The one guarantee that a cart is ordered once, taken inside the transaction the
     * order is written in and before its first row.
     *
     * Everything above this is a narrowing, not a guarantee: the placement re-reads the
     * table before it starts, and it takes a lock — but a lock is only as shared as its
     * store, the shipped default is a file on the local disk, and a second application
     * server reads the very same "no order yet" and writes a second order. Only the
     * database arbitrates between two nodes.
     *
     * The row of the cart is what is locked, and not the orders of that cart. Locking a
     * set of orders that is empty — which is the normal case — takes a gap lock on
     * `cart_id`, and that gap spans the carts either side of it: measured on MariaDB
     * 10.11, `SELECT … WHERE cart_id = 100 FOR UPDATE` blocks the insert of an order for
     * cart 101. Two placements of two different carts would each hold that gap, gap locks
     * being compatible with one another, and each would then wait on the other's insert:
     * a deadlock, produced by the guard, between two requests that have nothing to do with
     * each other. The cart row exists, so locking it is a plain record lock and it
     * serialises exactly the two requests that are about the same cart.
     *
     * The re-read that follows is a consistent read and it is not stale: the read view of
     * a transaction is opened by its first consistent read, and the statement before it is
     * a locking one, which opens none. Measured on the same server — a row committed by
     * another session while this transaction holds a locking read is seen by the plain
     * read that comes after it.
     *
     * A cancelled order is not one: a payment that did not go through takes the order back
     * and leaves the buyer with the cart they still have, and refusing to let them order it
     * again would strand them. That is also why the guard is not a unique index on
     * `cart_id`, which would refuse the second, legitimate order as well.
     *
     * @throws CartAlreadyOrderedException when this very cart already carries an order that stands
     * @throws PropelException
     */
    private function refuseACartThatHasAlreadyBeenOrdered(CartModel $cart, ConnectionInterface $connection): void
    {
        $cartId = (int) $cart->getId();

        $lockTheCart = $connection->prepare('SELECT `id` FROM `cart` WHERE `id` = :cartId FOR UPDATE');
        $lockTheCart->bindValue(':cartId', $cartId, \PDO::PARAM_INT);
        $lockTheCart->execute();

        $existingOrder = $this->liveOrderOf($cartId, $connection);

        if ($existingOrder instanceof ModelOrder) {
            throw new CartAlreadyOrderedException((int) $existingOrder->getId(), $cartId);
        }
    }

    /**
     * The order of this cart that is still waiting for its payment, when there is one.
     *
     * The same reading as the guard above, taken before the placement starts: a new
     * payment attempt on a cart that already carries an unpaid order either reuses that
     * order or cancels it first, and the guard then lets the cart through. An order that
     * stands but is no longer unpaid — paid, further along, or refunded — is not handed
     * back: the cart is consumed, and the guard is what refuses it. This is the exact
     * complement of the reading Session::hasBeenPaidFor() makes of the same order.
     */
    public function findUnpaidOrderOf(CartModel $cart): ?ModelOrder
    {
        if (null === $cart->getId()) {
            return null;
        }

        $liveOrder = $this->liveOrderOf((int) $cart->getId());

        if (!$liveOrder instanceof ModelOrder) {
            return null;
        }

        return $liveOrder->isPaid(false) || $liveOrder->isRefunded(false) ? null : $liveOrder;
    }

    /**
     * The most recent order of the cart that was not cancelled: the one that stands.
     *
     * Cancelled is read on the effective code of the status, so a status of the shop's
     * own that stands for cancelled excludes the order exactly like the native one. That
     * reading lives in the model, not in a column the query could filter on: the few
     * orders a cart can carry are fetched with their status and sifted here.
     */
    private function liveOrderOf(int $cartId, ?ConnectionInterface $connection = null): ?ModelOrder
    {
        $orders = OrderQuery::create()
            ->filterByCartId($cartId)
            ->joinWithOrderStatus()
            ->orderById(Criteria::DESC)
            ->find($connection);

        foreach ($orders as $order) {
            if (!$order->isCancelled(false)) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Freezes how the postage tax of the order splits between the tax rules its
     * goods follow.
     *
     * Nothing is written when the shop applies a single rule to the postage,
     * which is the default: an order with no line reads as the one rate named
     * in `postage_tax_rule_title`, exactly like every order placed so far.
     *
     * @throws PropelException
     */
    private function persistPostageTaxBreakdown(
        ModelOrder $placedOrder,
        CartModel $cart,
        Country $taxCountry,
        LangModel $lang,
        ConnectionInterface $connection,
    ): void {
        $postageTax = (float) $placedOrder->getPostageTax();
        $untaxedPostage = (float) $placedOrder->getPostage() - $postageTax;

        $lines = $this->postageTaxBreakdownCalculator->splitForOrder(
            $cart,
            $taxCountry,
            OrderAddressQuery::create()->findPk($placedOrder->getDeliveryOrderAddressId())?->getState(),
            $untaxedPostage,
            $postageTax,
            $lang->getLocale(),
        );

        foreach ($lines as $line) {
            (new OrderPostageTax())
                ->setOrderId($placedOrder->getId())
                ->setTitle($line->title)
                ->setDescription($line->description)
                ->setUntaxedAmount((string) $line->untaxedAmount)
                ->setAmount((string) $line->amount)
                ->save($connection);
        }
    }

    /**
     * Freezes what the buyer answered to every consent the shop was asking for.
     *
     * One row per active consent, ticked or not: a refusal is as much of an answer as an
     * acceptance, and an order with no row for an optional consent would later read as
     * an order placed before that consent existed.
     *
     * Everything comes from the answer as it was given — the wording, the long text and
     * the moment the box was answered, held in the session of a buyer walking the screens
     * of a theme or in the request of a caller with none — and nothing is read back from the consent
     * table. That is the whole point: a merchant who rewords a consent between the tick
     * and the payment must not end up with an order stating the buyer agreed to a
     * sentence they never saw. The one legitimate re-read is the consent nobody
     * answered: there is no displayed wording to copy, so the current one goes down
     * against a refusal. The address is the one the answer came from.
     *
     * Only called for orders placed through the checkout tunnel — see the
     * $recordConsentAnswers guard in createOrder(). An order created from the back
     * office or the command line has no buyer answering boxes, so it gets no rows here:
     * writing "declined" against the admin's IP would be a false record, not a proof.
     *
     * @throws PropelException
     */
    private function persistConsentAcceptances(
        ModelOrder $placedOrder,
        LangModel $lang,
        ConnectionInterface $connection,
    ): void {
        $answers = $this->consentAnswers->answers();
        $ipAddress = $this->requestStack->getMainRequest()?->getClientIp();
        $locale = (string) $lang->getLocale();

        foreach ($this->consentProvider->activeConsents() as $consent) {
            $code = (string) $consent->getCode();
            $answer = $answers[$code] ?? null;

            (new OrderConsent())
                ->setOrderId($placedOrder->getId())
                ->setConsentCode($code)
                ->setTitle($answer['title'] ?? $this->consentProvider->title($consent, $locale))
                ->setDescription($answer['description'] ?? $this->consentProvider->description($consent, $locale))
                ->setAccepted(($answer['accepted'] ?? false) ? 1 : 0)
                ->setAnsweredAt($answer['answeredAt'] ?? new \DateTimeImmutable())
                ->setIpAddress($ipAddress)
                ->save($connection);
        }
    }

    /**
     * Read-only pre-check on the whole cart, before any row is written.
     *
     * Virtual products are skipped: their stock usage is decided by an event
     * that requires the placed order, so they are handled in the main loop.
     *
     * @throws StockShortageException
     */
    private function assertCartStockIsAvailable(CartModel $cart): void
    {
        if (!ConfigQuery::checkAvailableStock()) {
            return;
        }

        foreach ($cart->getCartItems() as $cartItem) {
            if (1 === $cartItem->getProduct()->getVirtual()) {
                continue;
            }

            $this->stockPolicy->assertStockIsAvailable(
                $cartItem->getQuantity(),
                $cartItem->getProductSaleElements()->getQuantity(),
                $cartItem->getProduct()->getRef(),
            );
        }
    }
}

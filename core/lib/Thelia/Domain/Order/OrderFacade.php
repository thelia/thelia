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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Checkout\Service\ConsentAcceptanceStore;
use Thelia\Domain\Checkout\Service\ConsentProvider;
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
        private ConsentAcceptanceStore $consentAcceptanceStore,
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
                    // Atomic conditional decrement: check and write are a single
                    // statement, so concurrent checkouts cannot oversell.
                    $this->stockDecrementer->decrement(
                        $productSaleElements->getId(),
                        (float) $cartItem->getQuantity(),
                        guardAvailability: $checkStock,
                        allowNegativeStock: (bool) (int) ConfigQuery::read('allow_negative_stock', 0),
                        connection: $connection,
                    );
                } elseif ($checkStock) {
                    // Stock is managed later (e.g. at payment): keep the
                    // advisory availability check on order creation.
                    $this->stockPolicy->assertStockIsAvailable(
                        $cartItem->getQuantity(),
                        $productSaleElements->getQuantity(),
                        'Not enough stock'
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
                $this->consentAcceptanceStore->clear();
            }

            return $placedOrder;
        } catch (\Throwable $throwable) {
            $this->orderTransactionManager->rollback($connection);
            throw $throwable;
        }
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
     * Everything comes from the answer held in the session — the wording, the long text
     * and the moment the box was answered — and nothing is read back from the consent
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
        $answers = $this->consentAcceptanceStore->answers();
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
     * @throws TheliaProcessException
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
                'Not enough stock'
            );
        }
    }
}

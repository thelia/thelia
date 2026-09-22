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
use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Checkout\Service\GiftWrappingProvider;
use Thelia\Domain\Taxation\TaxEngine\OrderProductTaxCollection;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\Country;
use Thelia\Model\GiftWrapping;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\State;
use Thelia\Model\TaxRule;

/**
 * Turns the gift wrapping a cart carries into a line of the order.
 *
 * A line, and not a column beside the postage: an order line already knows how to hold a
 * price, a tax and a wording frozen at the moment of the sale, and everything downstream
 * — the invoice, the credit note, the accounting export, the totals — already walks the
 * lines. A column would have to be taught to each of them, one at a time, and forgotten
 * by the next one written.
 *
 * What is frozen is what the buyer was shown: the wording in the language of the order,
 * the price as it stood, and the name of the tax rule that was applied. Renaming or
 * repricing the service afterwards, or deleting it outright, leaves the invoice saying
 * what it said the day it was issued.
 */
readonly class GiftWrappingLineFactory
{
    public function __construct(
        private GiftWrappingProvider $giftWrappingProvider,
        private TranslationProvider $translationProvider,
        private TaxCalculatorFactoryInterface $taxCalculatorFactory,
    ) {
    }

    /**
     * Writes the service line and its taxes, and answers null when there is nothing to
     * write — which is the normal case: no wrapping picked, or one the shop stopped
     * offering while the cart was sitting there.
     *
     * @throws PropelException
     */
    public function createFor(
        ModelOrder $placedOrder,
        CartModel $cart,
        Country $taxCountry,
        ?State $taxState,
        string $locale,
        ConnectionInterface $connection,
    ): ?OrderProduct {
        $giftWrapping = $this->giftWrappingProvider->findActive(
            null === $cart->getGiftWrappingId() ? null : (int) $cart->getGiftWrappingId()
        );

        if (!$giftWrapping instanceof GiftWrapping) {
            return null;
        }

        $untaxedPrice = (float) $giftWrapping->getPrice();
        $taxRule = $giftWrapping->getTaxRule();
        $taxes = $this->computeTaxes($taxRule, $taxCountry, $taxState, $untaxedPrice, $locale);
        $taxRuleI18n = $this->translationProvider->getTaxRuleTranslation($locale, (int) $giftWrapping->getTaxRuleId());

        $orderProduct = (new OrderProduct())
            ->setOrderId($placedOrder->getId())
            // No catalogue reference to copy, so the code of the service stands in: an
            // export grouping by reference then groups the wrappings together, which is
            // the reading a merchant wants of them.
            ->setProductRef((string) $giftWrapping->getCode())
            ->setProductSaleElementsRef((string) $giftWrapping->getCode())
            ->setProductSaleElementsId(null)
            ->setTitle($this->giftWrappingProvider->title($giftWrapping, $locale))
            ->setDescription($this->giftWrappingProvider->description($giftWrapping, $locale))
            ->setQuantity(1)
            ->setPrice((string) $untaxedPrice)
            ->setPromoPrice('0.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setVirtual(0)
            ->setIsOffered(0)
            ->setTaxRuleTitle($taxRuleI18n->getTitle())
            ->setTaxRuleDescription($taxRuleI18n->getDescription())
            ->setLineType(OrderProduct::LINE_TYPE_SERVICE);

        $orderProduct->save($connection);

        foreach ($taxes as $tax) {
            $tax->setOrderProductId($orderProduct->getId());
            $tax->save($connection);
        }

        return $orderProduct;
    }

    /**
     * The tax the wrapping's own rule puts on its price, broken down the way a product
     * line's is so the invoice can state each tax by name.
     *
     * A wrapping the shop offers still goes through the engine: the rule may be there for
     * the accounting, and a zero amount recorded under its name reads better on the
     * invoice than no line at all.
     *
     * @return list<OrderProductTax>
     *
     * @throws PropelException
     */
    private function computeTaxes(
        ?TaxRule $taxRule,
        Country $taxCountry,
        ?State $taxState,
        float $untaxedPrice,
        string $locale,
    ): array {
        if (!$taxRule instanceof TaxRule) {
            return [];
        }

        $taxCollection = new OrderProductTaxCollection();

        $this->taxCalculatorFactory
            ->createTaxCalculator()
            ->loadTaxRuleWithoutProduct($taxRule, $taxCountry, $taxState)
            ->getTaxedPrice($untaxedPrice, $taxCollection, $locale);

        $taxes = [];

        foreach ($taxCollection as $tax) {
            /* @var OrderProductTax $tax */
            // The line is never in promotion, and the column is not nullable: the promo
            // amount mirrors the plain one so a reader taking either reads the same tax.
            $taxes[] = $tax->setPromoAmount($tax->getAmount());
        }

        return $taxes;
    }
}

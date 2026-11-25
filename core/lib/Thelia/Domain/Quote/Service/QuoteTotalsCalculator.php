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

namespace Thelia\Domain\Quote\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Pricing\MoneyRounding;
use Thelia\Domain\Quote\DTO\QuoteSumLine;
use Thelia\Domain\Quote\DTO\QuoteTotals;
use Thelia\Domain\Shipping\Enum\PostageTaxStrategy;
use Thelia\Domain\Shipping\Service\PostageTaxBreakdownCalculator;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Lang;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItem;
use Thelia\Model\QuoteRevision;
use Thelia\Model\State;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

/**
 * What a revision of a quote comes to, worked out the way a cart and an order work it out.
 *
 * - The taxes of a line follow the tax rule of its product (the rule written on the line when the
 *   product is gone), for the country of the delivery address of the quote, or the default
 *   country of the shop when the quote has no address; the tax engine of the shop does the sums.
 * - A line is totalled as the cart totals it, by the rounding mode of the shop.
 * - The discount is an amount with taxes, taken off the total of the lines with taxes, and never
 *   below nothing; the postage comes after it.
 * - The postage is the untaxed amount the shop negotiated. It is taxed as an order edited by the
 *   shop taxes it: split over the rules of the goods when the shop asks for it, else by the tax
 *   rule of the delivery module of the quote, else by the shop-wide tax rule of postages.
 *
 * A buyer exempted from VAT is not looked for here: the exemption belongs to the cart, and applies
 * when the quote is accepted.
 */
final readonly class QuoteTotalsCalculator
{
    public function __construct(
        private TaxCalculatorFactoryInterface $taxCalculatorFactory,
        private PostageTaxBreakdownCalculator $postageTaxBreakdownCalculator,
        private QuoteSums $sums,
    ) {
    }

    /**
     * @param iterable<QuoteItem> $items the lines of the revision
     */
    public function calculate(Quote $quote, QuoteRevision $revision, iterable $items): QuoteTotals
    {
        $items = [...$items];
        [$country, $state] = $this->destinationOf($quote);
        $locale = (string) ($quote->getLocale() ?: Lang::getDefaultLanguage()->getLocale());
        [$products, $taxRules] = $this->goodsOf($items);

        $lines = [];

        foreach ($items as $item) {
            $unitPrice = (float) $item->getPrice();
            $unitTaxed = $this->calculatorFor($item, $country, $state, $products, $taxRules)?->getTaxedPrice($unitPrice) ?? $unitPrice;
            $lines[] = new QuoteSumLine($unitPrice, (float) $unitTaxed, (float) $item->getQuantity());
        }

        return $this->sums->totals(
            $lines,
            ConfigQuery::isRoundingModeRoundingOfSums(),
            (float) $revision->getDiscount(),
            (float) $revision->getPostage(),
            fn (float $postageUntaxed): float => $this->postageTax($revision->getDeliveryModuleId(), $postageUntaxed, $items, $products, $country, $state, $locale),
        );
    }

    /**
     * The tax on the postage of a revision when it ships to this destination instead of the one
     * the quote names: the amount of the postage and the carrier are those of the revision.
     *
     * @param iterable<QuoteItem> $items the lines of the revision
     */
    public function postageTaxFor(Quote $quote, QuoteRevision $revision, iterable $items, Country $country, ?State $state): string
    {
        $locale = (string) ($quote->getLocale() ?: Lang::getDefaultLanguage()->getLocale());
        $postageUntaxed = MoneyRounding::toTheCent((float) $revision->getPostage());

        $items = [...$items];

        return $this->amount($this->postageTax($revision->getDeliveryModuleId(), $postageUntaxed, $items, $this->goodsOf($items)[0], $country, $state, $locale));
    }

    /**
     * @return array{Country, State|null}
     */
    private function destinationOf(Quote $quote): array
    {
        $address = null === $quote->getDeliveryAddressId() ? null : $quote->getDeliveryAddress();

        if (null !== $address && null !== $address->getCountryId()) {
            return [$address->getCountry(), $address->getState()];
        }

        return [Country::getDefaultCountry(), null];
    }

    /**
     * The products and the tax rules the lines stand for, read once for all of them: a line costs
     * no statement.
     *
     * @param list<QuoteItem> $items
     *
     * @return array{array<int, Product>, array<int, TaxRule>} the products by id, the tax rules by id (those of the products, and those of the lines whose product is gone)
     */
    private function goodsOf(array $items): array
    {
        $productIds = array_values(array_unique(array_filter(array_map(static fn (QuoteItem $item): ?int => $item->getProductId(), $items))));
        $products = [];

        if ([] !== $productIds) {
            // With the translation the cart lines read off a product (the postage tax splits over a cart that is built from them).
            foreach (ProductQuery::create()->filterById($productIds, Criteria::IN)->joinWithI18n(Lang::getDefaultLanguage()->getLocale())->find() as $product) {
                $products[(int) $product->getId()] = $product;
            }
        }

        $taxRuleIds = [];

        foreach ($items as $item) {
            $product = null === $item->getProductId() ? null : ($products[(int) $item->getProductId()] ?? null);
            $taxRuleId = null === $product ? $item->getTaxRuleId() : $product->getTaxRuleId();

            if (null !== $taxRuleId) {
                $taxRuleIds[(int) $taxRuleId] = (int) $taxRuleId;
            }
        }

        $taxRules = [];

        if ([] !== $taxRuleIds) {
            foreach (TaxRuleQuery::create()->filterById(array_values($taxRuleIds), Criteria::IN)->find() as $taxRule) {
                $taxRules[(int) $taxRule->getId()] = $taxRule;
            }
        }

        // What the engine reads off a product is read from what was just loaded.
        foreach ($products as $product) {
            $taxRule = null === $product->getTaxRuleId() ? null : ($taxRules[(int) $product->getTaxRuleId()] ?? null);

            if (null !== $taxRule) {
                $product->setTaxRule($taxRule);
            }
        }

        return [$products, $taxRules];
    }

    /**
     * @param array<int, Product> $products
     * @param array<int, TaxRule> $taxRules
     */
    private function calculatorFor(QuoteItem $item, Country $country, ?State $state, array $products, array $taxRules): ?TaxCalculatorInterface
    {
        $calculator = $this->taxCalculatorFactory->createTaxCalculator();
        $product = null === $item->getProductId() ? null : ($products[(int) $item->getProductId()] ?? null);

        if (null !== $product) {
            return isset($taxRules[(int) $product->getTaxRuleId()]) ? $calculator->load($product, $country, $state) : null;
        }

        $taxRule = null === $item->getTaxRuleId() ? null : ($taxRules[(int) $item->getTaxRuleId()] ?? null);

        return null === $taxRule ? null : $calculator->loadTaxRuleWithoutProduct($taxRule, $country, $state);
    }

    /**
     * @param list<QuoteItem>     $items
     * @param array<int, Product> $products
     */
    private function postageTax(?int $deliveryModuleId, float $untaxedPostage, array $items, array $products, Country $country, ?State $state, string $locale): float
    {
        if ($untaxedPostage <= 0.0) {
            return 0.0;
        }

        $split = $this->splitOverTheGoods($untaxedPostage, $items, $products, $country, $state, $locale);

        if (null !== $split) {
            return $split;
        }

        $module = null === $deliveryModuleId ? null : ModuleQuery::create()->findPk($deliveryModuleId);

        try {
            $instance = $module?->createInstance();
        } catch (\Throwable) {
            $instance = null;
        }

        if (null !== $instance && method_exists($instance, 'buildOrderPostage')) {
            return MoneyRounding::toTheCent((float) $instance->buildOrderPostage($untaxedPostage, $country, $locale)->getAmountTax());
        }

        $taxRule = (int) ConfigQuery::read('taxrule_id_delivery_module', '0');
        $taxRule = 0 === $taxRule ? null : TaxRuleQuery::create()->findPk($taxRule);

        if (null === $taxRule) {
            return 0.0;
        }

        return MoneyRounding::toTheCent((float) $this->taxCalculatorFactory->createTaxCalculator()
            ->loadTaxRuleWithoutProduct($taxRule, $country, $state)
            ->getTaxAmountFromUntaxedPrice($untaxedPostage));
    }

    /**
     * The tax of the postage when the shop makes it follow the goods, null when it does not (or
     * when the goods carry no rule to follow).
     *
     * @param list<QuoteItem>     $items
     * @param array<int, Product> $products
     */
    private function splitOverTheGoods(float $untaxedPostage, array $items, array $products, Country $country, ?State $state, string $locale): ?float
    {
        if (PostageTaxStrategy::SINGLE_RULE === PostageTaxStrategy::fromShopConfiguration()) {
            return null;
        }

        // The breakdown reads the goods off a cart: one is built here, and never saved.
        $cart = new Cart();

        foreach ($items as $item) {
            $product = null === $item->getProductId() ? null : ($products[(int) $item->getProductId()] ?? null);

            if (null === $product) {
                continue;
            }

            $line = new CartItem();
            $line->setProduct($product)
                ->setQuantity((float) $item->getQuantity())
                ->setPrice((string) $item->getPrice())
                ->setPromo(0);
            $cart->addCartItem($line);
        }

        $postage = new OrderPostage($untaxedPostage, 0.0);
        $postage->setUntaxedAmount($untaxedPostage);

        try {
            $this->postageTaxBreakdownCalculator->applyToPostage($postage, $cart, $country, $state, $locale);
        } finally {
            // A line set on a product is added to the product too, and a product comes from the pool
            // that every later reader of the process shares: it must not keep lines that exist nowhere.
            foreach ($products as $product) {
                $product->clearCartItems();
            }
        }

        return [] === $postage->getTaxBreakdown() ? null : MoneyRounding::toTheCent((float) $postage->getAmountTax());
    }

    private function amount(float $value): string
    {
        return number_format(round($value, 6), 6, '.', '');
    }
}

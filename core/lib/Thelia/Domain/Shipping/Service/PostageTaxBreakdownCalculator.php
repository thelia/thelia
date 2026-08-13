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

namespace Thelia\Domain\Shipping\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Shipping\DTO\PostageTaxLine;
use Thelia\Domain\Shipping\Enum\PostageTaxStrategy;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\Lang;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

/**
 * Splits the tax on a postage between the tax rules the goods of a cart follow.
 *
 * A cart holding a case at 20 % and a book at 5.5 % pays one postage, and the
 * European rule is that the postage follows the goods. Thelia has always taxed
 * it under a single rule, so the split only happens when the shop asks for it
 * through the `postage_tax_strategy` variable: on the default `single_rule`
 * this class returns nothing and leaves the postage exactly as the delivery
 * module quoted it.
 *
 * The untaxed postage is the part that stays fixed. It is the price the module
 * quoted, and the merchant charges it whatever the destination; the tax is the
 * derived figure, so splitting recomputes the tax and the taxed total from it.
 */
readonly class PostageTaxBreakdownCalculator
{
    public function __construct(
        private TaxCalculatorFactoryInterface $taxCalculatorFactory,
    ) {
    }

    /**
     * Rewrites the tax carried by an OrderPostage so that it follows the goods,
     * and hands the breakdown back on the value object.
     *
     * A no-op on `single_rule`, on an untaxed or free postage, and on a cart
     * whose lines carry no usable tax rule: the postage keeps the amount, the
     * tax and the rule title the delivery module produced.
     *
     * @throws PropelException
     */
    public function applyToPostage(
        OrderPostage $postage,
        Cart $cart,
        Country $country,
        ?State $state = null,
        ?string $locale = null,
    ): void {
        $untaxedPostage = round((float) $postage->getUntaxedAmount(), 2);

        $lines = $this->split($cart, $country, $state, $untaxedPostage, $locale);

        if ([] === $lines) {
            return;
        }

        $tax = round(array_sum(array_map(static fn (PostageTaxLine $line): float => $line->amount, $lines)), 2);

        $postage->setUntaxedAmount($untaxedPostage);
        $postage->setAmountTax($tax);
        $postage->setAmount(round($untaxedPostage + $tax, 2));
        $postage->setTaxBreakdown($lines);
    }

    /**
     * The breakdown of a postage already placed on an order.
     *
     * The order columns are what the customer was charged, so they are the
     * reference: the lines are recomputed from the cart, then the rounding
     * remainder of each column is given to the largest line so that
     * sum(untaxed_amount) and sum(amount) match the order exactly.
     *
     * @return list<PostageTaxLine>
     *
     * @throws PropelException
     */
    public function splitForOrder(
        Cart $cart,
        Country $country,
        ?State $state,
        float $untaxedPostage,
        float $postageTax,
        ?string $locale = null,
    ): array {
        $lines = $this->split($cart, $country, $state, round($untaxedPostage, 2), $locale);

        if ([] === $lines) {
            return [];
        }

        $taxTotal = array_sum(array_map(static fn (PostageTaxLine $line): float => $line->amount, $lines));

        return $this->giveRemainderToTheLargestLine($lines, round($postageTax - $taxTotal, 2));
    }

    /**
     * @return list<PostageTaxLine>
     *
     * @throws PropelException
     */
    private function split(
        Cart $cart,
        Country $country,
        ?State $state,
        float $untaxedPostage,
        ?string $locale,
    ): array {
        $strategy = PostageTaxStrategy::fromShopConfiguration();

        if (PostageTaxStrategy::SINGLE_RULE === $strategy) {
            return [];
        }

        if ($untaxedPostage <= 0.0 || null === $country->getId()) {
            return [];
        }

        $groups = $this->groupCartByTaxRule($cart, $country, $state);

        if ([] === $groups) {
            return [];
        }

        if (PostageTaxStrategy::HIGHEST_RATE === $strategy) {
            $groups = [$this->highestRatedGroup($groups)];
        }

        $locale ??= Lang::getDefaultLanguage()->getLocale();
        $shares = $this->shareOut($untaxedPostage, array_map(static fn (array $group): float => $group['base'], $groups));

        $lines = [];

        foreach ($groups as $index => $group) {
            /** @var TaxRule $taxRule */
            $taxRule = $group['taxRule'];
            /** @var TaxCalculatorInterface $calculator */
            $calculator = $group['calculator'];

            $taxRule->setLocale($locale);
            $title = (string) $taxRule->getTitle();

            $lines[] = new PostageTaxLine(
                '' === $title ? \sprintf('Tax rule #%d', $taxRule->getId()) : $title,
                $taxRule->getDescription(),
                $shares[$index],
                round((float) $calculator->getTaxAmountFromUntaxedPrice($shares[$index]), 2),
            );
        }

        return $lines;
    }

    /**
     * The untaxed value each tax rule of the cart accounts for, with the tax
     * calculator already loaded for the destination.
     *
     * @return list<array{base: float, rate: float, taxRule: TaxRule, calculator: TaxCalculatorInterface}>
     *
     * @throws PropelException
     */
    private function groupCartByTaxRule(Cart $cart, Country $country, ?State $state): array
    {
        $bases = [];

        /** @var CartItem $cartItem */
        foreach ($cart->getCartItems() as $cartItem) {
            $taxRuleId = (int) $cartItem->getProduct()->getTaxRuleId();

            if (0 === $taxRuleId) {
                continue;
            }

            $bases[$taxRuleId] = ($bases[$taxRuleId] ?? 0.0) + $cartItem->getTotalRealPrice();
        }

        $groups = [];

        foreach ($bases as $taxRuleId => $base) {
            if ($base <= 0.0) {
                continue;
            }

            $taxRule = TaxRuleQuery::create()->findPk($taxRuleId);

            if (!$taxRule instanceof TaxRule) {
                continue;
            }

            $calculator = $this->taxCalculatorFactory->createTaxCalculator();
            $calculator->loadTaxRuleWithoutProduct($taxRule, $country, $state);

            $groups[] = [
                'base' => $base,
                // Per hundred, so that two rules can be compared whatever they hold.
                'rate' => (float) $calculator->getTaxAmountFromUntaxedPrice(100.0),
                'taxRule' => $taxRule,
                'calculator' => $calculator,
            ];
        }

        return $groups;
    }

    /**
     * @param list<array{base: float, rate: float, taxRule: TaxRule, calculator: TaxCalculatorInterface}> $groups
     *
     * @return array{base: float, rate: float, taxRule: TaxRule, calculator: TaxCalculatorInterface}
     */
    private function highestRatedGroup(array $groups): array
    {
        $highest = $groups[0];

        foreach ($groups as $group) {
            // A tie is settled on the value at stake, so the answer does not
            // depend on the order the cart items happen to come back in.
            if ($group['rate'] > $highest['rate']
                || ($group['rate'] === $highest['rate'] && $group['base'] > $highest['base'])) {
                $highest = $group;
            }
        }

        return $highest;
    }

    /**
     * Spreads an amount over shares proportional to the given bases.
     *
     * Every share is rounded to the cent and the largest one takes what the
     * rounding left over, so the shares always add up to the amount exactly.
     *
     * @param list<float> $bases
     *
     * @return list<float>
     */
    private function shareOut(float $amount, array $bases): array
    {
        $total = array_sum($bases);
        $largest = (int) array_search(max($bases), $bases, true);

        $shares = [];
        $distributed = 0.0;

        foreach ($bases as $index => $base) {
            if ($index === $largest) {
                $shares[$index] = 0.0;

                continue;
            }

            $shares[$index] = round($amount * $base / $total, 2);
            $distributed += $shares[$index];
        }

        $shares[$largest] = round($amount - $distributed, 2);

        ksort($shares);

        return array_values($shares);
    }

    /**
     * @param list<PostageTaxLine> $lines
     *
     * @return list<PostageTaxLine>
     */
    private function giveRemainderToTheLargestLine(array $lines, float $remainder): array
    {
        if (0.0 === $remainder) {
            return $lines;
        }

        $largest = 0;

        foreach ($lines as $index => $line) {
            if ($line->untaxedAmount > $lines[$largest]->untaxedAmount) {
                $largest = $index;
            }
        }

        $lines[$largest] = $lines[$largest]->withAmounts(
            $lines[$largest]->untaxedAmount,
            round($lines[$largest]->amount + $remainder, 2),
        );

        return $lines;
    }
}

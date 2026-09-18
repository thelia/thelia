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

namespace Thelia\Domain\Pricing\Rule\Conversion;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleCreateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Sale;
use Thelia\Model\SaleCustomerQuery;
use Thelia\Model\SaleProductQuery;

/**
 * Turns a flash sale into catalog price rules, so a merchant moves an operation to
 * the mechanism that follows the catalog without typing it again.
 *
 * The sale is left exactly as it is, and the rules come out turned off: the merchant
 * turns the sale off and the rules on when they choose to, and nothing prices
 * twice in between.
 *
 * A sale selects (product, attribute value | any) pairs, combined with OR; a rule
 * combines its criterion types with AND. The pairs are therefore grouped by the set
 * of attribute values they narrow the product down to, and each group becomes one
 * rule - one rule for the usual sale, several for the ones mixing whole products
 * and single combinations.
 */
class SaleToPriceRuleConverter
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function convert(Sale $sale, ?string $title = null): ConversionResult
    {
        $locale = Lang::getDefaultLanguage()->getLocale();
        $sale->setLocale($locale);
        $title ??= (string) $sale->getTitle();
        $warnings = [];

        $groups = $this->productGroups($sale);

        if ([] === $groups) {
            $groups = ['' => []];
        }

        if (\count($groups) > 1) {
            $warnings[] = \sprintf('The sale narrows some products down to attribute values: it became %d rules, one per set of attribute values.', \count($groups));
        }

        [$effectType, $percentage, $valuesByCurrency, $effectWarning] = $this->effectOf($sale);

        if (null !== $effectWarning) {
            $warnings[] = $effectWarning;
        }

        /** @var list<int> $customerIds */
        $customerIds = array_map('intval', SaleCustomerQuery::create()
            ->filterBySaleId($sale->getId())
            ->select('CustomerId')
            ->find()
            ->getData());

        $rules = [];
        $index = 0;

        foreach ($groups as $attributeAvIds => $productIds) {
            ++$index;
            $criteria = [];

            if ([] !== $productIds) {
                $criteria[CatalogPriceRule::CRITERION_PRODUCT] = $productIds;
            }

            if ('' !== (string) $attributeAvIds) {
                $criteria[CatalogPriceRule::CRITERION_ATTRIBUTE_AV] = array_map('intval', explode(',', (string) $attributeAvIds));
            }

            $event = (new CatalogPriceRuleCreateEvent())
                ->setLocale($locale)
                ->setTitle(\count($groups) > 1 ? \sprintf('%s (%d)', $title, $index) : $title)
                ->setDescription($sale->getDescription())
                ->setActive(false)
                ->setPriority(100)
                ->setStopProcessing(false)
                ->setStartDate($sale->getStartDate())
                ->setEndDate($sale->getEndDate())
                ->setEffectType($effectType)
                ->setPercentageValue($percentage)
                ->setEffectValuesByCurrency($valuesByCurrency)
                ->setAudienceMode((int) $sale->getAudienceMode())
                ->setCustomerIds($customerIds)
                ->setDisplayInitialPrice((bool) $sale->getDisplayInitialPrice())
                ->setIncludeSubcategories(true)
                ->setCriteria($criteria);

            $this->eventDispatcher->dispatch($event, TheliaEvents::CATALOG_PRICE_RULE_CREATE);

            $rule = $event->getCatalogPriceRule();

            if (null !== $rule) {
                $rules[] = $rule;
            }
        }

        return new ConversionResult($rules, $warnings);
    }

    /**
     * The products of the sale grouped by the attribute values they are narrowed
     * down to: '' for the whole product, '3,7' for two attribute values.
     *
     * @return array<string, list<int>> attribute value ids (sorted, joined) => product ids
     */
    private function productGroups(Sale $sale): array
    {
        /** @var array<int, list<int>> $attributeAvsByProduct */
        $attributeAvsByProduct = [];

        foreach (SaleProductQuery::create()->filterBySaleId($sale->getId())->orderById()->find() as $saleProduct) {
            $productId = (int) $saleProduct->getProductId();
            $attributeAvsByProduct[$productId] ??= [];

            if (null !== $saleProduct->getAttributeAvId()) {
                $attributeAvsByProduct[$productId][] = (int) $saleProduct->getAttributeAvId();
            }
        }

        $groups = [];

        foreach ($attributeAvsByProduct as $productId => $attributeAvIds) {
            $attributeAvIds = array_values(array_unique($attributeAvIds));
            sort($attributeAvIds);
            $groups[implode(',', $attributeAvIds)][] = $productId;
        }

        return $groups;
    }

    /**
     * @return array{0: int, 1: float|null, 2: array<int, float>, 3: string|null} effect type, percentage, values per currency, warning
     */
    private function effectOf(Sale $sale): array
    {
        /** @var array<int, float> $offsets */
        $offsets = [];

        foreach ($sale->getPriceOffsets() as $currencyId => $value) {
            $offsets[(int) $currencyId] = (float) $value;
        }

        if (Sale::OFFSET_TYPE_AMOUNT === (int) $sale->getPriceOffsetType()) {
            return [CatalogPriceRule::EFFECT_TYPE_AMOUNT, null, $offsets, null];
        }

        $defaultCurrencyId = (int) Currency::getDefaultCurrency()->getId();
        $percentage = $offsets[$defaultCurrencyId] ?? ($offsets[array_key_first($offsets) ?? 0] ?? null);
        $warning = null;

        if (\count(array_unique(array_map(static fn (float $value): string => number_format($value, 4, '.', ''), $offsets))) > 1) {
            $warning = 'The sale typed a different percentage per currency; the rule takes the percentage of the default currency for every currency.';
        }

        return [CatalogPriceRule::EFFECT_TYPE_PERCENTAGE, $percentage, [], $warning];
    }
}

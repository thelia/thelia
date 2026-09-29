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

namespace Thelia\Domain\QuickOrder\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\QuickOrder\DTO\Candidate;
use Thelia\Domain\QuickOrder\DTO\QuickOrderLine;
use Thelia\Domain\QuickOrder\DTO\QuickOrderTable;
use Thelia\Domain\QuickOrder\Enum\LineStatus;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\AttributeAvI18n;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\AttributeI18n;
use Thelia\Model\AttributeI18nQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;

/**
 * Turns references and quantities into the lines of a control table, without
 * writing anything. The quick order and the purchase lists both go through it,
 * and so does the addition to the cart, which never trusts what the browser sent.
 *
 * A reference is first looked for on the sale elements, by reference and by EAN
 * code; only a reference no sale element carries is then looked for on the
 * products, and resolves to the product's default sale element. The core gives
 * every sale element of a product the product's reference by default, so a
 * reference shared by several sale elements is common: the line is ambiguous,
 * with the default sale element preselected when they all belong to one product.
 *
 * The number of statements does not grow with the number of lines: two lookups,
 * the titles, the attributes of the ambiguous lines, and the prices.
 *
 * Products hidden by a reserved sale are left out for the visitor of the current
 * request, whatever customer is given: ReservedSaleVisibility reads the signed-in
 * customer itself. The API and the theme always resolve for that customer; on the
 * command line nobody is signed in and every hidden product stays hidden.
 */
final readonly class ReferenceResolver
{
    public function __construct(
        private ReservedSaleVisibility $reservedSaleVisibility,
        private EffectivePriceCatalog $effectivePriceCatalog,
        private TaxEngine $taxEngine,
        private TaxCalculatorFactoryInterface $taxCalculatorFactory,
        private LangService $langService,
    ) {
    }

    public function resolve(Customer $customer, ReferenceQuantityLines $lines, Currency $currency): QuickOrderTable
    {
        if (0 === \count($lines)) {
            return new QuickOrderTable([]);
        }

        $references = array_values(array_unique(array_map(
            static fn (ReferenceQuantity $line): string => $line->reference,
            $lines->all(),
        )));

        $bySaleElementReference = $this->saleElementsCarrying($references);
        $unmatched = array_values(array_filter(
            $references,
            static fn (string $reference): bool => !isset($bySaleElementReference[self::key($reference)]),
        ));
        $byProductReference = $this->defaultSaleElementsOfProducts($unmatched);

        $decisions = [];

        foreach ($lines as $line) {
            $key = self::key($line->reference);
            $candidates = $bySaleElementReference[$key] ?? (isset($byProductReference[$key]) ? [$byProductReference[$key]] : []);
            $decisions[] = ['line' => $line, 'chosen' => self::choose($line, $candidates), 'candidates' => $candidates];
        }

        $chosen = [];
        $ambiguous = [];

        foreach ($decisions as $decision) {
            if (null !== $decision['chosen']) {
                $chosen[(int) $decision['chosen']->getId()] = $decision['chosen'];
            } elseif (\count($decision['candidates']) > 1) {
                foreach ($decision['candidates'] as $candidate) {
                    $ambiguous[(int) $candidate->getId()] = $candidate;
                }
            }
        }

        $locales = $this->locales();
        $titles = $this->titlesOf(
            array_map(static fn (ProductSaleElements $saleElements): int => (int) $saleElements->getProductId(), $chosen),
            $locales,
        );
        $attributes = $this->attributesOf(array_keys($ambiguous), $locales);
        $prices = $this->untaxedUnitPricesOf($chosen, $customer, $currency);
        $this->warmTaxRulesOf($chosen);

        $tableLines = [];

        foreach ($decisions as $decision) {
            $tableLines[] = $this->lineOf($decision['line'], $decision['chosen'], $decision['candidates'], $titles, $attributes, $prices);
        }

        return new QuickOrderTable($tableLines);
    }

    /**
     * @param list<ProductSaleElements>                                 $candidates
     * @param array<int, string>                                        $titles
     * @param array<int, list<array{attribute: string, value: string}>> $attributes
     * @param array<int, array{untaxed: float, promo: bool}|null>       $prices
     */
    private function lineOf(
        ReferenceQuantity $line,
        ?ProductSaleElements $chosen,
        array $candidates,
        array $titles,
        array $attributes,
        array $prices,
    ): QuickOrderLine {
        if (null === $chosen) {
            if (\count($candidates) < 2 || null !== $line->productSaleElementsId) {
                return new QuickOrderLine($line->reference, $line->quantity, LineStatus::Unknown);
            }

            return new QuickOrderLine(
                $line->reference,
                $line->quantity,
                LineStatus::Ambiguous,
                candidates: self::candidatesOf($candidates, $attributes),
            );
        }

        $saleElementsId = (int) $chosen->getId();
        $productId = (int) $chosen->getProductId();
        $title = $titles[$productId] ?? null;
        $price = $prices[$saleElementsId] ?? null;

        if (null === $price) {
            return new QuickOrderLine($line->reference, $line->quantity, LineStatus::Unavailable, $saleElementsId, $productId, $title);
        }

        if (ConfigQuery::checkAvailableStock()) {
            $stock = (float) $chosen->getQuantity();

            if ($stock <= 0) {
                return new QuickOrderLine($line->reference, $line->quantity, LineStatus::Unavailable, $saleElementsId, $productId, $title, availableQuantity: 0.0);
            }

            if ($line->quantity > $stock) {
                return new QuickOrderLine($line->reference, $line->quantity, LineStatus::QuantityRefused, $saleElementsId, $productId, $title, availableQuantity: $stock);
            }
        }

        $taxedUnitPrice = (float) $this->taxCalculatorFactory->createTaxCalculator()
            ->load($chosen->getProduct(), $this->taxEngine->getDeliveryCountry())
            ->getTaxedPrice($price['untaxed']);

        return new QuickOrderLine(
            $line->reference,
            $line->quantity,
            LineStatus::Resolved,
            $saleElementsId,
            $productId,
            $title,
            $price['untaxed'],
            $taxedUnitPrice,
            $price['promo'],
        );
    }

    /**
     * The sale element a line goes to, or null when the buyer still has to choose
     * or nothing carries the reference. A sale element the line names must be one
     * of the candidates: a request cannot point a reference at any sale element.
     *
     * @param list<ProductSaleElements> $candidates
     */
    private static function choose(ReferenceQuantity $line, array $candidates): ?ProductSaleElements
    {
        if (null !== $line->productSaleElementsId) {
            foreach ($candidates as $candidate) {
                if ((int) $candidate->getId() === $line->productSaleElementsId) {
                    return $candidate;
                }
            }

            return null;
        }

        return 1 === \count($candidates) ? $candidates[0] : null;
    }

    /**
     * @param list<ProductSaleElements>                                 $candidates
     * @param array<int, list<array{attribute: string, value: string}>> $attributes
     *
     * @return list<Candidate>
     */
    private static function candidatesOf(array $candidates, array $attributes): array
    {
        $productIds = array_unique(array_map(
            static fn (ProductSaleElements $saleElements): int => (int) $saleElements->getProductId(),
            $candidates,
        ));
        $preselectedId = null;

        if (1 === \count($productIds)) {
            $preselectedId = (int) $candidates[0]->getId();

            foreach ($candidates as $candidate) {
                if ($candidate->getIsDefault()) {
                    $preselectedId = (int) $candidate->getId();

                    break;
                }
            }
        }

        return array_map(
            static fn (ProductSaleElements $saleElements): Candidate => new Candidate(
                (int) $saleElements->getId(),
                (int) $saleElements->getProductId(),
                (string) $saleElements->getRef(),
                (bool) $saleElements->getIsDefault(),
                (int) $saleElements->getId() === $preselectedId,
                $attributes[(int) $saleElements->getId()] ?? [],
            ),
            $candidates,
        );
    }

    /**
     * The visible sale elements of visible products whose reference or EAN code is
     * one of the given references, grouped by the reference they answer to.
     *
     * @param list<string> $references
     *
     * @return array<string, list<ProductSaleElements>>
     */
    private function saleElementsCarrying(array $references): array
    {
        $query = ProductSaleElementsQuery::create()
            ->filterByVisible(true)
            ->joinWithProduct()
            ->where(ProductTableMap::COL_VISIBLE.' = ?', 1)
            ->condition('byReference', ProductSaleElementsTableMap::COL_REF.' IN ?', $references)
            ->condition('byEanCode', ProductSaleElementsTableMap::COL_EAN_CODE.' IN ?', $references)
            ->where(['byReference', 'byEanCode'], Criteria::LOGICAL_OR)
            ->orderByProductId()
            ->orderByPosition()
            ->orderById();

        $this->reservedSaleVisibility->applyTo($query, ProductSaleElementsTableMap::COL_PRODUCT_ID);

        $grouped = [];

        foreach ($query->find() as $saleElements) {
            $keys = array_unique(array_filter(
                [self::key((string) $saleElements->getRef()), self::key((string) $saleElements->getEanCode())],
                static fn (string $key): bool => '' !== $key,
            ));

            foreach ($keys as $key) {
                $grouped[$key][] = $saleElements;
            }
        }

        return $grouped;
    }

    /**
     * For each given reference that is a visible product's own, the sale element
     * that stands for the product: its default one, else its first.
     *
     * @param list<string> $references
     *
     * @return array<string, ProductSaleElements>
     */
    private function defaultSaleElementsOfProducts(array $references): array
    {
        if ([] === $references) {
            return [];
        }

        $query = ProductSaleElementsQuery::create()
            ->filterByVisible(true)
            ->joinWithProduct()
            ->where(ProductTableMap::COL_VISIBLE.' = ?', 1)
            ->where(ProductTableMap::COL_REF.' IN ?', $references)
            ->orderByIsDefault(Criteria::DESC)
            ->orderByPosition()
            ->orderById();

        $this->reservedSaleVisibility->applyTo($query, ProductSaleElementsTableMap::COL_PRODUCT_ID);

        $byReference = [];

        foreach ($query->find() as $saleElements) {
            $byReference[self::key((string) $saleElements->getProduct()->getRef())] ??= $saleElements;
        }

        return $byReference;
    }

    /**
     * @param array<int, int> $productIds
     * @param list<string>    $locales    the current locale first, then the default one
     *
     * @return array<int, string>
     */
    private function titlesOf(array $productIds, array $locales): array
    {
        if ([] === $productIds) {
            return [];
        }

        /** @var list<ProductI18n> $rows */
        $rows = ProductI18nQuery::create()
            ->filterById(array_values(array_unique($productIds)))
            ->filterByLocale($locales)
            ->find()
            ->getData();

        return self::localizedTitles($rows, $locales);
    }

    /**
     * @param list<int>    $saleElementsIds
     * @param list<string> $locales
     *
     * @return array<int, list<array{attribute: string, value: string}>>
     */
    private function attributesOf(array $saleElementsIds, array $locales): array
    {
        if ([] === $saleElementsIds) {
            return [];
        }

        $combinations = AttributeCombinationQuery::create()
            ->filterByProductSaleElementsId($saleElementsIds)
            ->orderByPosition()
            ->find();

        $attributeIds = [];
        $valueIds = [];

        foreach ($combinations as $combination) {
            $attributeIds[] = (int) $combination->getAttributeId();
            $valueIds[] = (int) $combination->getAttributeAvId();
        }

        if ([] === $attributeIds) {
            return [];
        }

        /** @var list<AttributeI18n> $attributeRows */
        $attributeRows = AttributeI18nQuery::create()
            ->filterById(array_values(array_unique($attributeIds)))
            ->filterByLocale($locales)
            ->find()
            ->getData();
        /** @var list<AttributeAvI18n> $valueRows */
        $valueRows = AttributeAvI18nQuery::create()
            ->filterById(array_values(array_unique($valueIds)))
            ->filterByLocale($locales)
            ->find()
            ->getData();

        $attributeTitles = self::localizedTitles($attributeRows, $locales);
        $valueTitles = self::localizedTitles($valueRows, $locales);
        $attributes = [];

        foreach ($combinations as $combination) {
            $attributes[(int) $combination->getProductSaleElementsId()][] = [
                'attribute' => $attributeTitles[(int) $combination->getAttributeId()] ?? '',
                'value' => $valueTitles[(int) $combination->getAttributeAvId()] ?? '',
            ];
        }

        return $attributes;
    }

    /**
     * The unit price each sale element sells at to this customer, before tax, in the
     * given currency: the catalog price converted from the default currency when the
     * currency has none of its own, the promo price when the sale element is on
     * promotion or a price rule covers it, the customer discount applied. Null for a
     * sale element that has no price at all.
     *
     * @param array<int, ProductSaleElements> $saleElements
     *
     * @return array<int, array{untaxed: float, promo: bool}|null>
     */
    private function untaxedUnitPricesOf(array $saleElements, Customer $customer, Currency $currency): array
    {
        if ([] === $saleElements) {
            return [];
        }

        $ids = array_keys($saleElements);
        $currencyId = (int) $currency->getId();
        $defaultCurrency = Currency::getDefaultCurrency();
        $defaultCurrencyId = (int) $defaultCurrency->getId();
        $rows = [];

        $query = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($ids)
            ->filterByCurrencyId(array_values(array_unique([$currencyId, $defaultCurrencyId])))
            ->find();

        foreach ($query as $row) {
            $rows[(int) $row->getProductSaleElementsId()][(int) $row->getCurrencyId()] = $row;
        }

        $factor = 1 - ((float) $customer->getDiscount() / 100);
        $effectivePrices = $this->effectivePriceCatalog->warm($ids, $currency, $customer);
        $prices = [];

        foreach ($saleElements as $id => $saleElementsRow) {
            $row = $rows[$id][$currencyId] ?? null;
            $rate = 1.0;

            if (null === $row || $row->getFromDefaultCurrency()) {
                $row = $rows[$id][$defaultCurrencyId] ?? null;
                $rate = (float) $currency->getRate() / (float) $defaultCurrency->getRate();
            }

            if (null === $row) {
                $prices[$id] = null;

                continue;
            }

            $promo = (bool) $saleElementsRow->getPromo();
            $promoPrice = (float) $row->getPromoPrice() * $rate * $factor;
            $effectivePrice = $effectivePrices[$id] ?? null;

            if (null !== $effectivePrice) {
                $promoPrice = $effectivePrice->untaxedPromoPrice * $factor;
                $promo = true;
            }

            $prices[$id] = [
                'untaxed' => $promo ? $promoPrice : (float) $row->getPrice() * $rate * $factor,
                'promo' => $promo,
            ];
        }

        return $prices;
    }

    /**
     * Loads the tax rules of the chosen products in one statement and hands each
     * product its rule. The tax calculator reads the rule through the product,
     * which otherwise fetches it by primary key, one statement per product.
     *
     * @param array<int, ProductSaleElements> $saleElements
     */
    private function warmTaxRulesOf(array $saleElements): void
    {
        $products = [];

        foreach ($saleElements as $saleElementsRow) {
            $product = $saleElementsRow->getProduct();
            $products[(int) $product->getId()] = $product;
        }

        $taxRuleIds = array_values(array_unique(array_map(static fn (Product $product): int => (int) $product->getTaxRuleId(), $products)));

        if ([] === $taxRuleIds) {
            return;
        }

        $taxRules = [];

        foreach (TaxRuleQuery::create()->filterById($taxRuleIds)->find() as $taxRule) {
            $taxRules[(int) $taxRule->getId()] = $taxRule;
        }

        foreach ($products as $product) {
            $taxRule = $taxRules[(int) $product->getTaxRuleId()] ?? null;

            if (null !== $taxRule) {
                $product->setTaxRule($taxRule);
            }
        }
    }

    /**
     * @param list<ProductI18n|AttributeI18n|AttributeAvI18n> $rows
     * @param list<string>                                    $locales the current locale first, then the default one
     *
     * @return array<int, string>
     */
    private static function localizedTitles(array $rows, array $locales): array
    {
        $titles = [];

        foreach ($locales as $locale) {
            foreach ($rows as $row) {
                $title = (string) $row->getTitle();

                if ($row->getLocale() === $locale && '' !== $title) {
                    $titles[(int) $row->getId()] ??= $title;
                }
            }
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        $default = (string) Lang::getDefaultLanguage()->getLocale();

        return array_values(array_unique([(string) ($this->langService->getLocale() ?? $default), $default]));
    }

    /**
     * The form a reference is compared under. The database compares without case,
     * so the rows it returns are matched back to the lines the same way.
     */
    private static function key(string $reference): string
    {
        return mb_strtolower($reference);
    }
}

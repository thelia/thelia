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
use Thelia\Domain\Catalog\Bundle\BundleDisplayedStock;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Quantity\InvalidQuantityRuleException;
use Thelia\Domain\Catalog\Quantity\NotPositiveQuantityException;
use Thelia\Domain\Catalog\Quantity\QuantityPolicy;
use Thelia\Domain\Catalog\Quantity\QuantityRefusedException;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\QuantityTranslation;
use Thelia\Domain\Catalog\Quantity\UnreadableQuantityException;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Domain\QuickOrder\DTO\Candidate;
use Thelia\Domain\QuickOrder\DTO\QuickOrderLine;
use Thelia\Domain\QuickOrder\DTO\QuickOrderTable;
use Thelia\Domain\QuickOrder\Enum\LineStatus;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\AttributeAvI18n;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\AttributeI18n;
use Thelia\Model\AttributeI18nQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductI18nQuery;
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
 * Products hidden from the visitor of the current request (a reserved sale, a
 * module rule) are left out, whatever customer is given: ProductVisibility reads
 * the signed-in customer itself. The API and the theme always resolve for that customer; on the
 * command line nobody is signed in and every product hidden from an anonymous
 * visitor stays hidden.
 */
final readonly class ReferenceResolver
{
    public function __construct(
        private ProductVisibility $productVisibility,
        private CustomerPriceContextInterface $customerPriceContext,
        private TaxEngine $taxEngine,
        private TaxCalculatorFactoryInterface $taxCalculatorFactory,
        private LangService $langService,
        private BundleDisplayedStock $bundleDisplayedStock,
        private PriceVisibilityResolver $priceVisibilityResolver,
        private QuantityPolicy $quantityPolicy = new QuantityPolicy(),
    ) {
    }

    public function resolve(Customer $customer, ReferenceQuantityLines $lines, Currency $currency, ?Cart $cart = null): QuickOrderTable
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
        $prices = $this->untaxedUnitPricesOf($chosen, $customer, $currency, $this->quantitiesOf($decisions, $cart));
        $bundleStocks = $this->bundleDisplayedStock->forModels($chosen);
        $this->warmTaxRulesOf($chosen);
        // Read once: it goes through the session cart and, for a signed-in customer
        // without a delivery address yet, their default address.
        $deliveryCountry = [] === $chosen ? null : $this->taxEngine->getDeliveryCountry();

        $tableLines = [];

        foreach ($decisions as $decision) {
            $tableLines[] = $this->lineOf($decision['line'], $decision['chosen'], $decision['candidates'], $titles, $attributes, $prices, $deliveryCountry, $bundleStocks);
        }

        return new QuickOrderTable($tableLines);
    }

    /**
     * @param list<ProductSaleElements>                                 $candidates
     * @param array<int, string>                                        $titles
     * @param array<int, list<array{attribute: string, value: string}>> $attributes
     * @param array<int, array{untaxed: float, promo: bool}|null>       $prices
     * @param array<int, int>                                           $bundleStocks what the components of a bundle supply, by product id
     */
    private function lineOf(
        ReferenceQuantity $line,
        ?ProductSaleElements $chosen,
        array $candidates,
        array $titles,
        array $attributes,
        array $prices,
        ?Country $deliveryCountry,
        array $bundleStocks = [],
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

        $refusal = $this->refusalByTheRules($chosen, $line->quantity);

        if (null !== $refusal) {
            return new QuickOrderLine($line->reference, $line->quantity, LineStatus::QuantityRefused, $saleElementsId, $productId, $title, refusalReason: $refusal);
        }

        // A virtual product is never short of stock, as on its product page. A bundle is
        // supplied by its components, never by the stock of its own sale element.
        $isBundle = isset($bundleStocks[$productId]);

        if (ConfigQuery::checkAvailableStock() && ($isBundle || 0 === (int) $chosen->getProduct()->getVirtual())) {
            // What a sale element may sell, below zero stock included when its sale mode allows it
            // (SellableStock); null when it sells without limit.
            $stock = $isBundle ? (float) $bundleStocks[$productId] : SellableStock::ceilingOfPse($chosen);

            if (null !== $stock && $stock <= 0) {
                return new QuickOrderLine($line->reference, $line->quantity, LineStatus::Unavailable, $saleElementsId, $productId, $title, availableQuantity: 0.0);
            }

            if (null !== $stock && $line->quantity > $stock) {
                return new QuickOrderLine($line->reference, $line->quantity, LineStatus::QuantityRefused, $saleElementsId, $productId, $title, availableQuantity: $stock);
            }
        }

        $taxedUnitPrice = (float) $this->taxCalculatorFactory->createTaxCalculator()
            ->load($chosen->getProduct(), $deliveryCountry ?? $this->taxEngine->getDeliveryCountry())
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
     * Why the rules of the combination refuse the quantity of a line, null when they sell
     * it. The quick order and the purchase lists count whole units: a combination sold by
     * measure is ordered from its product page, where its decimals can be typed.
     */
    private function refusalByTheRules(ProductSaleElements $saleElements, int $quantity): ?string
    {
        try {
            if (QuantityRule::fromProductSaleElements($saleElements)->isSoldByMeasure()) {
                return QuantityTranslation::trans('This product is sold by measure: order it from its product page.');
            }

            $this->quantityPolicy->assertAcceptable((float) $quantity, $saleElements);
        } catch (QuantityRefusedException|NotPositiveQuantityException|UnreadableQuantityException $refusal) {
            return $refusal->getMessage();
        } catch (InvalidQuantityRuleException) {
            return null;
        }

        return null;
    }

    /**
     * The sale element a line goes to, or null when the buyer still has to choose
     * or nothing carries the reference. A sale element the line names must be one
     * of the candidates: a request cannot point a reference at any sale element.
     * One the reference no longer offers falls back on the only candidate left, when
     * there is one: a product reference saved with the default sale element of the
     * day still finds the product once the shop moves its default.
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

        $this->productVisibility->applyTo($query, ProductSaleElementsTableMap::COL_PRODUCT_ID);

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

        $this->productVisibility->applyTo($query, ProductSaleElementsTableMap::COL_PRODUCT_ID);

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
     * The quantity the lines ask of each sale element they settled on, summed, plus what the cart
     * that receives them already holds of it: the cart merges the lines of one sale element, and
     * the tier of a price list follows the quantity it holds ({@see \Thelia\Domain\Cart\Service\CartLinePricer}:
     * the lines it offers are not counted).
     *
     * @param list<array{line: ReferenceQuantity, chosen: ProductSaleElements|null, candidates: list<ProductSaleElements>}> $decisions
     *
     * @return array<int, int>
     */
    private function quantitiesOf(array $decisions, ?Cart $cart): array
    {
        $quantities = [];

        foreach ($decisions as $decision) {
            if (null !== $decision['chosen']) {
                $saleElementsId = (int) $decision['chosen']->getId();
                $quantities[$saleElementsId] = ($quantities[$saleElementsId] ?? 0) + $decision['line']->quantity;
            }
        }

        if (null === $cart || [] === $quantities) {
            return $quantities;
        }

        $held = [];

        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductSaleElementsId(array_keys($quantities))->find() as $item) {
            if (1 !== (int) $item->getIsOffered()) {
                $saleElementsId = (int) $item->getProductSaleElementsId();
                $held[$saleElementsId] = ($held[$saleElementsId] ?? 0.0) + (float) $item->getQuantity();
            }
        }

        foreach ($held as $saleElementsId => $quantity) {
            $quantities[$saleElementsId] = (int) floor($quantities[$saleElementsId] + $quantity);
        }

        return $quantities;
    }

    /**
     * The unit price each sale element sells at to this customer, before tax, in the
     * given currency, as the customer price context resolves it for the whole batch:
     * the price of the customer group, the discounts, the price rules and the reserved
     * operations are all in it, and the promo price when the sale element is on
     * promotion. Null for a sale element that has no price at all, whose price the mode
     * hides from the customer, or that an exclusive price list does not price.
     *
     * @param array<int, ProductSaleElements> $saleElements
     * @param array<int, int>                 $quantities   what the lines ask of each sale element, by id: the tier of a price list follows it
     *
     * @return array<int, array{untaxed: float, promo: bool}|null>
     */
    private function untaxedUnitPricesOf(array $saleElements, Customer $customer, Currency $currency, array $quantities): array
    {
        if ([] === $saleElements) {
            return [];
        }

        $resolved = $this->customerPriceContext->resolve(array_keys($saleElements), $currency, $customer, $quantities);
        $hiddenProductIds = $this->priceVisibilityResolver->productsWithHiddenPrice(
            array_values(array_unique(array_map(static fn (ProductSaleElements $row): int => (int) $row->getProductId(), $saleElements))),
            $customer,
        );
        $prices = [];

        foreach ($saleElements as $id => $row) {
            $price = $resolved[$id] ?? null;

            // A price the customer is not shown (the mode of the shop or of the product), or that
            // an exclusive price list does not give the sale element, is no price: the line cannot
            // be added, as the cart would refuse it.
            if ($price instanceof ResolvedPrice && (!$price->purchasable || \in_array((int) $row->getProductId(), $hiddenProductIds, true))) {
                $price = null;
            }

            $prices[$id] = null === $price ? null : [
                'untaxed' => $price->chargedPrice(),
                'promo' => $price->isPromo,
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

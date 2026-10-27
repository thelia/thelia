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

namespace Thelia\Api\Service\DataAccess;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Service\DiscountProration;
use Thelia\Domain\Promotion\Coupon\Service\OfferedCartLineService;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorResolverTrait;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Base\BrandQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\State;
use Thelia\Model\Tools\ModelCriteriaTools;
use Thelia\Tools\DateTimeFormat;

class AttributeAccessService
{
    use TaxCalculatorResolverTrait;

    /**
     * The object each attr() family resolves to, held for the duration of one request: a page
     * asks for a dozen attributes of the same object and every one of them would replay the
     * query. Reset on every main request, the way ResourceMemoizer and RewritingUrlMemoizer
     * are: the store outlives the request under a persistent runtime (FrankenPHP, RoadRunner)
     * and inside a test process, and would then serve the object of an earlier request.
     */
    private static array $dataAccessCache = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SecurityContext $securityContext,
        private readonly TaxEngine $taxEngine,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CouponManager $couponManager, private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OfferedCartLineService $offeredCartLineService,
        private readonly VatExemptionResolver $vatExemptionResolver,
    ) {
    }

    public function attributeAdmin(string $attributeName): mixed
    {
        return $this->dataAccess('Admin User', $attributeName, $this->securityContext->getAdminUser());
    }

    public function attributeCustomer(string $attributeName): mixed
    {
        return $this->dataAccess('Customer User', $attributeName, $this->securityContext->getCustomerUser());
    }

    public function attributeProduct(string $attributeName): mixed
    {
        if (null === $productId = $this->getRequestParam('product_id')) {
            return '';
        }
        $search = ProductQuery::create();

        return $this->dataAccessWithI18n(
            objectLabel: 'Product',
            attributeName: $attributeName,
            search: $search->filterByPrimaryKey($productId),
        );
    }

    public function attributeCategory(string $attributeName): mixed
    {
        $categoryId = $this->getRequestParam('category_id');

        if ($categoryId === null) {
            $productId = $this->getRequestParam('product_id');
            if ($productId !== null) {
                $product = ProductQuery::create()->findPk($productId);
                if ($product !== null) {
                    // getDefaultCategoryId() returns 0, not null, when the product has no default category.
                    $defaultCategoryId = $product->getDefaultCategoryId();
                    $categoryId = $defaultCategoryId === 0 ? null : $defaultCategoryId;
                }
            }
        }

        if ($categoryId === null) {
            return '';
        }

        return $this->dataAccessWithI18n(
            'Category',
            $attributeName,
            CategoryQuery::create()->filterByPrimaryKey($categoryId)
        );
    }

    public function attributeContent(string $attributeName): mixed
    {
        $contentId = $this->getRequestParam('content_id');

        if ($contentId === null) {
            return '';
        }

        return $this->dataAccessWithI18n(
            'Content',
            $attributeName,
            ContentQuery::create()->filterByPrimaryKey($contentId)
        );
    }

    public function attributeFolder(string $attributeName): mixed
    {
        $folderId = $this->getRequestParam('folder_id');

        if ($folderId === null) {
            $contentId = $this->getRequestParam('content_id');

            if ($contentId !== null) {
                $content = ContentQuery::create()->findPk($contentId);
                if ($content !== null) {
                    // getDefaultFolderId() returns 0, not null, when the content has no default folder.
                    $defaultFolderId = $content->getDefaultFolderId();
                    $folderId = $defaultFolderId === 0 ? null : $defaultFolderId;
                }
            }
        }

        if ($folderId === null) {
            return '';
        }

        return $this->dataAccessWithI18n(
            'Folder',
            $attributeName,
            FolderQuery::create()->filterByPrimaryKey($folderId)
        );
    }

    public function attributeBrand(string $attributeName): mixed
    {
        $brandId = $this->getRequestParam('brand_id');

        if ($brandId === null) {
            $productId = $this->getRequestParam('product_id');

            if ($productId !== null) {
                $product = ProductQuery::create()->findPk($productId);
                if ($product !== null) {
                    $brandId = $product->getBrandId();
                }
            }
        }

        if ($brandId === null) {
            return '';
        }

        return $this->dataAccessWithI18n(
            'Brand',
            $attributeName,
            BrandQuery::create()->filterByPrimaryKey($brandId)
        );
    }

    public function attributeCurrency(string $attributeName): mixed
    {
        $currency = $this->getSession()->getCurrency();

        if ($currency) {
            return $this->dataAccessWithI18n(
                'Currency',
                $attributeName,
                CurrencyQuery::create()->filterByPrimaryKey($currency->getId()),
                ['NAME']
            );
        }

        return '';
    }

    /**
     * Reads an attribute of the shop's default country: its title, its iso
     * codes, any of its columns. The argument names that attribute, the way
     * attributeCurrency() and attributeBrand() take one; it does not name the
     * country, since the default one is the only country this reads.
     */
    public function attributeCountry(string $attributeName): mixed
    {
        return $this->dataAccessWithI18n(
            'defaultCountry',
            $attributeName,
            // Filtering by the already memoized default country's id, rather than
            // by_default=1 again, skips a redundant lookup of which country that is.
            CountryQuery::create()->filterById(Country::getDefaultCountry()->getId())->limit(1)
        );
    }

    /**
     * @throws PropelException
     */
    public function attributeCart(string $attributeName): mixed
    {
        if (!\array_key_exists('currentCountry', self::$dataAccessCache)) {
            self::$dataAccessCache['currentCountry'] = $this->taxEngine->getDeliveryCountry();
        }
        /* @var Country $taxCountry */
        $taxCountry = self::$dataAccessCache['currentCountry'];

        if (!\array_key_exists('currentState', self::$dataAccessCache)) {
            self::$dataAccessCache['currentState'] = $this->taxEngine->getDeliveryState();
        }
        /* @var State $taxState */
        $taxState = self::$dataAccessCache['currentState'];

        /** @var Cart $cart */
        $cart = $this->getSession()->getSessionCart($this->dispatcher);

        $result = '';
        switch ($attributeName) {
            case 'count_product':
            case 'product_count':
                $result = $cart->getCartItems()->count();
                break;
            case 'count_item':
            case 'item_count':
                $countAllItem = 0;
                foreach ($cart->getCartItems() as $cartItem) {
                    $countAllItem += $cartItem->getQuantity();
                }
                $result = $countAllItem;
                break;
            case 'taxed_postage':
                $result = $cart->getTaxedPostage();
                break;
            case 'untaxed_postage':
                $result = $cart->getUntaxedPostage();
                break;
            case 'postage':
                $result = $cart->getTaxedPostage();
                break;
            case 'postage_tax':
                $result = $cart->getPostageTaxAmount();
                break;
            case 'total_price':
            case 'total_price_with_discount':
                $result = $cart->getTotalAmount(true, $taxCountry, $taxState, true, true);
                break;
            case 'total_price_without_discount':
                $result = $cart->getTotalAmount(false, $taxCountry, $taxState, true, true);
                break;
            case 'total_price_without_postage':
                $result = $cart->getTotalAmount(true, $taxCountry, $taxState);
                break;
            case 'raw_total_price':
                $result = $cart->getTotalAmount(false, $taxCountry, $taxState);
                break;
            case 'total_taxed_price':
            case 'total_taxed_price_with_discount':
                $result = $cart->getTaxedAmount($taxCountry, true, $taxState, true, true);
                break;
            case 'total_taxed_price_without_discount':
                $result = $cart->getTaxedAmount($taxCountry, false, $taxState, true, true);
                break;
            case 'total_taxed_price_without_postage':
                $result = $cart->getTaxedAmount($taxCountry, true, $taxState);
                break;
            case 'raw_taxed_total_price':
                $result = $cart->getTaxedAmount($taxCountry, false, $taxState);
                break;
            case 'is_virtual':
            case 'contains_virtual_product':
                $result = $cart->isVirtual();
                break;
            case 'total_vat':
            case 'total_tax_amount':
                $result = $cart->getTotalVAT($taxCountry, $taxState, true, true, true);
                break;
            case 'total_tax_amount_without_discount':
                $result = $cart->getTotalVAT($taxCountry, $taxState, false, true, true);
                break;
            case 'raw_total_tax_amount':
                $result = $cart->getTotalVAT($taxCountry, $taxState, false, false);
                break;
            case 'taxed_discount':
                $result = $cart->getCalculatedDiscount(true, $taxCountry, $taxState);
                break;
            case 'discount':
                $result = $cart->getCalculatedDiscount(false, $taxCountry, $taxState);
                break;
            case 'discount_tax_amount':
                $result = $cart->getDiscountVAT($taxCountry, $taxState);
                break;
            case 'weight':
                $result = $cart->getWeight();
                break;
            case 'delivery_module_id':
                $result = $cart->getDeliveryModuleId();
                break;
            case 'payment_module_id':
                $result = $cart->getPaymentModuleId();
                break;
            case 'discounts':
                $result = $this->cartDiscounts($cart, $taxCountry, $taxState);
                break;
            case 'cart_items':
                $result = $this->cartItems($cart, $taxCountry, $taxState);
                break;
            case 'unavailable_promotions':
                $result = $this->offeredCartLineService->getUnavailablePromotions();
                break;
            case 'gift_wrapping_id':
                $result = $cart->getGiftWrappingId();
                break;
            case 'gift_wrapping_title':
                // The wording of the service as the shop writes it today. The order freezes
                // its own copy, so a theme showing a placed order reads that one, not this.
                $result = $cart->getGiftWrapping()?->setLocale((string) $this->getSession()->getLang()?->getLocale())->getTitle();
                break;
            case 'gift_wrapping':
            case 'untaxed_gift_wrapping':
                $result = $cart->getUntaxedGiftWrapping();
                break;
            case 'taxed_gift_wrapping':
                $result = $cart->getTaxedGiftWrapping($taxCountry, $taxState);
                break;
            case 'gift_message':
                $result = $cart->getGiftMessage();
                break;
            case 'is_vat_exempted':
                $result = $cart->isVatExempted();
                break;
            case 'vat_exemption_state':
                $result = $this->vatExemptionResolver->stateForCart($cart)->value;
                break;
            case 'invoice_vat_number':
                $result = $cart->getCartAddressRelatedByAddressInvoiceId()?->getVatNumber() ?? '';
                break;
        }

        return $result;
    }

    /**
     * What each kept promotion takes off the cart, so the front can name the
     * promotions instead of showing one anonymous total.
     *
     * The stored `cart.discount` is the authoritative figure — it was capped and
     * rounded when the evaluation wrote it — so what the promotions price now is
     * prorated onto it: the listed taxed_amount add up EXACTLY to
     * attr('cart', 'taxed_discount'), the rounding remainder on the last line,
     * and the amounts to attr('cart', 'discount') the same way. A stored
     * discount of zero has nothing to distribute: the list is empty.
     *
     * The amounts come from the coupons the evaluation retained, which
     * CouponManager memoises for the request: reading this attribute never
     * rebuilds them. A promotion discounting nothing — free shipping, an offer
     * whose gift is out of stock — is left out: it has no line to show.
     *
     * @return list<array{label: string, amount: float, taxed_amount: float}>
     */
    private function cartDiscounts(Cart $cart, Country $taxCountry, ?State $taxState): array
    {
        $storedTaxedDiscount = round($cart->getCalculatedDiscount(true, $taxCountry, $taxState), 2);

        if ($storedTaxedDiscount <= 0.0) {
            return [];
        }

        $couponsKept = $this->couponManager->getCouponsKept();

        if ([] === $couponsKept) {
            return [];
        }

        $titles = $this->couponTitles($couponsKept);

        $labels = [];
        $rawAmounts = [];

        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            $rawAmount = round($coupon->exec(), 2);

            if ($rawAmount <= 0.0) {
                continue;
            }

            $modelId = $coupon instanceof CouponAbstract ? $coupon->getCouponModelId() : null;

            $labels[] = $titles[$modelId] ?? ('' !== $coupon->getTitle() ? $coupon->getTitle() : $coupon->getCode());
            $rawAmounts[] = $rawAmount;
        }

        $taxedAmounts = DiscountProration::prorate($rawAmounts, $storedTaxedDiscount);

        if ([] === $taxedAmounts) {
            return [];
        }

        // The pair the cart exposes gives the factor to untax a share of the
        // discount; the last line takes the untaxed rounding remainder too, and
        // as with the taxed shares no line takes more than what is left.
        $untaxedDiscount = round($cart->getCalculatedDiscount(false, $taxCountry, $taxState), 2);
        $untaxFactor = $untaxedDiscount / $storedTaxedDiscount;

        $discounts = [];
        $allocatedUntaxed = 0.0;
        $lastIndex = array_key_last($taxedAmounts);

        foreach ($taxedAmounts as $index => $taxedAmount) {
            $amount = $index === $lastIndex
                ? round($untaxedDiscount - $allocatedUntaxed, 2)
                : min(round($taxedAmount * $untaxFactor, 2), round($untaxedDiscount - $allocatedUntaxed, 2));

            $allocatedUntaxed = round($allocatedUntaxed + $amount, 2);

            $discounts[] = [
                'label' => $labels[$index],
                'amount' => $amount,
                'taxed_amount' => $taxedAmount,
            ];
        }

        return $discounts;
    }

    /**
     * The cart lines with what no column carries, so that a theme prints a line without
     * working a figure out of two others, in the display the buyer is shown:
     *
     * - `offered_taxed_discount` and `offered_discount`, the taxed and the untaxed discount THIS
     *   line carries when a promotion offered it, 0.0 for any other line: what lets a cart page
     *   strike the gift's own price instead of showing one anonymous total. Raw, as the
     *   promotion grants it;
     * - `offered_effective_taxed` and `offered_effective_untaxed`, the same discount bounded by
     *   what the line is worth: a promotion configured to take off more than the line takes the
     *   line, and the buyer is never shown a negative price;
     * - `line_total_taxed` and `line_total_untaxed`, what the line is worth for its whole
     *   quantity, at the price it is charged (the promotional one when it is on promotion);
     * - `line_total_after_discount_taxed` and `line_total_after_discount_untaxed`, the same
     *   once `offered_effective_*` is taken off: what the buyer pays for the line.
     *
     * @return list<array{id: int, product_id: int, is_offered: int, offered_taxed_discount: float, offered_discount: float, offered_effective_taxed: float, offered_effective_untaxed: float, line_total_taxed: float, line_total_untaxed: float, line_total_after_discount_taxed: float, line_total_after_discount_untaxed: float}>
     */
    private function cartItems(Cart $cart, Country $taxCountry, ?State $taxState): array
    {
        $offeredDiscounts = $this->offeredDiscounts($cart, $taxCountry, $taxState);

        $cartItems = $cart->getCartItems();
        $this->preloadProductsOf($cartItems);

        // One calculator for the whole cart, as Cart::getTaxedAmount() does: the line neither
        // reads its cart again nor resolves a calculator of its own.
        $cartTaxCalculator = $this->createCartTaxCalculator($cart);

        $items = [];

        foreach ($cartItems as $cartItem) {
            $offeredTaxedDiscount = $offeredDiscounts[(int) $cartItem->getId()]['taxed'] ?? 0.0;
            $offeredUntaxedDiscount = $offeredDiscounts[(int) $cartItem->getId()]['untaxed'] ?? 0.0;

            $lineTotalTaxed = round($cartItem->getTotalRealTaxedPrice($taxCountry, $taxState, $cartTaxCalculator), 2);
            $lineTotalUntaxed = round($cartItem->getTotalRealPrice(), 2);

            // A discount worth more than the line is a discount worth the line.
            $effectiveTaxedDiscount = min(max($offeredTaxedDiscount, 0.0), $lineTotalTaxed);
            $effectiveUntaxedDiscount = min(max($offeredUntaxedDiscount, 0.0), $lineTotalUntaxed);

            $items[] = [
                'id' => (int) $cartItem->getId(),
                'product_id' => (int) $cartItem->getProductId(),
                'is_offered' => (int) $cartItem->getIsOffered(),
                'offered_taxed_discount' => $offeredTaxedDiscount,
                'offered_discount' => $offeredUntaxedDiscount,
                'offered_effective_taxed' => $effectiveTaxedDiscount,
                'offered_effective_untaxed' => $effectiveUntaxedDiscount,
                'line_total_taxed' => $lineTotalTaxed,
                'line_total_untaxed' => $lineTotalUntaxed,
                'line_total_after_discount_taxed' => round($lineTotalTaxed - $effectiveTaxedDiscount, 2),
                'line_total_after_discount_untaxed' => round($lineTotalUntaxed - $effectiveUntaxedDiscount, 2),
            ];
        }

        return $items;
    }

    /**
     * Hands each line its product, tax rule and title included, read for all the lines at once:
     * left to itself a line reads its product, then its title, one line after the other.
     *
     * @param iterable<CartItem> $cartItems
     */
    private function preloadProductsOf(iterable $cartItems): void
    {
        $productIds = [];

        foreach ($cartItems as $cartItem) {
            $productIds[(int) $cartItem->getProductId()] = (int) $cartItem->getProductId();
        }

        if ([] === $productIds) {
            return;
        }

        // A product already held by the instance pool is the one every other read of the request
        // shares: it is taken as it is, its tax rule and its title come from the pool too, and
        // reading it again would cost a query the pool exists to save (and seed a second copy of
        // its translation). Only the others are read, all in one query.
        $products = [];
        $unpooledIds = [];

        foreach ($productIds as $productId) {
            $pooled = ProductTableMap::getInstanceFromPool((string) $productId);

            if ($pooled instanceof Product) {
                $products[$productId] = $pooled;

                continue;
            }

            $unpooledIds[] = $productId;
        }

        if ([] !== $unpooledIds) {
            // LEFT, as a product with no tax rule must still be handed over, not dropped.
            $fetched = ProductQuery::create()
                ->joinWithTaxRule(Criteria::LEFT_JOIN)
                ->filterById($unpooledIds, Criteria::IN)
                ->find()
                ->getArrayCopy('Id');

            $this->seedTitles($fetched);
            $products += $fetched;
        }

        foreach ($cartItems as $cartItem) {
            $product = $products[(int) $cartItem->getProductId()] ?? null;

            if ($product instanceof Product) {
                $cartItem->setProduct($product);
            }
        }
    }

    /**
     * Seeds on each product the title in the locale CartItem::getProduct() reads it in, for all
     * of them at once: a product asked for its title otherwise reads it on its own. A product
     * with no row in that locale is seeded with an empty, unsaved one, as Propel's own
     * getTranslation() would attach it, or it would still go and look for it.
     *
     * @param array<int, Product> $products freshly read, not yet holding any translation
     */
    private function seedTitles(array $products): void
    {
        $locale = Lang::getDefaultLanguage()->getLocale();
        $untitledProductIds = array_keys($products);

        $titles = ProductI18nQuery::create()
            ->filterById($untitledProductIds, Criteria::IN)
            ->filterByLocale($locale)
            ->find();

        foreach ($titles as $title) {
            $products[$title->getId()]->addProductI18n($title);
            unset($untitledProductIds[array_search($title->getId(), $untitledProductIds, true)]);
        }

        foreach ($untitledProductIds as $untitledProductId) {
            $products[$untitledProductId]->addProductI18n((new ProductI18n())->setLocale($locale));
        }
    }

    /**
     * The discount each OFFERED cart line carries, taxed and untaxed, keyed by
     * cart item id, prorated by the same factor as the 'discounts' attribute:
     * what the owning promotion grants on that very line, scaled onto the
     * stored cart discount. A cart item missing from the map carries no
     * offered discount (0.0) — a regular line, or an offered line whose
     * promotion prices nothing.
     *
     * @return array<int, array{taxed: float, untaxed: float}>
     */
    private function offeredDiscounts(Cart $cart, Country $taxCountry, ?State $taxState): array
    {
        $storedTaxedDiscount = round($cart->getCalculatedDiscount(true, $taxCountry, $taxState), 2);

        if ($storedTaxedDiscount <= 0.0) {
            return [];
        }

        $couponsKept = $this->couponManager->getCouponsKept();

        if ([] === $couponsKept) {
            return [];
        }

        $rawAmounts = [];

        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            $rawAmounts[] = round($coupon->exec(), 2);
        }

        $factor = DiscountProration::factor($rawAmounts, $storedTaxedDiscount);

        if (0.0 === $factor) {
            return [];
        }

        $map = [];

        foreach ($cart->getCartItems() as $cartItem) {
            if (1 !== (int) $cartItem->getIsOffered()) {
                continue;
            }

            $rawTaxedDiscount = 0.0;
            $rawUntaxedDiscount = 0.0;

            foreach ($couponsKept as $coupon) {
                if ($coupon instanceof BuyXGetY) {
                    $rawTaxedDiscount += $coupon->taxedDiscountOnOfferedLine($cartItem);
                    $rawUntaxedDiscount += $coupon->untaxedDiscountOnOfferedLine($cartItem);
                }
            }

            if ($rawTaxedDiscount > 0.0) {
                $map[(int) $cartItem->getId()] = [
                    'taxed' => round($rawTaxedDiscount * $factor, 2),
                    'untaxed' => round($rawUntaxedDiscount * $factor, 2),
                ];
            }
        }

        return $map;
    }

    /**
     * The public title of each coupon, in the language the visitor is browsing.
     * A coupon built for the checkout carries the title of the default locale,
     * which is not the one the cart page is written in.
     *
     * @param CouponInterface[] $coupons
     *
     * @return array<int, string>
     */
    private function couponTitles(array $coupons): array
    {
        $modelIds = array_values(array_filter(array_map(
            static fn (CouponInterface $coupon): ?int => $coupon instanceof CouponAbstract ? $coupon->getCouponModelId() : null,
            $coupons,
        )));

        if ([] === $modelIds) {
            return [];
        }

        $locale = $this->getSession()->getLang()?->getLocale();

        // A page reads several cart attributes going through here — 'discounts',
        // the per-line offered discounts — and each would replay the query: the
        // titles are kept for the request, like every other attr() resolution.
        $cacheKey = \sprintf('couponTitles_%s_%s', $locale ?? '-', implode('-', $modelIds));

        if (\array_key_exists($cacheKey, self::$dataAccessCache)) {
            return self::$dataAccessCache[$cacheKey];
        }

        $titles = [];

        foreach (CouponQuery::create()->filterById($modelIds, Criteria::IN)->find() as $model) {
            if (null !== $locale) {
                $model->setLocale($locale);
            }

            $title = (string) $model->getTitle();

            if ('' !== $title) {
                $titles[(int) $model->getId()] = $title;
            }
        }

        return self::$dataAccessCache[$cacheKey] = $titles;
    }

    public function attributeCoupon(string $attributeName): mixed
    {
        /** @var Cart $cart */
        $cart = $this->getSession()->getSessionCart($this->eventDispatcher);

        switch ($attributeName) {
            case 'has_coupons':
                return [] !== $this->keptCouponCodes();
            case 'coupon_count':
                return \count($this->keptCouponCodes());
            case 'coupon_list':
                return $this->keptCouponCodes();
            case 'is_delivery_free':
                return $this->couponManager->isCouponRemovingPostage($cart);
        }
        throw new \InvalidArgumentException(\sprintf("%s has no '%s' attribute", 'Order', $attributeName));
    }

    /**
     * The codes of the coupons the evaluation retained. These attributes drive
     * the "your coupon" block of the cart page — a code typed in, shown, removable.
     * An automatic promotion carries no code: it has nothing to show or remove
     * there, and belongs to the 'discounts' attribute instead.
     *
     * @return list<string>
     */
    private function keptCouponCodes(): array
    {
        $codes = [];

        /** @var CouponInterface $coupon */
        foreach ($this->couponManager->getCouponsKept() as $coupon) {
            $code = $coupon->getCode();

            if ('' !== $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public function orderDataAccess(string $attributeName): mixed
    {
        $order = $this->getSession()->getOrder();
        switch ($attributeName) {
            case 'untaxed_postage':
                return $order->getUntaxedPostage();
            case 'postage':
                return $order->getPostage();
            case 'postage_tax':
                return $order->getPostageTax();
            case 'discount':
                return $order->getDiscount();
            case 'delivery_address':
                return $order->getChoosenDeliveryAddress();
            case 'invoice_address':
                return $order->getChoosenInvoiceAddress();
            case 'delivery_module':
                return $order->getDeliveryModuleId();
            case 'payment_module':
                return $order->getPaymentModuleId();
            case 'has_virtual_product':
                return $order->hasVirtualProduct();
            case 'vat_exempted':
                return $order->getVatExempted();
            case 'invoice_vat_number':
                return $order->getOrderAddressRelatedByInvoiceOrderAddressId()?->getVatNumber() ?? '';
        }

        throw new \InvalidArgumentException(\sprintf("%s has no '%s' attribute", 'Order', $attributeName));
    }

    public function attributeLang(string $attributeName): mixed
    {
        return $this->dataAccess('Lang', $attributeName, $this->getSession()->getLang());
    }

    public function attributeConfig(string $attributeName)
    {
        return ConfigQuery::read($attributeName);
    }

    protected function dataAccessWithI18n(
        string $objectLabel,
        string $attributeName,
        ModelCriteria $search,
        array $columns = ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'],
        ?string $foreignTable = null,
        string $foreignKey = 'ID',
    ): mixed {
        $cacheKey = 'data_'.$objectLabel;

        $data = self::$dataAccessCache[$cacheKey] ?? null;

        if ($data === null) {
            $lang = $this->getSession()->getLang()?->getId();

            ModelCriteriaTools::getI18n(
                false,
                $lang,
                $search,
                $this->getSession()->getLang()?->getLocale(),
                $columns,
                $foreignTable,
                $foreignKey,
                true
            );

            $data = $search->findOne();

            // The texts come from the joined i18n columns, the getters that read a translation themselves
            // (getUrl(), getMetaTitle()...) from the locale of the model: the language of the page too, not the
            // default locale of the model class, which made attr('category', 'url') an unrewritten en_US address.
            $locale = $this->getSession()->getLang()?->getLocale();
            if (null !== $data && null !== $locale && method_exists($data, 'setLocale')) {
                $data->setLocale($locale);
            }

            self::$dataAccessCache[$cacheKey] = $data;
        }

        if ($data === null) {
            throw new NotFoundHttpException();
        }
        $noGetterData = array_map(static fn ($column) => $data->getVirtualColumn('i18n_'.$column), $columns);
        $noGetterData = array_combine($columns, $noGetterData);

        return $this->dataAccess($objectLabel, $attributeName, $data, $noGetterData);
    }

    protected function dataAccess(
        string $objectLabel,
        string $attributeName,
        ?object $data,
        array $noGetterData = [],
    ): string|int|null {
        if (empty($attributeName) || $data === null) {
            return '';
        }
        $keyAttribute = strtoupper($attributeName);

        if (\array_key_exists($keyAttribute, $noGetterData)) {
            return $noGetterData[$keyAttribute];
        }

        $getter = \sprintf('get%s', $this->underscoreToCamelcase($attributeName));

        if (method_exists($data, $getter)) {
            $return = $data->$getter();

            if ($return instanceof \DateTime) {
                $format = DateTimeFormat::getInstance($this->getRequest())->getFormat();

                return $return->format((string) $format);
            }

            return $return;
        }
        throw new \InvalidArgumentException(\sprintf("%s has no '%s' attribute", $objectLabel, $attributeName));
    }

    private function underscoreToCamelcase(string $str): mixed
    {
        $words = explode('_', strtolower($str));

        $return = '';

        foreach ($words as $word) {
            $return .= ucfirst(trim($word));
        }

        return $return;
    }

    public function getRequest(): Request
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            throw new \RuntimeException('No request available');
        }

        return $request;
    }

    public function getSession(): Session
    {
        $request = $this->getRequest();
        /** @var Session $session */
        $session = $request->getSession();

        return $session;
    }

    public function getRequestParam(string $key): mixed
    {
        $request = $this->getRequest();

        return $request->attributes->get($key)
            ?? $request->request->get($key)
            ?? $request->query->get($key);
    }

    public static function clearCache(): void
    {
        self::$dataAccessCache = [];
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            self::clearCache();
        }
    }
}

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

namespace Thelia\Domain\Promotion\Coupon\Type;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineProviderInterface;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineRequest;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Model\ProductCategoryQuery;

/**
 * "Buy X, get Y": when the cart holds a given quantity of triggering products,
 * a quantity of a product is offered, entirely or with a discount.
 *
 * exec() is PURE: it never writes the cart. It runs on every discount evaluation
 * and again after the order is placed (Action\Coupon::afterOrder()), so a mutating
 * exec() would loop through CART_ADDITEM -> updateOrderDiscount() -> exec(), the
 * trap FreeProduct works around with session markers. The offered line for
 * target_mode=product is written by OfferedCartLineService from the requests this
 * coupon exposes through OfferedLineProviderInterface.
 */
class BuyXGetY extends CouponAbstract implements OfferedLineProviderInterface
{
    public const TRIGGER_SCOPE_FIELD = 'trigger_scope';
    public const TRIGGER_IDS_FIELD = 'trigger_ids';
    public const TRIGGER_QUANTITY_FIELD = 'trigger_quantity';
    public const TARGET_MODE_FIELD = 'target_mode';
    public const TARGET_PRODUCT_ID_FIELD = 'target_product_id';
    public const OFFERED_QUANTITY_FIELD = 'offered_quantity';
    public const DISCOUNT_TYPE_FIELD = 'discount_type';
    public const DISCOUNT_VALUE_FIELD = 'discount_value';

    public const TRIGGER_SCOPE_PRODUCT = 'product';
    public const TRIGGER_SCOPE_CATEGORY = 'category';
    public const TRIGGER_SCOPE_SELECTION = 'selection';

    public const TARGET_MODE_SAME = 'same';
    public const TARGET_MODE_PRODUCT = 'product';
    public const TARGET_MODE_CHEAPEST = 'cheapest';

    public const DISCOUNT_TYPE_FREE = 'free';
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    public const DISCOUNT_TYPE_AMOUNT = 'amount';

    protected string $serviceId = 'thelia.coupon.type.buy_x_get_y';

    protected string $triggerScope = self::TRIGGER_SCOPE_PRODUCT;

    /** @var int[] product ids (scope product/selection) or category ids (scope category) */
    protected array $triggerIds = [];

    protected int $triggerQuantity = 1;
    protected string $targetMode = self::TARGET_MODE_SAME;
    protected ?int $targetProductId = null;
    protected int $offeredQuantity = 1;
    protected string $discountType = self::DISCOUNT_TYPE_FREE;
    protected float $discountValue = 0.0;

    /**
     * The triggering product ids per cart id, for the lifetime of this instance.
     * An instance is built per evaluation cycle, and within one cycle exec() and
     * getOfferedLineRequests() replay the same product_category query otherwise.
     * Nothing invalidates it: the next cycle starts on a fresh clone.
     *
     * @var array<int, int[]>
     */
    private array $categoryTriggerProductIds = [];

    public function set(
        FacadeInterface $facade,
        $code,
        $title,
        $shortDescription,
        $description,
        array $effects,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        \DateTime $expirationDate,
        $freeShippingForCountries,
        $freeShippingForModules,
        $perCustomerUsageCount,
    ): static {
        parent::set(
            $facade,
            $code,
            $title,
            $shortDescription,
            $description,
            $effects,
            $isCumulative,
            $isRemovingPostage,
            $isAvailableOnSpecialOffers,
            $isEnabled,
            $maxUsage,
            $expirationDate,
            $freeShippingForCountries,
            $freeShippingForModules,
            $perCustomerUsageCount,
        );

        $this->setFieldsValue($effects);

        return $this;
    }

    /**
     * Lenient on purpose: this runs on every cart evaluation against stored effects,
     * so a stale or unknown value falls back to something that offers nothing,
     * while getEffects() rejects it at save time.
     */
    public function setFieldsValue(array $effects): void
    {
        $scope = (string) ($effects[self::TRIGGER_SCOPE_FIELD] ?? self::TRIGGER_SCOPE_PRODUCT);
        $this->triggerScope = \in_array($scope, $this->triggerScopes(), true) ? $scope : self::TRIGGER_SCOPE_PRODUCT;

        $this->triggerIds = $this->normalizeIdList($effects[self::TRIGGER_IDS_FIELD] ?? []);
        $this->triggerQuantity = max(1, (int) ($effects[self::TRIGGER_QUANTITY_FIELD] ?? 1));

        $mode = (string) ($effects[self::TARGET_MODE_FIELD] ?? self::TARGET_MODE_SAME);
        $this->targetMode = \in_array($mode, $this->targetModes(), true) ? $mode : self::TARGET_MODE_SAME;

        $targetProductId = (int) ($effects[self::TARGET_PRODUCT_ID_FIELD] ?? 0);
        $this->targetProductId = $targetProductId > 0 ? $targetProductId : null;

        $this->offeredQuantity = max(1, (int) ($effects[self::OFFERED_QUANTITY_FIELD] ?? 1));

        if (self::TARGET_MODE_PRODUCT !== $this->targetMode) {
            // The offered units are taken among the triggering units themselves: a lot
            // offering as much as it takes to form would trigger itself for free. Such
            // stored effects (getEffects() rejects them at save time) offer nothing.
            $this->offeredQuantity = min($this->offeredQuantity, max(0, $this->triggerQuantity - 1));
        }

        $type = (string) ($effects[self::DISCOUNT_TYPE_FIELD] ?? self::DISCOUNT_TYPE_FREE);
        $this->discountType = \in_array($type, $this->discountTypes(), true) ? $type : self::DISCOUNT_TYPE_FREE;

        $this->discountValue = (float) ($effects[self::DISCOUNT_VALUE_FIELD] ?? 0.0);

        if (self::DISCOUNT_TYPE_PERCENTAGE === $this->discountType) {
            $this->discountValue = min(100.0, max(0.0, $this->discountValue));
        }
    }

    public function exec(): float
    {
        $cart = $this->facade->getCart();

        if (null === $cart || [] === $this->triggerIds) {
            return 0.0;
        }

        $unitPrices = $this->eligibleUnitPrices($cart);
        $lots = intdiv(\count($unitPrices), $this->triggerQuantity);

        if (0 === $lots) {
            return 0.0;
        }

        if (self::TARGET_MODE_PRODUCT === $this->targetMode) {
            return $this->discountOnOfferedLine($cart, $lots);
        }

        // same / cheapest: the offered units are taken among the triggering units
        // themselves, cheapest first, so the discount never exceeds what those
        // units actually cost.
        $offeredUnits = min($lots * $this->offeredQuantity, \count($unitPrices));
        $base = (float) array_sum(\array_slice($unitPrices, 0, $offeredUnits));

        return $this->applyDiscountType($base, $offeredUnits);
    }

    public function getOfferedLineRequests(FacadeInterface $facade): array
    {
        if (self::TARGET_MODE_PRODUCT !== $this->targetMode
            || null === $this->targetProductId
            || null === $this->getCouponModelId()
            || [] === $this->triggerIds) {
            return [];
        }

        $cart = $facade->getCart();

        if (null === $cart) {
            return [];
        }

        $lots = intdiv(\count($this->eligibleUnitPrices($cart)), $this->triggerQuantity);

        if (0 === $lots) {
            return [];
        }

        return [
            new OfferedLineRequest(
                $this->targetProductId,
                $lots * $this->offeredQuantity,
                $this->getCouponModelId(),
            ),
        ];
    }

    /**
     * The taxed unit price of every cart unit eligible to form a lot, cheapest
     * first. Offered lines never trigger a lot, whoever offered them.
     *
     * @return float[]
     */
    private function eligibleUnitPrices(Cart $cart): array
    {
        $country = $this->facade->getDeliveryCountry();
        $categoryProductIds = self::TRIGGER_SCOPE_CATEGORY === $this->triggerScope
            ? $this->triggerProductIdsInCart($cart)
            : null;

        $unitPrices = [];

        /** @var CartItem $cartItem */
        foreach ($cart->getCartItems() as $cartItem) {
            if (1 === (int) $cartItem->getIsOffered()) {
                continue;
            }

            if ($cartItem->getPromo() && !$this->isAvailableOnSpecialOffers()) {
                continue;
            }

            $productId = (int) $cartItem->getProductId();

            $isTrigger = null !== $categoryProductIds
                ? \in_array($productId, $categoryProductIds, true)
                : \in_array($productId, $this->triggerIds, true);

            if (!$isTrigger) {
                continue;
            }

            $unitPrice = $cartItem->getRealTaxedPrice($country);

            for ($unit = 0; $unit < (int) $cartItem->getQuantity(); ++$unit) {
                $unitPrices[] = $unitPrice;
            }
        }

        sort($unitPrices);

        return $unitPrices;
    }

    /**
     * @return int[] the cart product ids belonging to one of the triggering categories
     */
    private function triggerProductIdsInCart(Cart $cart): array
    {
        $cartId = (int) $cart->getId();

        if (isset($this->categoryTriggerProductIds[$cartId])) {
            return $this->categoryTriggerProductIds[$cartId];
        }

        return $this->categoryTriggerProductIds[$cartId] = $this->queryTriggerProductIdsInCart($cart);
    }

    /**
     * @return int[]
     */
    private function queryTriggerProductIdsInCart(Cart $cart): array
    {
        $productIds = [];

        foreach ($cart->getCartItems() as $cartItem) {
            $productIds[] = (int) $cartItem->getProductId();
        }

        if ([] === $productIds) {
            return [];
        }

        return array_map(
            '\intval',
            ProductCategoryQuery::create()
                ->filterByCategoryId($this->triggerIds, Criteria::IN)
                ->filterByProductId(array_unique($productIds), Criteria::IN)
                ->select(ProductCategoryTableMap::COL_PRODUCT_ID)
                ->find()
                ->getData(),
        );
    }

    /**
     * The discount granted on the offered line this coupon marked in the cart:
     * zero as long as the line is not there (stock shortage, not reconciled yet).
     */
    private function discountOnOfferedLine(Cart $cart, int $lots): float
    {
        /** @var CartItem $cartItem */
        foreach ($cart->getCartItems() as $cartItem) {
            if ($this->ownsOfferedLine($cartItem)) {
                return $this->discountCarriedBy($cartItem, $lots);
            }
        }

        return 0.0;
    }

    /**
     * The taxed discount THIS offered cart line carries, before any proration:
     * zero for a line this coupon does not own. This is what lets the cart page
     * price an offered line individually instead of one anonymous total.
     */
    public function taxedDiscountOnOfferedLine(CartItem $cartItem): float
    {
        if (!$this->ownsOfferedLine($cartItem)) {
            return 0.0;
        }

        $cart = $this->facade->getCart();

        if (null === $cart || [] === $this->triggerIds) {
            return 0.0;
        }

        $lots = intdiv(\count($this->eligibleUnitPrices($cart)), $this->triggerQuantity);

        if (0 === $lots) {
            return 0.0;
        }

        return $this->discountCarriedBy($cartItem, $lots);
    }

    private function ownsOfferedLine(CartItem $cartItem): bool
    {
        return self::TARGET_MODE_PRODUCT === $this->targetMode
            && null !== $this->targetProductId
            && null !== $this->getCouponModelId()
            && 1 === (int) $cartItem->getIsOffered()
            && $this->getCouponModelId() === (int) $cartItem->getOfferedByCouponId()
            && $this->targetProductId === (int) $cartItem->getProductId();
    }

    private function discountCarriedBy(CartItem $cartItem, int $lots): float
    {
        $units = min((int) $cartItem->getQuantity(), $lots * $this->offeredQuantity);
        $base = $cartItem->getRealTaxedPrice($this->facade->getDeliveryCountry()) * $units;

        return $this->applyDiscountType($base, $units);
    }

    private function applyDiscountType(float $base, int $units): float
    {
        return match ($this->discountType) {
            self::DISCOUNT_TYPE_FREE => $base,
            // setFieldsValue() clamps the percentage to [0, 100]; the min() is the
            // belt keeping the discount within what the offered units cost anyway.
            self::DISCOUNT_TYPE_PERCENTAGE => min($base * $this->discountValue / 100, $base),
            self::DISCOUNT_TYPE_AMOUNT => min($this->discountValue * $units, $base),
            default => 0.0,
        };
    }

    public function getEffects($data): array
    {
        $raw = json_decode((string) ($data[self::COUPON_DATASET_NAME] ?? ''), true);

        if (!\is_array($raw)) {
            throw new \InvalidArgumentException($this->translator->trans('The coupon fields are missing or invalid'));
        }

        $scope = (string) ($raw[self::TRIGGER_SCOPE_FIELD] ?? '');

        if (!\in_array($scope, $this->triggerScopes(), true)) {
            throw new \InvalidArgumentException($this->translator->trans('Please select what triggers the offer: a product, a category or a selection'));
        }

        $triggerIds = $this->normalizeIdList($raw[self::TRIGGER_IDS_FIELD] ?? []);

        if ([] === $triggerIds) {
            throw new \InvalidArgumentException($this->translator->trans('Please select at least one triggering product or category'));
        }

        $triggerQuantity = (int) ($raw[self::TRIGGER_QUANTITY_FIELD] ?? 0);

        if ($triggerQuantity < 1) {
            throw new \InvalidArgumentException($this->translator->trans('The triggering quantity must be at least 1'));
        }

        $mode = (string) ($raw[self::TARGET_MODE_FIELD] ?? '');

        if (!\in_array($mode, $this->targetModes(), true)) {
            throw new \InvalidArgumentException($this->translator->trans('Please select which products are offered'));
        }

        $targetProductId = (int) ($raw[self::TARGET_PRODUCT_ID_FIELD] ?? 0);

        if (self::TARGET_MODE_PRODUCT === $mode && $targetProductId < 1) {
            throw new \InvalidArgumentException($this->translator->trans('Please select the offered product'));
        }

        $offeredQuantity = (int) ($raw[self::OFFERED_QUANTITY_FIELD] ?? 0);

        if ($offeredQuantity < 1) {
            throw new \InvalidArgumentException($this->translator->trans('The offered quantity must be at least 1'));
        }

        if (self::TARGET_MODE_PRODUCT !== $mode && $offeredQuantity >= $triggerQuantity) {
            // The offered units come from the triggering units themselves: offering as
            // much as the lot takes to form would make the whole lot free on its own.
            throw new \InvalidArgumentException($this->translator->trans('The offered quantity must be lower than the triggering quantity when the offer is taken from the triggering products'));
        }

        $discountType = (string) ($raw[self::DISCOUNT_TYPE_FIELD] ?? '');

        if (!\in_array($discountType, $this->discountTypes(), true)) {
            throw new \InvalidArgumentException($this->translator->trans('Please select the discount type of the offer'));
        }

        $discountValue = (float) ($raw[self::DISCOUNT_VALUE_FIELD] ?? 0.0);

        if (self::DISCOUNT_TYPE_PERCENTAGE === $discountType && ($discountValue <= 0 || $discountValue > 100)) {
            throw new \InvalidArgumentException($this->translator->trans('The discount percentage must be between 0 and 100'));
        }

        if (self::DISCOUNT_TYPE_AMOUNT === $discountType && $discountValue <= 0) {
            throw new \InvalidArgumentException($this->translator->trans('The discount amount must be positive'));
        }

        return [
            self::TRIGGER_SCOPE_FIELD => $scope,
            self::TRIGGER_IDS_FIELD => $triggerIds,
            self::TRIGGER_QUANTITY_FIELD => $triggerQuantity,
            self::TARGET_MODE_FIELD => $mode,
            self::TARGET_PRODUCT_ID_FIELD => self::TARGET_MODE_PRODUCT === $mode ? $targetProductId : null,
            self::OFFERED_QUANTITY_FIELD => $offeredQuantity,
            self::DISCOUNT_TYPE_FIELD => $discountType,
            self::DISCOUNT_VALUE_FIELD => $discountValue,
        ];
    }

    public function getName(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans('Buy X, get Y offered', []);
    }

    public function getToolTip(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans(
                'Offers a quantity of a product, entirely or with a discount, when the cart contains a given quantity of selected products.',
                [],
            );
    }

    public function drawBackOfficeInputs(): string
    {
        return $this->facade->getParser()->render('coupon/type-fragments/buy-x-get-y.html', [
            'trigger_scope_field_name' => $this->makeCouponFieldName(self::TRIGGER_SCOPE_FIELD),
            'trigger_scope_value' => $this->triggerScope,

            'trigger_ids_field_name' => $this->makeCouponFieldName(self::TRIGGER_IDS_FIELD),
            'trigger_ids_values' => $this->triggerIds,

            'trigger_quantity_field_name' => $this->makeCouponFieldName(self::TRIGGER_QUANTITY_FIELD),
            'trigger_quantity_value' => $this->triggerQuantity,

            'target_mode_field_name' => $this->makeCouponFieldName(self::TARGET_MODE_FIELD),
            'target_mode_value' => $this->targetMode,

            'target_product_id_field_name' => $this->makeCouponFieldName(self::TARGET_PRODUCT_ID_FIELD),
            'target_product_id_value' => $this->targetProductId,

            'offered_quantity_field_name' => $this->makeCouponFieldName(self::OFFERED_QUANTITY_FIELD),
            'offered_quantity_value' => $this->offeredQuantity,

            'discount_type_field_name' => $this->makeCouponFieldName(self::DISCOUNT_TYPE_FIELD),
            'discount_type_value' => $this->discountType,

            'discount_value_field_name' => $this->makeCouponFieldName(self::DISCOUNT_VALUE_FIELD),
            'discount_value_value' => $this->discountValue,
        ]);
    }

    protected function getFieldList(): array
    {
        return [
            self::TRIGGER_SCOPE_FIELD,
            self::TRIGGER_IDS_FIELD,
            self::TRIGGER_QUANTITY_FIELD,
            self::TARGET_MODE_FIELD,
            self::TARGET_PRODUCT_ID_FIELD,
            self::OFFERED_QUANTITY_FIELD,
            self::DISCOUNT_TYPE_FIELD,
            self::DISCOUNT_VALUE_FIELD,
        ];
    }

    /**
     * @return int[]
     */
    private function normalizeIdList(mixed $ids): array
    {
        if (\is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if (!\is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('\intval', $ids), static fn (int $id): bool => $id > 0));
    }

    /**
     * @return string[]
     */
    private function triggerScopes(): array
    {
        return [self::TRIGGER_SCOPE_PRODUCT, self::TRIGGER_SCOPE_CATEGORY, self::TRIGGER_SCOPE_SELECTION];
    }

    /**
     * @return string[]
     */
    private function targetModes(): array
    {
        return [self::TARGET_MODE_SAME, self::TARGET_MODE_PRODUCT, self::TARGET_MODE_CHEAPEST];
    }

    /**
     * @return string[]
     */
    private function discountTypes(): array
    {
        return [self::DISCOUNT_TYPE_FREE, self::DISCOUNT_TYPE_PERCENTAGE, self::DISCOUNT_TYPE_AMOUNT];
    }
}

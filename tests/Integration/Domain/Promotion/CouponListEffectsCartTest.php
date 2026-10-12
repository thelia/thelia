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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartItemTableMap;

/**
 * The coupon types that target a list of products, categories or attribute
 * values, saved the way the back-office saves them: the posted coupon_specific
 * array is JSON-encoded, turned into effects by the type's getEffects(), stored,
 * then rebuilt from the database on every cart evaluation.
 *
 * The form posts every id of the list as a string, the API stores them as
 * integers, and coupons saved before the list was kept whole hold a single id.
 * All of them must discount the cart.
 */
final class CouponListEffectsCartTest extends ActionIntegrationPromotionTestCase
{
    public function testAProductDiscountAppliesToEveryProductOfTheList(): void
    {
        $category = $this->factory->category();
        $first = $this->productIn($category);
        $second = $this->productIn($category);

        $this->automaticPromotion(
            type: 'thelia.coupon.type.remove_amount_on_products',
            effects: $this->effectsSavedByTheBackOffice('thelia.coupon.type.remove_amount_on_products', [
                'amount' => '3',
                'category_id' => (string) $category->getId(),
                'products' => [(string) $first->getId(), (string) $second->getId()],
            ]),
            conditions: $this->atLeastLines(1),
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $second);

        self::assertSame(3.0, $this->cartDiscount($cart), 'The second product of the list must be discounted too.');
    }

    public function testACategoryDiscountAppliesToEveryCategoryOfTheList(): void
    {
        $firstCategory = $this->factory->category();
        $secondCategory = $this->factory->category();
        $product = $this->productIn($secondCategory);

        $this->automaticPromotion(
            type: 'thelia.coupon.type.remove_amount_on_categories',
            effects: $this->effectsSavedByTheBackOffice('thelia.coupon.type.remove_amount_on_categories', [
                'amount' => '3',
                'categories' => [(string) $firstCategory->getId(), (string) $secondCategory->getId()],
            ]),
            conditions: $this->atLeastLines(1),
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $product);

        self::assertSame(3.0, $this->cartDiscount($cart), 'A product of the second category must be discounted too.');
    }

    public function testAnAttributeValueDiscountAppliesToEveryValueOfTheList(): void
    {
        $attribute = $this->factory->attribute();
        $firstValue = $this->factory->attributeAv($attribute);
        $secondValue = $this->factory->attributeAv($attribute);
        $product = $this->product();
        $this->factory->attributeCombination($this->defaultPseFor($product), $secondValue);

        $this->automaticPromotion(
            type: 'thelia.coupon.type.remove_amount_on_attribute_av',
            effects: $this->effectsSavedByTheBackOffice('thelia.coupon.type.remove_amount_on_attribute_av', [
                'amount' => '3',
                'attribute_id' => (string) $attribute->getId(),
                'attribute_avs' => [(string) $firstValue->getId(), (string) $secondValue->getId()],
            ]),
            conditions: $this->atLeastLines(1),
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $product);

        self::assertSame(3.0, $this->cartDiscount($cart), 'A sale element carrying the second value must be discounted too.');
    }

    public function testAFreeProductIsOfferedForEveryProductOfTheList(): void
    {
        $category = $this->factory->category();
        $first = $this->productIn($category);
        $second = $this->productIn($category);
        $gift = $this->productIn($category);

        $coupon = $this->factory->coupon([
            'code' => 'FREEPROD-'.uniqid(),
            'type' => 'thelia.coupon.type.free_product',
            'effects' => $this->effectsSavedByTheBackOffice('thelia.coupon.type.free_product', [
                'category_id' => (string) $category->getId(),
                'products' => [(string) $first->getId(), (string) $second->getId()],
                'offered_category_id' => (string) $category->getId(),
                'offered_product_id' => (string) $gift->getId(),
            ]),
            'conditions' => $this->atLeastLines(1),
        ]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $second);

        $this->session()->setConsumedCoupons([$coupon->getCode()]);
        $this->recomputeDiscount();

        self::assertSame(1, $this->linesOf($cart, $gift->getId()), 'Buying the second product of the list must put the gift in the cart.');
        self::assertGreaterThan(0.0, $this->cartDiscount($cart), 'The gift must be discounted.');
    }

    public function testAProductDiscountSavedWithASingleProductStillApplies(): void
    {
        $category = $this->factory->category();
        $product = $this->productIn($category);

        $this->automaticPromotion(
            type: 'thelia.coupon.type.remove_amount_on_products',
            effects: [
                'amount' => '3',
                'category_id' => (string) $category->getId(),
                'products' => (string) $product->getId(),
            ],
            conditions: $this->atLeastLines(1),
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $product);

        self::assertSame(3.0, $this->cartDiscount($cart), 'Effects holding one product id as a string must still discount it.');
    }

    public function testAProductDiscountStoredWithIntegerIdsApplies(): void
    {
        $category = $this->factory->category();
        $first = $this->productIn($category);
        $second = $this->productIn($category);

        $this->automaticPromotion(
            type: 'thelia.coupon.type.remove_amount_on_products',
            effects: [
                'amount' => 3,
                'category_id' => $category->getId(),
                'products' => [$first->getId(), $second->getId()],
            ],
            conditions: $this->atLeastLines(1),
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $second);

        self::assertSame(3.0, $this->cartDiscount($cart));
    }

    /**
     * What CouponController::save() of the back-office does with the posted
     * coupon_specific array before the effects are stored.
     *
     * @param array<string, mixed> $couponSpecific
     *
     * @return array<string, mixed>
     */
    private function effectsSavedByTheBackOffice(string $couponType, array $couponSpecific): array
    {
        $type = $this->getService($couponType);
        self::assertInstanceOf(CouponInterface::class, $type);

        return $type->getEffects([
            CouponAbstract::COUPON_DATASET_NAME => json_encode($couponSpecific, \JSON_THROW_ON_ERROR),
        ]);
    }

    private function linesOf(Cart $cart, int $productId): int
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->filterByProductId($productId)
            ->count();
    }

    private function cartDiscount(Cart $cart): float
    {
        $cart->reload();

        return (float) $cart->getDiscount();
    }

    private function atLeastLines(int $lines): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => $lines],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}

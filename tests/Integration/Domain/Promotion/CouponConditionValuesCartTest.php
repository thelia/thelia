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

use BackOfficeDefaultTwigBundle\Service\Coupon\CouponConditionsRenderer;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Implementation\MatchForXArticles;
use Thelia\Condition\Operators;
use Thelia\Model\Cart;

/**
 * The coupon conditions that compare a number of articles or the cart total,
 * saved the way the back-office saves them, then evaluated on a real cart.
 */
final class CouponConditionValuesCartTest extends ActionIntegrationPromotionTestCase
{
    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given.
        if (!class_exists(CouponConditionsRenderer::class)) {
            self::markTestSkipped('The default-twig back-office theme is not installed.');
        }

        parent::setUp();
    }

    public function testAnArticleCountAboveTheThresholdMatches(): void
    {
        $this->promotionWhen('thelia.condition.match_for_x_articles', [
            MatchForXArticles::CART_QUANTITY => ['operator' => Operators::SUPERIOR_OR_EQUAL, 'value' => '2'],
        ]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        self::assertSame(0.0, $this->cartDiscount($cart), 'One article is short of the threshold.');

        $this->addItem($cart, $this->product());

        self::assertSame(5.0, $this->cartDiscount($cart));
    }

    /**
     * The cart counts its units with the quantity of each line, a float.
     */
    public function testAQuantityAboveTheThresholdMatches(): void
    {
        $this->promotionWhen('thelia.condition.match_for_x_articles_include_quantity', [
            MatchForXArticles::CART_QUANTITY => ['operator' => Operators::SUPERIOR_OR_EQUAL, 'value' => '3'],
        ]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(), quantity: 2);

        self::assertSame(0.0, $this->cartDiscount($cart), 'Two units are short of the threshold.');

        $this->addItem($cart, $this->product());

        self::assertSame(5.0, $this->cartDiscount($cart));
    }

    /**
     * A product sold by weight is bought in a decimal quantity: two lines of half
     * a unit make one unit, not two lines of nothing.
     */
    public function testDecimalQuantitiesAddUpToTheThreshold(): void
    {
        $this->promotionWhen('thelia.condition.match_for_x_articles_include_quantity', [
            MatchForXArticles::CART_QUANTITY => ['operator' => Operators::SUPERIOR_OR_EQUAL, 'value' => '1'],
        ]);

        $cart = $this->newEmptyCart();
        $this->addLine($cart, 0.5);
        $this->addLine($cart, 0.5);

        self::assertSame(5.0, $this->cartDiscount($cart), 'Two half units make one unit.');
    }

    public function testDecimalQuantitiesAddUpToTheExactCount(): void
    {
        $this->promotionWhen('thelia.condition.match_for_x_articles_include_quantity', [
            MatchForXArticles::CART_QUANTITY => ['operator' => Operators::EQUAL, 'value' => '2'],
        ]);

        $cart = $this->newEmptyCart();
        $this->addLine($cart, 1.5);
        $this->addLine($cart, 0.5);

        self::assertSame(5.0, $this->cartDiscount($cart), 'One unit and a half plus half a unit make two units.');
    }

    public function testACartTotalBelowTheConditionMatches(): void
    {
        $this->promotionWhen('thelia.condition.match_for_total_amount', [
            MatchForTotalAmount::CART_TOTAL => ['operator' => Operators::INFERIOR, 'value' => '1000'],
            MatchForTotalAmount::CART_CURRENCY => ['operator' => Operators::EQUAL, 'value' => $this->session()->getCurrency(true)->getCode()],
        ]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        self::assertSame(5.0, $this->cartDiscount($cart));
    }

    /**
     * Saves an automatic promotion of 5.00 under one condition, built from the
     * request the condition editor of the back-office posts.
     *
     * @param array<string, array{operator: string, value: string}> $fields
     */
    private function promotionWhen(string $conditionServiceId, array $fields): void
    {
        $request = Request::create('/admin/coupon/1/condition/save', 'POST', array_merge(
            ['categoryCondition' => $conditionServiceId],
            $fields,
        ));

        $renderer = $this->getService(CouponConditionsRenderer::class);
        self::assertInstanceOf(CouponConditionsRenderer::class, $renderer);

        $condition = $renderer->buildConditionFromRequest($request);
        self::assertNotNull($condition);

        $conditions = new ConditionCollection();
        $conditions[] = $condition;

        $this->automaticPromotion(conditions: $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions));
    }

    /**
     * The cart events take whole quantities only: a decimal line is written
     * directly, then the discount is computed again.
     */
    private function addLine(Cart $cart, float $quantity): void
    {
        // A new cart is only written with its first line.
        if ($cart->isNew()) {
            $cart->save();
        }

        $product = $this->product();
        $this->factory->cartItem($cart, $product, $this->defaultPseFor($product), ['quantity' => $quantity]);
        // The session holds this very cart, with the lines it loaded before this one.
        $cart->clearCartItems();
        $this->recomputeDiscount();
    }

    private function cartDiscount(Cart $cart): float
    {
        $cart->reload();

        return (float) $cart->getDiscount();
    }
}

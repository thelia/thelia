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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon\Type;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The category scope, the one that reads the catalog: which cart products
 * belong to the triggering categories.
 */
final class BuyXGetYTest extends ActionIntegrationTestCase
{
    /**
     * A coupon instance lives for one evaluation cycle, in which exec() and
     * getOfferedLineRequests() must agree: the membership is read once.
     */
    public function testOneEvaluationCycleReadsTheCategoryMembershipOnce(): void
    {
        $category = $this->factory->category();
        $product = $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency());
        $coupon = $this->categoryCoupon($category->getId(), $this->cartHolding($product->getId(), 2, 10.0));

        $firstDiscount = $coupon->exec();
        ProductCategoryQuery::create()->filterByProductId($product->getId())->delete();

        self::assertSame(10.0, $firstDiscount);
        self::assertSame($firstDiscount, $coupon->exec());
    }

    private function categoryCoupon(int $categoryId, Cart $cart): BuyXGetY
    {
        $facade = $this->createStub(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($this->createStub(Translator::class));
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getCart')->willReturn($cart);
        $facade->method('getDeliveryCountry')->willReturn(new Country());

        return (new BuyXGetY($facade))->set(
            $facade,
            'CATEGORY',
            'Two from the category, one offered',
            '',
            '',
            [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_CATEGORY,
                BuyXGetY::TRIGGER_IDS_FIELD => [$categoryId],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_SAME,
                BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
            ],
            true,
            false,
            false,
            true,
            -1,
            new \DateTime('+1 month'),
            [],
            [],
            false,
        );
    }

    private function cartHolding(int $productId, int $quantity, float $unitPrice): Cart
    {
        $cartItem = $this->createStub(CartItem::class);
        $cartItem->method('getProductId')->willReturn($productId);
        $cartItem->method('getQuantity')->willReturn((float) $quantity);
        $cartItem->method('getRealTaxedPrice')->willReturn($unitPrice);
        $cartItem->method('getPromo')->willReturn(0);
        $cartItem->method('getIsOffered')->willReturn(0);

        $cart = $this->createStub(Cart::class);
        $cart->method('getId')->willReturn(1);
        $cart->method('getCartItems')->willReturnCallback(
            static fn (): \ArrayIterator => new \ArrayIterator([$cartItem]),
        );

        return $cart;
    }
}

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

namespace Thelia\Tests\Unit\Condition;

use Thelia\Condition\Exception\UnmatchableConditionException;
use Thelia\Condition\Implementation\MatchDeliveryModules;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Model\Cart;

/**
 * The empty-list path builds an InvalidConditionValueException, whose
 * constructor pulls in Tlog and ConfigQuery — see CartContainsProductsTest for
 * the same note. Only the evaluation paths are covered here.
 */
final class MatchDeliveryModulesTest extends FacadeBackedTestCase
{
    public function testServiceIdIsStable(): void
    {
        $condition = new MatchDeliveryModules($this->makeFacade());

        self::assertSame('thelia.condition.match_delivery_modules', $condition->getServiceId());
    }

    public function testIsMatchingReturnsTrueWhenTheChosenModuleIsListed(): void
    {
        $condition = $this->condition(Operators::IN, [4, 9], deliveryModuleId: 9);

        self::assertTrue($condition->isMatching());
    }

    public function testIsMatchingReturnsFalseWhenTheChosenModuleIsNotListed(): void
    {
        $condition = $this->condition(Operators::IN, [4, 9], deliveryModuleId: 12);

        self::assertFalse($condition->isMatching());
    }

    public function testTheOutOperatorExcludesTheListedModules(): void
    {
        self::assertFalse($this->condition(Operators::OUT, [4, 9], deliveryModuleId: 9)->isMatching());
        self::assertTrue($this->condition(Operators::OUT, [4, 9], deliveryModuleId: 12)->isMatching());
    }

    /**
     * The ids come back from the form as strings; the cart hands out an integer.
     */
    public function testModuleIdsGivenAsStringsStillMatch(): void
    {
        $condition = $this->condition(Operators::IN, ['4', '9'], deliveryModuleId: 9);

        self::assertTrue($condition->isMatching());
    }

    public function testNoDeliveryMethodChosenYetMakesTheConditionUnmatchable(): void
    {
        $condition = $this->condition(Operators::IN, [4], deliveryModuleId: null);

        $this->expectException(UnmatchableConditionException::class);

        $condition->isMatching();
    }

    public function testNoCartAtAllMakesTheConditionUnmatchable(): void
    {
        $facade = $this->makeFacade();
        $facade->method('getCart')->willReturn(null);

        $condition = (new MatchDeliveryModules($facade))->setValidatorsFromForm(
            [MatchDeliveryModules::MODULES_LIST => Operators::IN],
            [MatchDeliveryModules::MODULES_LIST => [4]],
        );

        $this->expectException(UnmatchableConditionException::class);

        $condition->isMatching();
    }

    /**
     * @param list<int|string> $moduleIds
     */
    private function condition(string $operator, array $moduleIds, ?int $deliveryModuleId): MatchDeliveryModules
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getDeliveryModuleId')->willReturn($deliveryModuleId);

        /** @var FacadeInterface&\PHPUnit\Framework\MockObject\MockObject $facade */
        $facade = $this->makeFacade();
        $facade->method('getCart')->willReturn($cart);

        return (new MatchDeliveryModules($facade))->setValidatorsFromForm(
            [MatchDeliveryModules::MODULES_LIST => $operator],
            [MatchDeliveryModules::MODULES_LIST => $moduleIds],
        );
    }
}

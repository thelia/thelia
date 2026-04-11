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

use Thelia\Condition\Implementation\MatchForXArticles;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;

/**
 * Failure paths (invalid operator / non-positive quantity) construct a
 * Tlog instance that hits ConfigQuery — see MatchForTotalAmountTest for
 * the full note. Only the happy `isMatching()` path is covered here.
 */
final class MatchForXArticlesTest extends FacadeBackedTestCase
{
    public function testServiceIdIsStable(): void
    {
        $condition = new MatchForXArticles($this->makeFacade());

        self::assertSame('thelia.condition.match_for_x_articles', $condition->getServiceId());
    }

    public function testIsMatchingDelegatesToCartArticleCount(): void
    {
        $facade = $this->makeFacade();
        $facade->method('getNbArticlesInCart')->willReturn(5);

        $condition = (new MatchForXArticles($facade))->setValidatorsFromForm(
            [MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR_OR_EQUAL],
            [MatchForXArticles::CART_QUANTITY => 3],
        );

        self::assertTrue($condition->isMatching());
    }

    public function testIsMatchingReturnsFalseWhenCartArticleCountIsBelowThreshold(): void
    {
        $facade = $this->makeFacade();
        $facade->method('getNbArticlesInCart')->willReturn(2);

        $condition = (new MatchForXArticles($facade))->setValidatorsFromForm(
            [MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR_OR_EQUAL],
            [MatchForXArticles::CART_QUANTITY => 3],
        );

        self::assertFalse($condition->isMatching());
    }

    /**
     * Builds a facade from the base class and makes `getNbArticlesInCart`
     * configurable without losing the ConditionEvaluator binding.
     */
    protected function makeFacade(): FacadeInterface
    {
        /** @var FacadeInterface&\PHPUnit\Framework\MockObject\MockObject $facade */
        $facade = parent::makeFacade();

        return $facade;
    }
}

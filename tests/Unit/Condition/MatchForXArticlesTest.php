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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Condition\Exception\InvalidConditionOperatorException;
use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Implementation\MatchForXArticles;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Tests\Support\Log\SilencedTlog;

/**
 * An article count condition compares the number of articles in the cart with a
 * strictly positive whole quantity. The exceptions rejecting a bad form log an
 * error, so the logger reads a configuration sending it nowhere instead of the
 * database.
 */
final class MatchForXArticlesTest extends FacadeBackedTestCase
{
    use SilencedTlog;

    protected function setUp(): void
    {
        $this->silenceTlog();
    }

    protected function tearDown(): void
    {
        $this->restoreTlog();
    }

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

    public function testACartWithFewerArticlesMatchesTheLessThanOperator(): void
    {
        $facade = $this->makeFacade();
        $facade->method('getNbArticlesInCart')->willReturn(2);

        $condition = (new MatchForXArticles($facade))->setValidatorsFromForm(
            [MatchForXArticles::CART_QUANTITY => Operators::INFERIOR],
            [MatchForXArticles::CART_QUANTITY => 3],
        );

        self::assertTrue($condition->isMatching());
    }

    public function testAListOperatorIsRejected(): void
    {
        $this->expectException(InvalidConditionOperatorException::class);

        (new MatchForXArticles($this->makeFacade()))->setValidatorsFromForm(
            [MatchForXArticles::CART_QUANTITY => Operators::IN],
            [MatchForXArticles::CART_QUANTITY => 3],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function quantitiesThatAreNotACount(): iterable
    {
        yield 'zero' => ['0'];
        yield 'a negative quantity' => ['-2'];
        yield 'a word instead of a number' => ['three'];
    }

    #[DataProvider('quantitiesThatAreNotACount')]
    public function testAQuantityThatIsNotStrictlyPositiveIsRejected(string $quantity): void
    {
        $this->expectException(InvalidConditionValueException::class);
        $this->expectExceptionMessage('on parameter quantity');

        (new MatchForXArticles($this->makeFacade()))->setValidatorsFromForm(
            [MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR_OR_EQUAL],
            [MatchForXArticles::CART_QUANTITY => $quantity],
        );
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

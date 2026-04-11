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

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;

final class MatchForEveryoneTest extends FacadeBackedTestCase
{
    public function testServiceIdIsStable(): void
    {
        $condition = new MatchForEveryone($this->makeFacade());

        self::assertSame('thelia.condition.match_for_everyone', $condition->getServiceId());
    }

    public function testIsMatchingAlwaysReturnsTrue(): void
    {
        $condition = new MatchForEveryone($this->makeFacade());

        self::assertTrue($condition->isMatching());
    }

    public function testSetValidatorsFromFormIsANoop(): void
    {
        $condition = new MatchForEveryone($this->makeFacade());
        $result = $condition->setValidatorsFromForm(['ignored' => '>'], ['ignored' => 'value']);

        self::assertSame($condition, $result);
        self::assertTrue($condition->isMatching());
    }

    public function testNameAndTooltipGoThroughTheTranslator(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->expects(self::atLeastOnce())
            ->method('trans')
            ->willReturnArgument(0);

        $facade = $this->createMock(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($translator);
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());

        $condition = new MatchForEveryone($facade);

        self::assertSame('Unconditional usage', $condition->getName());
        self::assertSame('This condition is always true', $condition->getToolTip());
    }
}

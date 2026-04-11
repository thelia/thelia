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

use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;

/**
 * Parent for condition tests — wires a fake FacadeInterface with:
 *  - a real ConditionEvaluator (pure arithmetic, no side effects)
 *  - a translator that returns the source message verbatim
 *
 * Individual tests override the facade expectations they care about
 * (cart total, currency, products…) via {@see makeFacade()}.
 *
 * Note: ConditionAbstract::$translator is typed against the concrete
 * Thelia Translator — we must mock the concrete class, not
 * TranslatorInterface, or the property assignment will throw a TypeError.
 */
abstract class FacadeBackedTestCase extends TestCase
{
    protected function makeFacade(): FacadeInterface
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('trans')->willReturnArgument(0);

        $facade = $this->createMock(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($translator);
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());

        return $facade;
    }
}

<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Condition;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Translation\Translator;

/**
 * Unit Test Operators Class
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class OperatorsTest extends TestCase
{
    public function testOperatorI18n()
    {
        /** @var Translator $stubTranslator */
        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnCallback(([$this, 'callbackI18n'])));

        $actual = Operators::getI18n($stubTranslator, Operators::INFERIOR);
        $expected = 'Less than';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::INFERIOR_OR_EQUAL);
        $expected = 'Less than or equals';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::EQUAL);
        $expected = 'Equal to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::SUPERIOR_OR_EQUAL);
        $expected = 'Greater than or equals';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::SUPERIOR);
        $expected = 'Greater than';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::DIFFERENT);
        $expected = 'Not equal to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::IN);
        $expected = 'In';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::OUT);
        $expected = 'Not in';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, 'unexpected operator');
        $expected = 'unexpected operator';
        $this->assertEquals($expected, $actual);
    }

    public function callbackI18n()
    {
        $args = \func_get_args();

        return $args[0];
    }
}

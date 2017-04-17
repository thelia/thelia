<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace Thelia\Tests\Tools\Version;

use Thelia\Tools\Version\Version as Tester;

/**
 * Class Version
 * @package Thelia\Tests\Tools\Version
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Version extends \PHPUnit_Framework_TestCase
{
    public function compareProvider()
    {
        return [
            ['2.1', '=2.1', true],
            ['2.1', '2.1', true],
            ['2.1', '>=2.1', true],
            ['2.1', '>2.1', false],
            ['2.1', '<=2.1', true],
            ['2.1', '<2.1', false],
            ['2.1', '~2.1', true],
            ['2.1.0', '=2.1', true],
            ['2.1.0', '=2.1.0', true],
            ['2.1.0', '=2.1', false, true],
            ['2.1.0', '=2.1.0', true, true],
            ['2.1.0', '>=2.1', true],
            ['2.1.0', '>2.1', false],
            ['2.1.0', '<=2.1', true],
            ['2.1.0', '<=2.1.0', true],
            ['2.1.0', '<2.1', false],
            ['2.1.0', '<2.1.0', false],
            ['2.1.0', '~2.1', true],
            ['2.1.1', '=2.1.0', false],
            ['2.1.1', '>=2.1', true],
            ['2.1.1', '>2.1', true],
            ['2.1.1', '<=2.1', false],
            ['2.1.1', '<2.1', false],
            ['2.1.1', '~2.1', true],
            ['2.1.0-alpha1', '>=2.1', true],
            ['2.1.0-alpha1', '>2.1', false],
            ['2.1.0-alpha1', '=2.1', true],
            ['2.1.0-alpha1', '~2.1', true],
            ['2.1.1-alpha1', '>=2.1', true],
            ['2.1.3', '=2.1.0 >2.1.2', false],
            ['2.1.3', '>=2.1 <2.2', true],
            ['2.1.3', '>2.1 <=2.1.3', true],
            ['2.1.3', '>2.1 <2.1.3', false],
            ['2.1.3', '~2.1 >2.1.0 <2.1.4', true],
            ['2.2', '=2.2', true],
            ['2.2', '2.2', true],
            ['2.2', '>=2.2', true],
            ['2.2', '>2.2', false],
            ['2.2', '<=2.2', true],
            ['2.2', '<2.2', false],
            ['2.2', '~2.2', true],
            ['2.2.0-alpha1', '>=2.2', true],
            ['2.2.0-alpha1', '>2.1', true],
            ['2.2.0-alpha1', '<2.1', false],
            ['2.2.0-alpha1', '=2.2', true],
            ['2.2.0-alpha1', '~2.2', true],
            ['2.2.0-alpha2', '>=2.2', true],
            ['2.2.0-alpha2', '>2.1', true],
            ['2.2.0-alpha2', '<2.1', false],
            ['2.2.0-alpha2', '=2.2', true],
            ['2.2.0-alpha2', '~2.2', true],
        ];
    }

    public function parseProvider()
    {
        return [
            [ '2.1.0', [
                'version'         => '2.1.0',
                'major'           => '2',
                'minus'           => '1',
                'release'         => '0',
                'extra'           => '',
            ] ],
            [ '2.5.0', [
                'version' => '2.5.0',
                'major'   => '2',
                'minus'   => '5',
                'release' => '0',
                'extra'   => '',
            ], ],
            [ '2.3.0-alpha2', [
                'version' => '2.3.0-alpha2',
                'major'   => '2',
                'minus'   => '3',
                'release' => '0',
                'extra'   => 'alpha2',
            ], ],
        ];
    }

    public function exceptionParseProvider()
    {
        return [
            ['x.3.1',         ],
            ['2.x.1',         ],
            ['2.3.x',         ],
            ['2.3.1-alpha.2', ],
            ['2.1',           ],
            ['a.4',           ],
            ['2.1.2.4',       ],
            ['2.1.2.4.5',     ],
            ['1.alpha.8',     ],
            ['.1.2',          ],
        ];
    }

    /**
     * @dataProvider compareProvider
     */
    public function testCompare($version, $expression, $result, $strict = false, $message = null)
    {
        if (null === $message) {
            $message = sprintf(
                "Version: %s, expression: %s, expected: %s",
                $version,
                $expression,
                $result ? "true" : "false"
            );
        }

        $this->assertSame($result, Tester::test($version, $expression, $strict), $message);
    }

    /**
     * @dataProvider ParseProvider
     */
    public function testParse($version, $expected)
    {
        $message = sprintf(
            "=====\n\tVersion: %s\n\t expected: %s\n======\n",
            var_export($version, true),
            var_export($expected, true)
        );
        $this->assertEquals($expected, Tester::parse($version), $message);
    }

    /**
     * @dataProvider exceptionParseProvider
     * @expectedException InvalidArgumentException
     */
    public function testExceptionParse($version)
    {
        Tester::parse($version);
    }
}

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
}

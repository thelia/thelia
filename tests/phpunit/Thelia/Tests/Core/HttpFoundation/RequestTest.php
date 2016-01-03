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

namespace Thelia\Tests\Core\HttpFoundation;

/**
 * the the helpers addinf in Request class
 *
 * Class RequestTest
 * @package Thelia\Tests\Core\HttpFoundation
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class RequestTest extends \PHPUnit_Framework_TestCase
{
    public function testGetUriAddingParameters()
    {
        $request = $this->getMock(
            "Thelia\Core\HttpFoundation\Request",
            array("getUri", "getQueryString")
        );

        $request->expects($this->any())
            ->method("getUri")
            ->will($this->onConsecutiveCalls(
                "http://localhost/",
                "http://localhost/?test=fu"
            ));

        $request->expects($this->any())
            ->method("getQueryString")
            ->will($this->onConsecutiveCalls(
                "",
                "test=fu"
            ));

        $result = $request->getUriAddingParameters(array("foo" => "bar"));

        $this->assertEquals("http://localhost/?foo=bar", $result);

        $result = $request->getUriAddingParameters(array("foo" => "bar"));

        $this->assertEquals("http://localhost/?test=fu&foo=bar", $result);
    }
}

<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Tests\Core\Smarty\Plugins;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Smarty\Plugins\Format;

/**
 * @coversDefaultClass \Thelia\Core\Template\Smarty\Plugins\Format
 */
class FormatTest extends \PHPUnit_Framework_TestCase
{
    protected $request;

    public function setUp()
    {
        $this->request = new Request();

        $this->request->setSession(new Session(new MockArraySessionStorage()));
    }

    /**
     *
     * test formatDate method with expected format
     *
     * @covers ::formatDate
     */
    public function testFormatDateWithSpecificFormat()
    {
        $dateTime = new \DateTime();
        $format = "Y-m-d H:i:s";

        $formatClass = new Format($this->request);

        $render = $formatClass->formatDate(array(
           "date" => $dateTime,
           "format" => $format
        ));

        $this->assertEquals($dateTime->format($format), $render);
    }

    /**
     *
     * test formatDate method with date default format
     *
     * @covers ::formatDate
     */
    public function testFormatDateWithDefaultSessionParam()
    {
        $dateTime = new \DateTime();

        $langMock = $this->getLangMock();
        $this->request->getSession()->setLang($langMock);

        $formatClass = new Format($this->request);

        $render = $formatClass->formatDate(array("date" => $dateTime));

        $this->assertEquals($dateTime->format("Y-m-d H:i:s"), $render);
    }

    /**
     *
     * test formatDate method with time default format
     *
     * @covers ::formatDate
     */
    public function testFormatDateWithDateSessionParam()
    {
        $dateTime = new \DateTime();

        $langMock = $this->getLangMock();
        $this->request->getSession()->setLang($langMock);

        $formatClass = new Format($this->request);

        $render = $formatClass->formatDate(array(
            "date" => $dateTime,
            "output" => "date"
        ));

        $this->assertEquals($dateTime->format("Y-m-d"), $render);
    }

    /**
     *
     * test formatDate method with datetime default format
     *
     * @covers ::formatDate
     */
    public function testFormatDateWithTimeSessionParam()
    {
        $dateTime = new \DateTime();

        $langMock = $this->getLangMock();
        $this->request->getSession()->setLang($langMock);

        $formatClass = new Format($this->request);

        $render = $formatClass->formatDate(array(
            "date" => $dateTime,
            "output" => "time"
        ));

        $this->assertEquals($dateTime->format("H:i:s"), $render);
    }

    /**
     *
     * test formatDate method without output or expected format. datetime format must be return
     *
     * @covers ::formatDate
     */
    public function testFormatDateWithDateTimeSessionParam()
    {
        $dateTime = new \DateTime();

        $langMock = $this->getLangMock();
        $this->request->getSession()->setLang($langMock);

        $formatClass = new Format($this->request);

        $render = $formatClass->formatDate(array(
            "date" => $dateTime,
            "output" => "datetime"
        ));

        $this->assertEquals($dateTime->format("Y-m-d H:i:s"), $render);
    }

    /**
     * @covers ::formatDate
     * @expectedException \Thelia\Core\Template\Smarty\Exception\SmartyPluginException
     */
    public function testFormatDateWithoutDate()
    {
        $dateTime = new \DateTime();

        $formatClass = new Format($this->request);

        $render = $formatClass->formatDate(array());

        $this->assertEquals($dateTime->format("Y-m-d H:i:s"), $render);
    }

    /**
     * create a mock for Thelia\Model\Lang class
     * @return \Thelia\Model\Lang instance
     */
    public function getLangMock()
    {
        $mock = $this->getMock(
            "Thelia\Model\Lang",
            array(
                "getDateFormat",
                "getTimeFormat",
                "getDateTimeFormat"
            )
        );

        $mock->expects($this->any())
            ->method("getDateFormat")
            ->will($this->returnValue("Y-m-d"));

        $mock->expects($this->any())
            ->method("getTimeFormat")
            ->will($this->returnValue("H:i:s"));

        $mock->expects($this->any())
            ->method("getDateTimeFormat")
            ->will($this->returnValue("Y-m-d H:i:s"));

        return $mock;
    }



}
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
     * test formatDate without mandatory parameters
     *
     * @covers ::formatDate
     * @expectedException Thelia\Core\Template\Smarty\Exception\SmartyPluginException
     */
    public function testFormatDateWithoutDate()
    {
        $dateTime = new \DateTime();

        $formatClass = new Format($this->request);

        $render = $formatClass->formatDate(array());

        $this->assertEmpty($render);
    }

    public function testFormatDateWithLocale()
    {
        // Fails on Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') return;

        $dateTime = new \DateTime();
        // 2014-06-17
        $dateTime->setTimestamp(1402987842);

        $formatClass = new Format($this->request);

        $render = $formatClass->formatDate(array(
                'date' => $dateTime,
                'locale' => ['fr_FR.UTF-8', 'fr_FR'],
                'format' => '%e %B %Y'
            ));

        $this->assertEquals('17 juin 2014', $render);
    }

    /**
     * test formatNumber without mandatory parameters
     *
     * @covers ::formatNumber
     */
    public function testFormatNumberWithoutParams()
    {
        $formatClass = new Format($this->request);

        $render = $formatClass->formatNumber(array());

        $this->assertEmpty($render);
    }

    /**
     * test formatDate specifying all parameters
     *
     * @covers ::formatNumber
     */
    public function testFormatNumberWithAllParams()
    {
        $formatClass = new Format($this->request);

        $number = 1256.12;
        $decimals = 1;
        $decPoint = ",";
        $thousandsSep  = " ";

        $render = $formatClass->formatNumber(array(
            "number" => $number,
            "decimals" => $decimals,
            "dec_point" => $decPoint,
            "thousands_sep" => $thousandsSep
        ));

        $this->assertEquals($render, "1 256,1");
    }

    /**
     * @covers ::formatNumber
     */
    public function testFormatNumberWithDefaultParameters()
    {
        $number = 1234.56;
        $langMock = $this->getLangMock();
        $this->request->getSession()->setLang($langMock);

        $formatClass = new Format($this->request);

        $render = $formatClass->formatNumber(array(
            "number" => $number
        ));

        $this->assertEquals( $render, number_format($number, 2, ",", " "));
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
                "getDateTimeFormat",
                "getDecimalSeparator",
                "getThousandsSeparator",
                "getDecimals"
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

        $mock->expects($this->any())
            ->method("getDecimals")
            ->will($this->returnValue(2));

        $mock->expects($this->any())
            ->method("getDecimalSeparator")
            ->will($this->returnValue(","));

        $mock->expects($this->any())
            ->method("getThousandsSeparator")
            ->will($this->returnValue(" "));

        return $mock;
    }

}

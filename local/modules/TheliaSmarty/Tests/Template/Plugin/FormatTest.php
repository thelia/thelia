<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace TheliaSmarty\Tests\Template\Plugin;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\CurrencyQuery;
use TheliaSmarty\Template\Plugins\Format;

/**
 * Class FormatTest
 * @package TheliaSmarty\Tests\Template\Plugin
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 * @author Baixas Alban <abaixas@openstudio.fr>
 */
class FormatTest extends SmartyPluginTestCase
{
    /** @var  Request */
    protected $request;

    public function testFormatTwoDimensionalArray()
    {
        $plugin = new Format(new Request());

        $params['values'] = [
            'Colors' => ['Green', 'Yellow', 'Red'],
            'Material' => ['Wood']
        ];

        $output = $plugin->formatTwoDimensionalArray($params);

        $this->assertEquals(
            "Colors : Green / Yellow / Red | Material : Wood",
            $output
        );
    }

    public function testFormatMoneyNotForceCurrency()
    {
        // new format_money method, thelia >= 2.3
        $data = $this->render("testFormatMoney.html", [
            'number' => 9.9999
        ]);

        $this->assertEquals("10.00 €", $data);
    }

    public function testFormatMoneyForceCurrency()
    {
        /********************/
        /*** Test for EUR ***/
        /********************/
        $currency = CurrencyQuery::create()->findOneByCode('EUR');

        // new format_money method, thelia >= 2.3
        $data = $this->render("testFormatMoney.html", [
            'number' => 9.9999,
            'currency' => $currency->getId()
        ]);

        $this->assertEquals("10.00 " . $currency->getSymbol(), $data);

        // old format_money method, thelia < 2.3
        $data = $this->render("testFormatMoney.html", [
            'number' => 9.9999,
            'currency_symbol' => $currency->getSymbol()
        ]);

        $this->assertEquals("10.00 " . $currency->getSymbol(), $data);

        /********************/
        /*** Test for USD ***/
        /********************/
        $currency = CurrencyQuery::create()->findOneByCode('USD');

        // new format_money method, thelia >= 2.3
        $data = $this->render("testFormatMoney.html", [
            'number' => 9.9999,
            'currency' => $currency->getId()
        ]);

        $this->assertEquals($currency->getSymbol() . "10.00", $data);

        // old format_money method, thelia < 2.3
        $data = $this->render("testFormatMoney.html", [
            'number' => 9.9999,
            'currency_symbol' => $currency->getSymbol()
        ]);

        $this->assertEquals($currency->getSymbol() . "10.00", $data);

        /********************/
        /*** Test for GBP ***/
        /********************/
        $currency = CurrencyQuery::create()->findOneByCode('GBP');

        // new format_money method, thelia >= 2.3
        $data = $this->render("testFormatMoney.html", [
            'number' => 9.9999,
            'currency' => $currency->getId()
        ]);

        $this->assertEquals($currency->getSymbol() . "10.00", $data);

        // old format_money method, thelia < 2.3
        $data = $this->render("testFormatMoney.html", [
            'number' => 9.9999,
            'currency_symbol' => $currency->getSymbol()
        ]);

        $this->assertEquals($currency->getSymbol() . "10.00", $data);
    }

    /**
     * @return \TheliaSmarty\Template\AbstractSmartyPlugin
     */
    protected function getPlugin(ContainerBuilder $container)
    {
        $this->request = $container->get("request");

        return new Format($this->request);
    }
}

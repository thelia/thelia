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
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\AddressQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\StateQuery;
use TheliaSmarty\Template\Plugins\Format;

/**
 * Class FormatTest
 * @package TheliaSmarty\Tests\Template\Plugin
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 * @author Baixas Alban <abaixas@openstudio.fr>
 */
class FormatTest extends SmartyPluginTestCase
{
    /** @var RequestStack */
    protected $requestStack;

    public function testFormatTwoDimensionalArray()
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $plugin = new Format($requestStack);

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

    public function testFormatAddress()
    {
        // Test for address in France
        $countryFR = CountryQuery::create()->filterByIsoalpha2('FR')->findOne();
        $address = AddressQuery::create()->findOne();
        $address
            ->setCountryId($countryFR->getId())
            ->save();

        $data = $this->renderString(
            '{format_address address=$address locale="fr_FR"}',
            [
            'address' => $address->getId()
            ]
        );

        $title = $address->getCustomerTitle()
            ->setLocale('fr_FR')
            ->getShort();

        $expected = [
            '<p >',
            sprintf('<span class="recipient">%s %s %s</span><br>', $title, $address->getLastname(), $address->getFirstname()),
            sprintf('<span class="address-line1">%s</span><br>', $address->getAddress1()),
            sprintf('<span class="postal-code">%s</span> <span class="locality">%s</span><br>', $address->getZipcode(), $address->getCity()),
            '<span class="country">France</span>',
            '</p>'
        ];

        $this->assertEquals($data, implode("\n", $expected));

        // Test for address in USA
        $stateDC = StateQuery::create()->filterByIsocode('DC')->findOne();
        $countryUS = $stateDC->getCountry();
        $address
            ->setCountryId($countryUS->getId())
            ->setStateId($stateDC->getId())
            ->save();

        $data = $this->renderString(
            '{format_address address=$address locale="en_US"}',
            [
                'address' => $address->getId()
            ]
        );

        $title = $address->getCustomerTitle()
            ->setLocale('en_US')
            ->getShort();

        $expected = [
            '<p >',
            sprintf('<span class="recipient">%s %s %s</span><br>', $title, $address->getLastname(), $address->getFirstname()),
            sprintf('<span class="address-line1">%s</span><br>', $address->getAddress1()),
            sprintf(
                '<span class="locality">%s</span>, <span class="administrative-area">%s</span> <span class="postal-code">%s</span><br>',
                $address->getCity(),
                $stateDC->getIsocode(),
                $address->getZipcode()
            ),
            '<span class="country">United States</span>',
            '</p>'
        ];

        $this->assertEquals($data, implode("\n", $expected));

        // Test html tag
        $data = $this->renderString(
            '{format_address html_tag="address" html_class="a_class" html_id="an_id" address=$address}',
            ['address' => $address->getId()]
        );

        $this->assertTrue(strpos($data, '<address class="a_class" id="an_id">') !== false);

        // Test plain text
        $data = $this->renderString(
            '{format_address html="0" address=$address locale="en_US"}',
            [
                'address' => $address->getId()
            ]
        );

        $expected = [
            sprintf('%s %s %s', $title, $address->getLastname(), $address->getFirstname()),
            sprintf('%s', $address->getAddress1()),
            sprintf('%s, %s %s', $address->getCity(), $stateDC->getIsocode(), $address->getZipcode()),
            'United States',
        ];
        $this->assertEquals($data, implode("\n", $expected));

    }


    /**
     * @param ContainerBuilder $container
     * @return \TheliaSmarty\Template\AbstractSmartyPlugin
     */
    protected function getPlugin(ContainerBuilder $container)
    {
        $this->requestStack = $container->get("request_stack");

        return new Format($this->requestStack);
    }
}

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

namespace Thelia\Tests\ImportExport\Export;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\ImportExport\Export\Type\OrderExport;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Lang;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderStatusQuery;

/**
 * The accounting export of an order. The demo data ships no order, so the
 * test seeds its own and removes it again.
 */
class OrderExportTest extends TestCase
{
    /**
     * @var Order|null
     */
    private $order;

    /**
     * @var Cart|null
     */
    private $cart;

    /**
     * @var Customer|null
     */
    private $customer;

    /**
     * @var array<int, OrderAddress>
     */
    private $addresses = [];

    /**
     * @var Country
     */
    private $invoiceCountry;

    /**
     * @var Country
     */
    private $deliveryCountry;

    protected function setUp(): void
    {
        // getDataJsonCache() writes its row cache there without creating it;
        // only ExportHandler::processExport() does, and this test does not go
        // through the handler.
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');

        $countries = CountryQuery::create()->orderById()->limit(2)->find();

        $this->assertCount(2, $countries, 'Two countries are part of every install.');

        $this->invoiceCountry = $countries[0];
        $this->deliveryCountry = $countries[1];

        $this->customer = $this->seedCustomer();
        $this->cart = $this->seedCart();

        $anyModule = ModuleQuery::create()->findOne();

        // Order::postInsert() overwrites the reference with a generated one.
        $order = new Order();
        $order->setCustomerId($this->customer->getId());
        $order->setInvoiceOrderAddressId($this->seedOrderAddress($this->invoiceCountry)->getId());
        $order->setDeliveryOrderAddressId($this->seedOrderAddress($this->deliveryCountry)->getId());
        $order->setCurrencyId(Currency::getDefaultCurrency()->getId());
        $order->setCurrencyRate(1.0);
        $order->setPaymentModuleId($anyModule->getId());
        $order->setDeliveryModuleId($anyModule->getId());
        $order->setStatusId(OrderStatusQuery::create()->findOne()->getId());
        $order->setLangId(Lang::getDefaultLanguage()->getId());
        $order->setCartId($this->cart->getId());
        $order->setPostage('4.9');
        $order->setPostageTax('0.98');
        $order->setPostageTaxRuleTitle('TVA 20%');
        $order->save();

        $this->order = $order;
    }

    protected function tearDown(): void
    {
        foreach ([$this->order, $this->cart, $this->customer] as $model) {
            if ($model !== null) {
                $model->delete();
            }
        }

        foreach ($this->addresses as $address) {
            $address->delete();
        }

        $this->order = $this->cart = $this->customer = null;
        $this->addresses = [];
    }

    /**
     * `postage_tax_rule_title` is a VARCHAR. Wrapping it in ROUND() made
     * MySQL coerce the label to a number, so every exported order carried
     * 0.00 instead of the tax rule name.
     */
    public function testItExportsTheShippingTaxRuleTitleAsText(): void
    {
        $row = $this->exportedRow();

        $this->assertSame('TVA 20%', $row['order_postage_tax_rule_title']);
    }

    /**
     * Both country columns used to be aliased to `invoice_country`, and the
     * second one silently overwrote the first: the delivery country never
     * reached the file.
     */
    public function testTheDeliveryAndInvoiceCountriesGetTheirOwnColumn(): void
    {
        $locale = Lang::getDefaultLanguage()->getLocale();
        $aliased = (new OrderExport())->applyOrderAndAliases($this->exportedRow());

        $this->assertArrayHasKey('delivery_country', $aliased);
        $this->assertSame($this->deliveryCountry->setLocale($locale)->getTitle(), $aliased['delivery_country']);
        $this->assertSame($this->invoiceCountry->setLocale($locale)->getTitle(), $aliased['invoice_country']);
    }

    /**
     * The export yields the raw column names, not the output aliases.
     *
     * @return array<string, mixed>
     */
    private function exportedRow(): array
    {
        $export = new OrderExport();
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate(null);

        foreach ($export as $row) {
            if (isset($row['order_ref']) && $row['order_ref'] === $this->order->getRef()) {
                return $row;
            }
        }

        $this->fail('The seeded order is missing from the export.');
    }

    private function seedCustomer(): Customer
    {
        $customer = new Customer();
        $customer->setTitleId(CustomerTitleQuery::create()->findOne()->getId());
        $customer->setLangId(Lang::getDefaultLanguage()->getId());
        $customer->setFirstname('John');
        $customer->setLastname('Doe');
        $customer->setEmail(uniqid('export-test-').'@example.com');
        $customer->save();

        return $customer;
    }

    private function seedCart(): Cart
    {
        $cart = new Cart();
        $cart->setToken(uniqid('export-test-'));
        $cart->setCustomerId($this->customer->getId());
        $cart->setCurrencyId(Currency::getDefaultCurrency()->getId());
        $cart->save();

        return $cart;
    }

    private function seedOrderAddress(Country $country): OrderAddress
    {
        $address = new OrderAddress();
        $address->setCustomerTitleId(CustomerTitleQuery::create()->findOne()->getId());
        $address->setFirstname('John');
        $address->setLastname('Doe');
        $address->setAddress1('1 Main Street');
        $address->setZipcode('75001');
        $address->setCity('Paris');
        $address->setCountryId($country->getId());
        $address->save();

        $this->addresses[] = $address;

        return $address;
    }
}

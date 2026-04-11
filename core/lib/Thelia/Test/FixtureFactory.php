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

namespace Thelia\Test;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxType\PricePercentTaxType;
use Thelia\Model\Address;
use Thelia\Model\Admin;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\Brand;
use Thelia\Model\Cart;
use Thelia\Model\Category;
use Thelia\Model\Content;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Coupon;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Feature;
use Thelia\Model\FeatureAv;
use Thelia\Model\Folder;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Profile;
use Thelia\Model\Tax;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

/**
 * Creates test entities with sensible defaults.
 *
 * Reference entities (Lang, Currency, etc.) use findOrCreate: they return
 * existing seed data when available, avoiding duplicates.
 *
 * All writes go through the injected connection so that
 * IntegrationTestCase's transaction rollback catches them.
 */
final class FixtureFactory
{
    private static int $counter = 0;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    private function next(): int
    {
        return ++self::$counter;
    }

    // ------------------------------------------------------------------
    // Reference entities (findOrCreate)
    // ------------------------------------------------------------------

    public function lang(array $overrides = []): Lang
    {
        $existing = LangQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $lang = new Lang();
        $lang->setTitle($overrides['title'] ?? 'English');
        $lang->setCode($overrides['code'] ?? 'en');
        $lang->setLocale($overrides['locale'] ?? 'en_US');
        $lang->setActive($overrides['active'] ?? true);
        $lang->setVisible($overrides['visible'] ?? 1);
        $lang->setByDefault($overrides['byDefault'] ?? 0);
        $lang->setDateFormat($overrides['dateFormat'] ?? 'Y-m-d');
        $lang->setTimeFormat($overrides['timeFormat'] ?? 'H:i:s');
        $lang->setDatetimeFormat($overrides['datetimeFormat'] ?? 'Y-m-d H:i:s');
        $lang->setDecimalSeparator($overrides['decimalSeparator'] ?? '.');
        $lang->setThousandsSeparator($overrides['thousandsSeparator'] ?? ',');
        $lang->setDecimals($overrides['decimals'] ?? '2');
        $lang->save($this->connection);

        return $lang;
    }

    public function currency(array $overrides = []): Currency
    {
        $existing = CurrencyQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $currency = new Currency();
        $currency->setCode($overrides['code'] ?? 'EUR');
        $currency->setSymbol($overrides['symbol'] ?? '€');
        $currency->setRate($overrides['rate'] ?? 1.0);
        $currency->setVisible($overrides['visible'] ?? 1);
        $currency->setByDefault($overrides['byDefault'] ?? 0);
        $currency->save($this->connection);

        return $currency;
    }

    public function customerTitle(array $overrides = []): CustomerTitle
    {
        $existing = CustomerTitleQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $n = $this->next();
        $title = new CustomerTitle();
        $title->setPosition($overrides['position'] ?? (string) $n);
        $title->setByDefault($overrides['byDefault'] ?? 0);
        $title->save($this->connection);

        return $title;
    }

    public function country(array $overrides = []): Country
    {
        $existing = CountryQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $country = new Country();
        $country->setIsocode($overrides['isocode'] ?? 'FR');
        $country->setIsoalpha2($overrides['isoalpha2'] ?? 'FR');
        $country->setIsoalpha3($overrides['isoalpha3'] ?? 'FRA');
        $country->setVisible($overrides['visible'] ?? 1);
        $country->setShopCountry($overrides['shopCountry'] ?? true);
        $country->save($this->connection);

        return $country;
    }

    public function taxRule(array $overrides = []): TaxRule
    {
        $existing = TaxRuleQuery::create()->findOne($this->connection);
        if (null !== $existing && [] === $overrides) {
            return $existing;
        }

        $taxRule = new TaxRule();
        $taxRule->setIsDefault($overrides['isDefault'] ?? false);
        $taxRule->save($this->connection);

        return $taxRule;
    }

    // ------------------------------------------------------------------
    // Structural entities
    // ------------------------------------------------------------------

    public function category(array $overrides = []): Category
    {
        $category = new Category();
        $category->setParent($overrides['parent'] ?? 0);
        $category->setVisible($overrides['visible'] ?? 1);
        $category->setPosition($overrides['position'] ?? $this->next());
        $category->save($this->connection);

        return $category;
    }

    // ------------------------------------------------------------------
    // Business entities (dependencies are explicit)
    // ------------------------------------------------------------------

    public function product(
        Category $category,
        TaxRule $taxRule,
        Currency $currency,
        array $overrides = [],
    ): Product {
        $n = $this->next();

        $product = new Product();
        $product
            ->setRef($overrides['ref'] ?? 'PROD-'.$n)
            ->setVisible($overrides['visible'] ?? 1)
            ->setPosition($overrides['position'] ?? $n);

        // Product::create() handles the full creation in a transaction:
        // persist the product, assign default category, create default PSE + price.
        $product->create(
            $category->getId(),
            $overrides['basePrice'] ?? 10.0,
            $currency->getId(),
            $taxRule->getId(),
            $overrides['baseWeight'] ?? 0.0,
            $overrides['baseQuantity'] ?? 0,
        );

        return $product;
    }

    public function customer(
        CustomerTitle $title,
        array $overrides = [],
    ): Customer {
        $n = $this->next();

        $customer = new Customer();
        $customer->setTitleId($title->getId());
        $customer->setFirstname($overrides['firstname'] ?? 'John');
        $customer->setLastname($overrides['lastname'] ?? 'Doe');
        $customer->setEmail($overrides['email'] ?? 'customer-'.$n.'@test.com');
        $customer->setPassword($overrides['password'] ?? 'password');
        $customer->save($this->connection);

        return $customer;
    }

    public function admin(array $overrides = []): Admin
    {
        $n = $this->next();

        $admin = new Admin();
        $admin->setFirstname($overrides['firstname'] ?? 'Admin');
        $admin->setLastname($overrides['lastname'] ?? 'Test');
        $admin->setLogin($overrides['login'] ?? 'admin-'.$n);
        $admin->setPassword($overrides['password'] ?? 'password');
        $admin->setLocale($overrides['locale'] ?? 'en_US');
        $admin->setEmail($overrides['email'] ?? 'admin-'.$n.'@test.com');
        $admin->save($this->connection);

        return $admin;
    }

    public function address(
        Customer $customer,
        ?Country $country = null,
        ?CustomerTitle $title = null,
        array $overrides = [],
    ): Address {
        $n = $this->next();

        $address = new Address();
        $address->setCustomerId($customer->getId());
        $address->setTitleId(($title ?? $this->customerTitle())->getId());
        $address->setLabel($overrides['label'] ?? 'Address '.$n);
        $address->setFirstname($overrides['firstname'] ?? 'John');
        $address->setLastname($overrides['lastname'] ?? 'Doe');
        $address->setAddress1($overrides['address1'] ?? $n.' Main Street');
        $address->setAddress2($overrides['address2'] ?? '');
        $address->setAddress3($overrides['address3'] ?? '');
        $address->setZipcode($overrides['zipcode'] ?? '75001');
        $address->setCity($overrides['city'] ?? 'Paris');
        $address->setCountryId(($country ?? $this->country())->getId());
        $address->save($this->connection);

        return $address;
    }

    public function brand(array $overrides = []): Brand
    {
        $n = $this->next();

        $brand = new Brand();
        $brand->setVisible($overrides['visible'] ?? 1);
        // Position is auto-assigned in Brand::preInsert() via PositionManagementTrait.
        $brand->setLocale($overrides['locale'] ?? 'en_US');
        $brand->setTitle($overrides['title'] ?? 'Brand '.$n);
        $brand->save($this->connection);

        return $brand;
    }

    public function folder(int $parent = 0, array $overrides = []): Folder
    {
        $n = $this->next();

        $folder = new Folder();
        $folder->setParent($parent);
        $folder->setVisible($overrides['visible'] ?? 1);
        $folder->setLocale($overrides['locale'] ?? 'en_US');
        $folder->setTitle($overrides['title'] ?? 'Folder '.$n);
        $folder->save($this->connection);

        return $folder;
    }

    public function content(Folder $folder, array $overrides = []): Content
    {
        $n = $this->next();

        $content = new Content();
        $content->setVisible($overrides['visible'] ?? 1);
        $content->setLocale($overrides['locale'] ?? 'en_US');
        $content->setTitle($overrides['title'] ?? 'Content '.$n);
        $content->save($this->connection);

        // Content requires a default folder link via ContentFolder.
        // setDefaultFolder() persists the link itself.
        $content->setDefaultFolder($folder->getId());

        return $content;
    }

    public function attribute(array $overrides = []): Attribute
    {
        $n = $this->next();

        $attribute = new Attribute();
        $attribute->setLocale($overrides['locale'] ?? 'en_US');
        $attribute->setTitle($overrides['title'] ?? 'Attribute '.$n);
        $attribute->save($this->connection);

        return $attribute;
    }

    public function attributeAv(Attribute $attribute, array $overrides = []): AttributeAv
    {
        $n = $this->next();

        $attributeAv = new AttributeAv();
        $attributeAv->setAttributeId($attribute->getId());
        $attributeAv->setLocale($overrides['locale'] ?? 'en_US');
        $attributeAv->setTitle($overrides['title'] ?? 'Value '.$n);
        $attributeAv->save($this->connection);

        return $attributeAv;
    }

    public function feature(array $overrides = []): Feature
    {
        $n = $this->next();

        $feature = new Feature();
        $feature->setVisible($overrides['visible'] ?? 1);
        $feature->setLocale($overrides['locale'] ?? 'en_US');
        $feature->setTitle($overrides['title'] ?? 'Feature '.$n);
        $feature->save($this->connection);

        return $feature;
    }

    public function featureAv(Feature $feature, array $overrides = []): FeatureAv
    {
        $n = $this->next();

        $featureAv = new FeatureAv();
        $featureAv->setFeatureId($feature->getId());
        $featureAv->setLocale($overrides['locale'] ?? 'en_US');
        $featureAv->setTitle($overrides['title'] ?? 'Value '.$n);
        $featureAv->save($this->connection);

        return $featureAv;
    }

    public function tax(array $overrides = []): Tax
    {
        $tax = new Tax();
        $tax->setType($overrides['type'] ?? PricePercentTaxType::class);
        $tax->setRequirements($overrides['requirements'] ?? ['percent' => '20']);
        $tax->setLocale($overrides['locale'] ?? 'en_US');
        $tax->setTitle($overrides['title'] ?? 'Test VAT');
        $tax->save($this->connection);

        return $tax;
    }

    public function orderStatus(array $overrides = []): OrderStatus
    {
        $n = $this->next();

        $status = new OrderStatus();
        $status->setCode($overrides['code'] ?? 'status-'.$n);
        $status->setColor($overrides['color'] ?? '#cccccc');
        $status->setLocale($overrides['locale'] ?? 'en_US');
        $status->setTitle($overrides['title'] ?? 'Status '.$n);
        $status->save($this->connection);

        return $status;
    }

    public function orderAddress(
        ?Country $country = null,
        ?CustomerTitle $title = null,
        array $overrides = [],
    ): OrderAddress {
        $n = $this->next();

        $address = new OrderAddress();
        $address->setCustomerTitleId(($title ?? $this->customerTitle())->getId());
        $address->setFirstname($overrides['firstname'] ?? 'John');
        $address->setLastname($overrides['lastname'] ?? 'Doe');
        $address->setAddress1($overrides['address1'] ?? $n.' Main Street');
        $address->setAddress2($overrides['address2'] ?? '');
        $address->setAddress3($overrides['address3'] ?? '');
        $address->setZipcode($overrides['zipcode'] ?? '75001');
        $address->setCity($overrides['city'] ?? 'Paris');
        $address->setCountryId(($country ?? $this->country())->getId());
        $address->save($this->connection);

        return $address;
    }

    public function coupon(array $overrides = []): Coupon
    {
        $n = $this->next();

        $coupon = new Coupon();
        $coupon->setCode($overrides['code'] ?? 'COUPON-'.$n);
        $coupon->setType($overrides['type'] ?? 'thelia.coupon.type.remove_x_amount');
        $coupon->setSerializedEffects(json_encode($overrides['effects'] ?? ['amount' => 5.0], \JSON_THROW_ON_ERROR));
        $coupon->setIsEnabled($overrides['isEnabled'] ?? true);
        $coupon->setExpirationDate($overrides['expirationDate'] ?? new \DateTime('+1 month'));
        $coupon->setMaxUsage($overrides['maxUsage'] ?? Coupon::UNLIMITED_COUPON_USE);
        $coupon->setIsCumulative($overrides['isCumulative'] ?? false);
        $coupon->setIsRemovingPostage($overrides['isRemovingPostage'] ?? false);
        $coupon->setIsAvailableOnSpecialOffers($overrides['isAvailableOnSpecialOffers'] ?? false);
        $coupon->setIsUsed($overrides['isUsed'] ?? false);
        $coupon->setPerCustomerUsageCount($overrides['perCustomerUsageCount'] ?? false);
        $coupon->setSerializedConditions($overrides['conditions'] ?? '');
        $coupon->setLocale($overrides['locale'] ?? 'en_US');
        $coupon->setTitle($overrides['title'] ?? 'Coupon '.$n);
        $coupon->setShortDescription('');
        $coupon->setDescription('');
        $coupon->save($this->connection);

        return $coupon;
    }

    public function profile(array $overrides = []): Profile
    {
        $n = $this->next();

        $profile = new Profile();
        $profile->setCode($overrides['code'] ?? 'profile-'.$n);
        $profile->setLocale($overrides['locale'] ?? 'en_US');
        $profile->setTitle($overrides['title'] ?? 'Profile '.$n);
        $profile->save($this->connection);

        return $profile;
    }

    /**
     * Creates an ADDITIONAL ProductSaleElements row. The default PSE is
     * already created by Product::create() — never call this for the
     * default one.
     */
    public function productSaleElement(Product $product, array $overrides = []): ProductSaleElements
    {
        $n = $this->next();

        $pse = new ProductSaleElements();
        $pse->setProductId($product->getId());
        $pse->setRef($overrides['ref'] ?? $product->getRef().'-PSE-'.$n);
        $pse->setQuantity($overrides['quantity'] ?? 10);
        $pse->setWeight($overrides['weight'] ?? 0.0);
        $pse->setIsDefault($overrides['isDefault'] ?? false);
        $pse->save($this->connection);

        return $pse;
    }

    /**
     * Creates a Cart. Mostly used as a structural dependency for Order.
     */
    public function cart(?Customer $customer = null, array $overrides = []): Cart
    {
        $cart = new Cart();
        if (null !== $customer) {
            $cart->setCustomerId($customer->getId());
        }
        $cart->setCurrencyId(($overrides['currency'] ?? $this->currency())->getId());
        $cart->setToken($overrides['token'] ?? 'cart-token-'.$this->next());
        $cart->save($this->connection);

        return $cart;
    }

    /**
     * Creates a minimal Order with its mandatory structural dependencies:
     * customer, invoice + delivery OrderAddress, cart, payment and
     * delivery modules (any installed module is reused — CustomDelivery
     * and Cheque ship by default). The order goes straight into the
     * "not_paid" status unless `statusCode` is overridden.
     *
     * If the CustomerFamily module is installed, the customer is
     * attached to a family on the fly so the module's
     * ORDER_AFTER_CREATE listener has something to link the fresh
     * order to — without this, the listener dereferences null and
     * crashes the save path.
     *
     * The factory does NOT add products to the order — dedicated tests
     * that need real revenue should create their own OrderProduct rows.
     */
    public function order(?Customer $customer = null, array $overrides = []): Order
    {
        $customer ??= $this->customer($this->customerTitle());
        $this->ensureCustomerFamilyLink($customer);
        $invoiceAddress = $this->orderAddress();
        $deliveryAddress = $this->orderAddress();
        $cart = $this->cart($customer);
        $currency = $this->currency();
        $lang = $this->lang();

        $statusCode = $overrides['statusCode'] ?? OrderStatus::CODE_NOT_PAID;
        $status = OrderStatusQuery::create()->findOneByCode($statusCode)
            ?? throw new \RuntimeException("Seeded order status '$statusCode' is missing — run bin/test-prepare.");

        $deliveryModule = ModuleQuery::create()->findOneByCode(
            $overrides['deliveryModuleCode'] ?? 'CustomDelivery',
        ) ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $paymentModule = ModuleQuery::create()->findOneByCode(
            $overrides['paymentModuleCode'] ?? 'Cheque',
        ) ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');

        $order = new Order();
        $order->setCustomer($customer);
        $order->setInvoiceOrderAddressId($invoiceAddress->getId());
        $order->setDeliveryOrderAddressId($deliveryAddress->getId());
        $order->setCurrencyId($currency->getId());
        $order->setCurrencyRate((float) ($overrides['currencyRate'] ?? 1.0));
        $order->setPaymentModuleId($paymentModule->getId());
        $order->setDeliveryModuleId($deliveryModule->getId());
        $order->setStatusId($status->getId());
        $order->setLangId($lang->getId());
        $order->setCartId($cart->getId());
        $order->setPostage((string) ($overrides['postage'] ?? 0));
        $order->setPostageTax((string) ($overrides['postageTax'] ?? 0));
        $order->save($this->connection);

        return $order;
    }

    /**
     * Attaches the customer to a CustomerFamily row so that
     * CustomerFamily's ORDER_AFTER_CREATE listener does not find null.
     *
     * The CustomerFamily module is optional — if it is not installed
     * (e.g. the model/query classes are missing), this is a no-op.
     */
    private function ensureCustomerFamilyLink(Customer $customer): void
    {
        $familyClass = 'CustomerFamily\\Model\\CustomerFamily';
        $familyQueryClass = 'CustomerFamily\\Model\\CustomerFamilyQuery';
        $linkQueryClass = 'CustomerFamily\\Model\\CustomerCustomerFamilyQuery';
        $linkClass = 'CustomerFamily\\Model\\CustomerCustomerFamily';

        if (!class_exists($familyClass) || !class_exists($familyQueryClass)) {
            return;
        }

        $existingLink = $linkQueryClass::create()
            ->filterByCustomerId($customer->getId())
            ->findOne($this->connection);
        if (null !== $existingLink) {
            return;
        }

        $family = $familyQueryClass::create()->findOne($this->connection);
        if (null === $family) {
            $family = new $familyClass();
            $family->setCode('fixture-family-'.$this->next());
            $family->setIsDefault(1);
            $family->setLocale('en_US');
            $family->setTitle('Fixture family');
            $family->save($this->connection);
        }

        $link = new $linkClass();
        $link->setCustomerId($customer->getId());
        $link->setCustomerFamilyId($family->getId());
        $link->save($this->connection);
    }
}

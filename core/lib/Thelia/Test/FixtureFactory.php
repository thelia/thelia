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
use Thelia\Core\Security\AccessManager;
use Thelia\Domain\Taxation\TaxEngine\TaxType\PricePercentTaxType;
use Thelia\Model\Accessory;
use Thelia\Model\Address;
use Thelia\Model\Admin;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\Brand;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItem;
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
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Profile;
use Thelia\Model\ProfileResource;
use Thelia\Model\Resource;
use Thelia\Model\ResourceQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleCustomer;
use Thelia\Model\SaleOffsetCurrency;
use Thelia\Model\SaleProduct;
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

        // Timestampable only fills created_at when it is untouched, so setting it
        // here survives the insert.
        if (isset($overrides['createdAt'])) {
            $product->setCreatedAt(self::wholeSeconds($overrides['createdAt']));
        }

        // A product has no product_i18n row unless a title is asked for, which is
        // what makes an untranslated product testable.
        if (isset($overrides['title'])) {
            $product
                ->setLocale($overrides['locale'] ?? 'en_US')
                ->setTitle($overrides['title']);
        }

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

        // Product::create() saves the product several times, so updated_at can only
        // be forced once the creation is over.
        if (isset($overrides['updatedAt'])) {
            $product->setUpdatedAt(self::wholeSeconds($overrides['updatedAt']))->save($this->connection);
        }

        return $product;
    }

    /**
     * Ties an accessory to a product at the given position, the way the back-office does.
     *
     * The position is written after the insert: Accessory::preInsert() overwrites it with the
     * next free one, so a position asked for at creation time never survives.
     */
    public function accessory(Product $product, Product $accessory, int $position): Accessory
    {
        $link = new Accessory();
        $link
            ->setProductId($product->getId())
            ->setAccessory($accessory->getId())
            ->save($this->connection);

        $link->setPosition($position)->save($this->connection);

        return $link;
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

    /**
     * A customer who ordered without creating an account: no password, marked as a guest.
     *
     * customer() always sets a password, which is exactly what a guest must not have,
     * and what tells the two apart everywhere the guest checkout is involved.
     */
    public function guestCustomer(
        CustomerTitle $title,
        array $overrides = [],
    ): Customer {
        $n = $this->next();

        $customer = new Customer();
        $customer->setIsGuest(1);
        $customer->setTitleId($title->getId());
        $customer->setFirstname($overrides['firstname'] ?? 'Guest');
        $customer->setLastname($overrides['lastname'] ?? 'Visitor');
        $customer->setEmail($overrides['email'] ?? 'guest-'.$n.'@test.com');
        $customer->setEnable(0);
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

        if (isset($overrides['profile'])) {
            $admin->setProfileId($overrides['profile']->getId());
        }

        $admin->save($this->connection);

        return $admin;
    }

    /**
     * Creates an administrator whose profile grants exactly the accesses given,
     * as [AdminResources code => list of AccessManager constants]. Unlike an
     * admin() with no profile, which is a superadministrator, such an
     * administrator is subject to the per-resource permission checks.
     *
     * @param array<string, list<string>> $grants
     */
    public function restrictedAdmin(array $grants, array $overrides = []): Admin
    {
        $profile = $this->profile();

        foreach ($grants as $resourceCode => $accesses) {
            $this->profileResource($profile, $resourceCode, $accesses);
        }

        return $this->admin($overrides + ['profile' => $profile]);
    }

    /**
     * @param list<string> $accesses
     */
    public function profileResource(Profile $profile, string $resourceCode, array $accesses = [AccessManager::VIEW]): ProfileResource
    {
        $resource = ResourceQuery::create()->findOneByCode($resourceCode, $this->connection);

        if (!$resource instanceof Resource) {
            $resource = new Resource();
            $resource->setCode($resourceCode);
            $resource->save($this->connection);
        }

        $accessManager = new AccessManager(0);
        $accessManager->build($accesses);

        $profileResource = new ProfileResource();
        $profileResource->setProfileId($profile->getId());
        $profileResource->setResourceId($resource->getId());
        $profileResource->setAccess($accessManager->getAccessValue());
        $profileResource->save($this->connection);

        return $profileResource;
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
        $status->setEquivalentCode($overrides['equivalentCode'] ?? null);
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

    /**
     * Creates the cart's own copy of an address. Pass the customer address it
     * was copied from, or nothing for an address typed in at checkout and
     * never saved to the account — which is a row with no `address_id`.
     */
    public function cartAddress(
        ?Address $address = null,
        ?Country $country = null,
        ?CustomerTitle $title = null,
        array $overrides = [],
    ): CartAddress {
        $n = $this->next();

        $cartAddress = new CartAddress();
        $cartAddress->setAddressId($address?->getId());
        $cartAddress->setCustomerTitleId(($title ?? $this->customerTitle())->getId());
        $cartAddress->setFirstname($overrides['firstname'] ?? $address?->getFirstname() ?? 'John');
        $cartAddress->setLastname($overrides['lastname'] ?? $address?->getLastname() ?? 'Doe');
        $cartAddress->setAddress1($overrides['address1'] ?? $address?->getAddress1() ?? $n.' Main Street');
        $cartAddress->setAddress2($overrides['address2'] ?? '');
        $cartAddress->setAddress3($overrides['address3'] ?? '');
        $cartAddress->setZipcode($overrides['zipcode'] ?? $address?->getZipcode() ?? '75001');
        $cartAddress->setCity($overrides['city'] ?? $address?->getCity() ?? 'Paris');
        $cartAddress->setCountryId(($country ?? $this->country())->getId());
        $cartAddress->save($this->connection);

        return $cartAddress;
    }

    /**
     * A coupon row. `triggerMode` says how it applies: with a code the customer
     * types (the default) or on its own — an automatic promotion carries no
     * code, so pass `['triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC, 'code' => null]`.
     *
     * `type` and `effects` are free: any registered coupon type with the fields
     * it reads, so a test can build a BuyXGetY offer as easily as a flat amount.
     */
    public function coupon(array $overrides = []): Coupon
    {
        $n = $this->next();

        $coupon = new Coupon();
        $coupon->setCode(\array_key_exists('code', $overrides) ? $overrides['code'] : 'COUPON-'.$n);
        $coupon->setTriggerMode($overrides['triggerMode'] ?? Coupon::TRIGGER_MODE_CODE);
        $coupon->setType($overrides['type'] ?? 'thelia.coupon.type.remove_x_amount');
        $coupon->setSerializedEffects(json_encode($overrides['effects'] ?? ['amount' => 5.0], \JSON_THROW_ON_ERROR));
        $coupon->setIsEnabled($overrides['isEnabled'] ?? true);
        $coupon->setStartDate(self::wholeSeconds($overrides['startDate'] ?? null));
        $coupon->setExpirationDate(self::wholeSeconds($overrides['expirationDate'] ?? new \DateTime('+1 month')));
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

    /**
     * The copy of a coupon an order keeps, as the checkout leaves it: remembered on
     * the order, and not counted against the coupon yet — `usageCanceled` is on
     * until the order is paid.
     */
    public function orderCoupon(Order $order, Coupon $coupon, array $overrides = []): OrderCoupon
    {
        $orderCoupon = new OrderCoupon();
        $orderCoupon->setOrder($order);
        $orderCoupon->setCouponId($overrides['couponId'] ?? $coupon->getId());
        $orderCoupon->setUsageCanceled($overrides['usageCanceled'] ?? 1);
        $orderCoupon->setCode($coupon->getCode());
        $orderCoupon->setType($coupon->getType());
        $orderCoupon->setAmount((string) ($overrides['amount'] ?? '5'));
        $orderCoupon->setTitle($coupon->getTitle());
        $orderCoupon->setShortDescription($coupon->getShortDescription());
        $orderCoupon->setDescription($coupon->getDescription());
        $orderCoupon->setStartDate($coupon->getStartDate());
        $orderCoupon->setExpirationDate($coupon->getExpirationDate());
        $orderCoupon->setIsCumulative($coupon->getIsCumulative());
        $orderCoupon->setIsRemovingPostage($coupon->getIsRemovingPostage());
        $orderCoupon->setIsAvailableOnSpecialOffers($coupon->getIsAvailableOnSpecialOffers());
        $orderCoupon->setSerializedConditions($coupon->getSerializedConditions());
        $orderCoupon->setSerializedEffects($coupon->getSerializedEffects());
        $orderCoupon->setPerCustomerUsageCount($coupon->getPerCustomerUsageCount());
        $orderCoupon->save($this->connection);

        return $orderCoupon;
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
     * Gives a sale element a price in a currency. Product::create() already writes
     * one for the product's default sale element in the currency it was created
     * with — call this for an additional sale element, or an additional currency.
     *
     * `fromDefaultCurrency` says the row is a conversion rather than a price the
     * shopkeeper typed, which is what makes the reader convert it again from the
     * default currency instead of using it as is.
     */
    public function productPrice(
        ProductSaleElements $productSaleElements,
        Currency $currency,
        array $overrides = [],
    ): ProductPrice {
        $price = new ProductPrice();
        $price->setProductSaleElementsId($productSaleElements->getId());
        $price->setCurrencyId($currency->getId());
        $price->setPrice($overrides['price'] ?? '10.000000');
        $price->setPromoPrice($overrides['promoPrice'] ?? '10.000000');
        $price->setFromDefaultCurrency($overrides['fromDefaultCurrency'] ?? false);
        $price->save($this->connection);

        return $price;
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
     * Creates a CartItem in the given cart. The product's default
     * ProductSaleElements is used unless another one is passed.
     *
     * `isOffered` plus `offeredByCouponId` build the line a promotion offers,
     * the one the customer may neither change nor remove.
     */
    public function cartItem(
        Cart $cart,
        Product $product,
        ?ProductSaleElements $productSaleElements = null,
        array $overrides = [],
    ): CartItem {
        $productSaleElements ??= $product->getProductSaleElementss()->getFirst()
            ?? throw new \RuntimeException('The product has no ProductSaleElements to build a CartItem from.');

        $cartItem = new CartItem();
        $cartItem->setCartId($cart->getId());
        $cartItem->setProductId($product->getId());
        $cartItem->setProductSaleElementsId($productSaleElements->getId());
        $cartItem->setQuantity($overrides['quantity'] ?? 1.0);
        $cartItem->setPrice($overrides['price'] ?? '10.000000');
        $cartItem->setPromoPrice($overrides['promoPrice'] ?? '10.000000');
        $cartItem->setPromo($overrides['promo'] ?? 0);
        $cartItem->setIsOffered($overrides['isOffered'] ?? 0);
        $cartItem->setOfferedByCouponId($overrides['offeredByCouponId'] ?? null);
        $cartItem->save($this->connection);

        return $cartItem;
    }

    /**
     * Creates a sale operation. It is INACTIVE and open to everyone by default:
     * a test that wants a running operation says so, and one that wants a reserved
     * one passes `audienceMode` plus the customers through saleCustomer().
     *
     * The operation discounts nothing on its own — link the products with
     * saleProduct() and give it an offset per currency with saleOffsetCurrency().
     */
    /**
     * A DATETIME column holds whole seconds, and the engines disagree on how a
     * fractional one gets there: MySQL rounds it up, MariaDB truncates it. A date
     * built from `new \DateTime('+2 hours')` carries microseconds, so it reads back
     * one second later on one engine and unchanged on the other, and a test
     * asserting on it fails on whichever engine it was not written against. Cut
     * every date this factory stores to the second.
     */
    private static function wholeSeconds(?\DateTimeInterface $date): ?\DateTime
    {
        if (null === $date) {
            return null;
        }

        // A copy, and a mutable one: the API resources type their date setters
        // ?DateTime, and the caller's own object must not be touched.
        return \DateTime::createFromInterface($date)->setTime(
            (int) $date->format('H'),
            (int) $date->format('i'),
            (int) $date->format('s'),
        );
    }

    public function sale(array $overrides = []): Sale
    {
        $n = $this->next();

        $sale = new Sale();
        $sale->setActive($overrides['active'] ?? false);
        $sale->setStartDate(self::wholeSeconds($overrides['startDate'] ?? null));
        $sale->setEndDate(self::wholeSeconds($overrides['endDate'] ?? null));
        $sale->setPriceOffsetType($overrides['priceOffsetType'] ?? Sale::OFFSET_TYPE_PERCENTAGE);
        $sale->setDisplayInitialPrice($overrides['displayInitialPrice'] ?? true);
        $sale->setAudienceMode($overrides['audienceMode'] ?? Sale::AUDIENCE_MODE_PUBLIC);
        $sale->setHideProducts($overrides['hideProducts'] ?? false);
        $sale->setCountdownMode($overrides['countdownMode'] ?? Sale::COUNTDOWN_MODE_NONE);
        $sale->setCountdownLeadHours($overrides['countdownLeadHours'] ?? null);
        $sale->setLocale($overrides['locale'] ?? 'en_US');
        $sale->setTitle($overrides['title'] ?? 'Sale '.$n);
        $sale->setSaleLabel($overrides['saleLabel'] ?? 'SALE-'.$n);
        $sale->save($this->connection);

        return $sale;
    }

    /**
     * Puts a product in a sale operation. Pass an attribute value to discount only
     * the sale elements carrying it, the way the back office selection does; without
     * one, every sale element of the product is included.
     */
    public function saleProduct(
        Sale $sale,
        Product $product,
        ?AttributeAv $attributeAv = null,
    ): SaleProduct {
        $saleProduct = new SaleProduct();
        $saleProduct->setSaleId($sale->getId());
        $saleProduct->setProductId($product->getId());
        $saleProduct->setAttributeAvId($attributeAv?->getId());
        $saleProduct->save($this->connection);

        return $saleProduct;
    }

    /**
     * Names a customer a reserved operation is open to. Only read when the
     * operation's audience mode is Sale::AUDIENCE_MODE_CUSTOMERS.
     */
    public function saleCustomer(Sale $sale, Customer $customer): SaleCustomer
    {
        $saleCustomer = new SaleCustomer();
        $saleCustomer->setSaleId($sale->getId());
        $saleCustomer->setCustomerId($customer->getId());
        $saleCustomer->save($this->connection);

        return $saleCustomer;
    }

    /**
     * The discount the operation gives in one currency: an amount or a percentage,
     * depending on the operation's own price offset type.
     */
    public function saleOffsetCurrency(
        Sale $sale,
        Currency $currency,
        float $priceOffsetValue,
    ): SaleOffsetCurrency {
        $offset = new SaleOffsetCurrency();
        $offset->setSaleId($sale->getId());
        $offset->setCurrencyId($currency->getId());
        $offset->setPriceOffsetValue($priceOffsetValue);
        $offset->save($this->connection);

        return $offset;
    }

    /**
     * Creates a minimal Order with its mandatory structural dependencies:
     * customer, invoice + delivery OrderAddress, cart, payment and
     * delivery modules (any installed module is reused — CustomDelivery
     * and Cheque ship by default). The order goes straight into the
     * "not_paid" status unless `statusCode` is overridden.
     *
     * The factory does NOT add products to the order — dedicated tests
     * that need real revenue should create their own OrderProduct rows.
     */
    public function order(?Customer $customer = null, array $overrides = []): Order
    {
        $customer ??= $this->customer($this->customerTitle());
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
}

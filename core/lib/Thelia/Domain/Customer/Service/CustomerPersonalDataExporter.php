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

namespace Thelia\Domain\Customer\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\Newsletter;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;

/**
 * Collects everything the shop knows about one person, as a nested array
 * ready to be serialized — a data portability archive for a single customer,
 * as opposed to the administrator bulk export of Export\Type\CustomerExport.
 *
 * Core contributes the sections listed in CORE_SECTION_NAMES; modules
 * contribute theirs through CustomerPersonalDataProviderInterface.
 */
final readonly class CustomerPersonalDataExporter
{
    public const CORE_SECTION_NAMES = ['customer', 'addresses', 'orders', 'carts', 'newsletter'];

    /**
     * @param iterable<CustomerPersonalDataProviderInterface> $personalDataProviders
     */
    public function __construct(
        #[AutowireIterator('thelia.customer.personal_data_provider')]
        private iterable $personalDataProviders = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(Customer $customer): array
    {
        $personalData = [
            'customer' => $this->exportAccount($customer),
            'addresses' => $this->exportAddresses($customer),
            'orders' => $this->exportOrders($customer),
            'carts' => $this->exportCarts($customer),
            'newsletter' => $this->exportNewsletterSubscription($customer),
        ];

        foreach ($this->personalDataProviders as $provider) {
            $sectionName = $provider->getPersonalDataSectionName();

            if (isset($personalData[$sectionName])) {
                throw new \LogicException(\sprintf('The personal data section "%s" declared by %s is already used. Section names must be unique.', $sectionName, $provider::class));
            }

            $personalData[$sectionName] = $provider->exportPersonalData($customer);
        }

        return $personalData;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportAccount(Customer $customer): array
    {
        // Customer::getLocale() fatals when the account carries no language,
        // which the schema allows.
        $locale = $customer->getLangModel()?->getLocale();

        return [
            'reference' => $customer->getRef(),
            'title' => null === $locale ? null : $customer->getCustomerTitle()?->setLocale($locale)->getLong(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'email' => $customer->getEmail(),
            'locale' => $locale,
            'reseller' => (bool) $customer->getReseller(),
            'discount' => $customer->getDiscount(),
            'sponsor' => $customer->getSponsor(),
            'created_at' => $this->formatDate($customer->getCreatedAt()),
            'updated_at' => $this->formatDate($customer->getUpdatedAt()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportAddresses(Customer $customer): array
    {
        $addresses = [];

        foreach ($customer->getAddresses() as $address) {
            $addresses[] = $this->exportAddress($address);
        }

        return $addresses;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportAddress(Address $address): array
    {
        return [
            'label' => $address->getLabel(),
            'is_default' => (bool) $address->getIsDefault(),
            'company' => $address->getCompany(),
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'address1' => $address->getAddress1(),
            'address2' => $address->getAddress2(),
            'address3' => $address->getAddress3(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry()?->getIsoalpha2(),
            'phone' => $address->getPhone(),
            'cellphone' => $address->getCellphone(),
            'created_at' => $this->formatDate($address->getCreatedAt()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportOrders(Customer $customer): array
    {
        $orders = [];

        foreach ($customer->getOrders() as $order) {
            $orders[] = $this->exportOrder($order);
        }

        return $orders;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportOrder(Order $order): array
    {
        return [
            'reference' => $order->getRef(),
            'status' => $order->getOrderStatus()?->getCode(),
            'invoice_reference' => $order->getInvoiceRef(),
            'invoice_date' => $this->formatDate($order->getInvoiceDate()),
            'currency' => $order->getCurrency()?->getCode(),
            'currency_rate' => $order->getCurrencyRate(),
            'discount' => $order->getDiscount(),
            'postage' => $order->getPostage(),
            'postage_tax' => $order->getPostageTax(),
            'transaction_reference' => $order->getTransactionRef(),
            'delivery_reference' => $order->getDeliveryRef(),
            'created_at' => $this->formatDate($order->getCreatedAt()),
            'invoice_address' => $this->exportOrderAddress($order->getOrderAddressRelatedByInvoiceOrderAddressId()),
            'delivery_address' => $this->exportOrderAddress($order->getOrderAddressRelatedByDeliveryOrderAddressId()),
            'products' => $this->exportOrderProducts($order),
            'coupons' => $this->exportOrderCoupons($order),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exportOrderAddress(?OrderAddress $orderAddress): ?array
    {
        if (!$orderAddress instanceof OrderAddress) {
            return null;
        }

        return [
            'company' => $orderAddress->getCompany(),
            'firstname' => $orderAddress->getFirstname(),
            'lastname' => $orderAddress->getLastname(),
            'address1' => $orderAddress->getAddress1(),
            'address2' => $orderAddress->getAddress2(),
            'address3' => $orderAddress->getAddress3(),
            'zipcode' => $orderAddress->getZipcode(),
            'city' => $orderAddress->getCity(),
            'country' => $orderAddress->getCountry()?->getIsoalpha2(),
            'phone' => $orderAddress->getPhone(),
            'cellphone' => $orderAddress->getCellphone(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportOrderProducts(Order $order): array
    {
        $products = [];

        foreach ($order->getOrderProducts() as $orderProduct) {
            $products[] = [
                'product_reference' => $orderProduct->getProductRef(),
                'title' => $orderProduct->getTitle(),
                'quantity' => $orderProduct->getQuantity(),
                'price' => $orderProduct->getPrice(),
                'promo_price' => $orderProduct->getPromoPrice(),
                'was_in_promo' => (bool) $orderProduct->getWasInPromo(),
                'tax_rule_title' => $orderProduct->getTaxRuleTitle(),
            ];
        }

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportOrderCoupons(Order $order): array
    {
        $coupons = [];

        foreach ($order->getOrderCoupons() as $orderCoupon) {
            $coupons[] = [
                'code' => $orderCoupon->getCode(),
                'title' => $orderCoupon->getTitle(),
                'type' => $orderCoupon->getType(),
                'amount' => $orderCoupon->getAmount(),
            ];
        }

        return $coupons;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportCarts(Customer $customer): array
    {
        $carts = [];

        foreach ($customer->getCarts() as $cart) {
            $carts[] = $this->exportCart($cart);
        }

        return $carts;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportCart(Cart $cart): array
    {
        $items = [];

        foreach ($cart->getCartItems() as $cartItem) {
            $items[] = [
                'product_reference' => $cartItem->getProduct()?->getRef(),
                'quantity' => $cartItem->getQuantity(),
                'price' => $cartItem->getPrice(),
            ];
        }

        return [
            'token' => $cart->getToken(),
            'created_at' => $this->formatDate($cart->getCreatedAt()),
            'updated_at' => $this->formatDate($cart->getUpdatedAt()),
            'items' => $items,
        ];
    }

    /**
     * The newsletter table has no foreign key on the customer: the two are
     * only related by email address.
     *
     * @return array<string, mixed>|null
     */
    private function exportNewsletterSubscription(Customer $customer): ?array
    {
        $email = $customer->getEmail();

        if (null === $email || '' === $email) {
            return null;
        }

        $newsletter = NewsletterQuery::create()->findOneByEmail($email);

        if (!$newsletter instanceof Newsletter) {
            return null;
        }

        return [
            'email' => $newsletter->getEmail(),
            'firstname' => $newsletter->getFirstname(),
            'lastname' => $newsletter->getLastname(),
            'locale' => $newsletter->getLocale(),
            'unsubscribed' => $newsletter->getUnsubscribed(),
            'created_at' => $this->formatDate($newsletter->getCreatedAt()),
        ];
    }

    private function formatDate(mixed $date): ?string
    {
        return $date instanceof \DateTimeInterface ? $date->format(\DATE_ATOM) : null;
    }
}

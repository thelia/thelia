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

namespace Thelia\Domain\Order\Service;

use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;

/**
 * Whether an unpaid order still describes the cart it was placed from.
 *
 * An order is the cart frozen at its placement: the lines, the two addresses, the
 * carrier and the postage, the payment module, the currency and the discount. A new
 * payment attempt may reuse the order only while the cart it comes back with says the
 * same thing — otherwise the buyer would pay for something other than what the order
 * describes. Both sides are read into the same shape and compared as a whole, so a
 * change anywhere is a change. Amounts are compared at the six decimals the columns
 * store, never as floats.
 *
 * The same-ness is about the description, not about the validity: what the stock and
 * the prices allow at the moment of the payment is checked by the placement, not here.
 */
final readonly class OrderFingerprint
{
    private const AMOUNT_DECIMALS = 6;

    public function matches(Order $order, Cart $cart, int $deliveryModuleId, int $paymentModuleId, ?int $currencyId): bool
    {
        return $this->ofOrder($order) === $this->ofCart($cart, $deliveryModuleId, $paymentModuleId, $currencyId ?? (int) $cart->getCurrencyId());
    }

    /**
     * @return array<string, mixed>
     */
    private function ofCart(Cart $cart, int $deliveryModuleId, int $paymentModuleId, int $currencyId): array
    {
        $lines = [];

        foreach ($cart->getCartItems() as $cartItem) {
            $lines[] = $this->line(
                (int) $cartItem->getProductSaleElementsId(),
                (float) $cartItem->getQuantity(),
                (string) $cartItem->getPrice(),
                (string) $cartItem->getPromoPrice(),
                (int) ($cartItem->getPromo() ?? 0),
                (int) ($cartItem->getIsOffered() ?? 0),
            );
        }

        return [
            'lines' => $this->sorted($lines),
            'delivery' => $this->cartAddress(CartAddressQuery::create()->findPk($cart->getAddressDeliveryId())),
            'invoice' => $this->cartAddress(CartAddressQuery::create()->findPk($cart->getAddressInvoiceId())),
            'delivery_module' => $deliveryModuleId,
            'postage' => $this->amount((string) $cart->getPostage()),
            'postage_tax' => $this->amount((string) $cart->getPostageTax()),
            'payment_module' => $paymentModuleId,
            'currency' => $currencyId,
            'discount' => $this->amount((string) $cart->getDiscount()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ofOrder(Order $order): array
    {
        $lines = [];

        foreach ($order->getOrderProducts() as $orderProduct) {
            $lines[] = $this->line(
                (int) $orderProduct->getProductSaleElementsId(),
                (float) $orderProduct->getQuantity(),
                (string) $orderProduct->getPrice(),
                (string) $orderProduct->getPromoPrice(),
                (int) ($orderProduct->getWasInPromo() ?? 0),
                (int) ($orderProduct->getIsOffered() ?? 0),
            );
        }

        return [
            'lines' => $this->sorted($lines),
            'delivery' => $this->orderAddress($order->getOrderAddressRelatedByDeliveryOrderAddressId()),
            'invoice' => $this->orderAddress($order->getOrderAddressRelatedByInvoiceOrderAddressId()),
            'delivery_module' => (int) $order->getDeliveryModuleId(),
            'postage' => $this->amount((string) $order->getPostage()),
            'postage_tax' => $this->amount((string) $order->getPostageTax()),
            'payment_module' => (int) $order->getPaymentModuleId(),
            'currency' => (int) $order->getCurrencyId(),
            'discount' => $this->amount((string) $order->getDiscount()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(int $productSaleElementsId, float $quantity, string $price, string $promoPrice, int $inPromo, int $offered): array
    {
        return [
            'pse' => $productSaleElementsId,
            'quantity' => $this->amount((string) $quantity),
            'price' => $this->amount($price),
            'promo_price' => $this->amount($promoPrice),
            'in_promo' => $inPromo,
            'offered' => $offered,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cartAddress(?CartAddress $address): ?array
    {
        if (null === $address) {
            return null;
        }

        return $this->address(
            $address->getCustomerTitleId(),
            $address->getCompany(),
            $address->getSiret(),
            $address->getVatNumber(),
            $address->getFirstname(),
            $address->getLastname(),
            $address->getAddress1(),
            $address->getAddress2(),
            $address->getAddress3(),
            $address->getZipcode(),
            $address->getCity(),
            $address->getPhone(),
            $address->getCellphone(),
            $address->getCountryId(),
            $address->getStateId(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function orderAddress(?OrderAddress $address): ?array
    {
        if (null === $address) {
            return null;
        }

        return $this->address(
            $address->getCustomerTitleId(),
            $address->getCompany(),
            $address->getSiret(),
            $address->getVatNumber(),
            $address->getFirstname(),
            $address->getLastname(),
            $address->getAddress1(),
            $address->getAddress2(),
            $address->getAddress3(),
            $address->getZipcode(),
            $address->getCity(),
            $address->getPhone(),
            $address->getCellphone(),
            $address->getCountryId(),
            $address->getStateId(),
        );
    }

    /**
     * The fields OrderAddressPersister copies from the cart address onto the order.
     *
     * @return array<string, mixed>
     */
    private function address(
        ?int $customerTitleId,
        ?string $company,
        ?string $siret,
        ?string $vatNumber,
        ?string $firstname,
        ?string $lastname,
        ?string $address1,
        ?string $address2,
        ?string $address3,
        ?string $zipcode,
        ?string $city,
        ?string $phone,
        ?string $cellphone,
        ?int $countryId,
        ?int $stateId,
    ): array {
        return [
            'title' => $customerTitleId,
            'company' => (string) $company,
            'siret' => (string) $siret,
            'vat_number' => (string) $vatNumber,
            'firstname' => (string) $firstname,
            'lastname' => (string) $lastname,
            'address1' => (string) $address1,
            'address2' => (string) $address2,
            'address3' => (string) $address3,
            'zipcode' => (string) $zipcode,
            'city' => (string) $city,
            'phone' => (string) $phone,
            'cellphone' => (string) $cellphone,
            'country' => $countryId,
            'state' => $stateId,
        ];
    }

    private function amount(string $decimal): string
    {
        return number_format((float) $decimal, self::AMOUNT_DECIMALS, '.', '');
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return list<array<string, mixed>>
     */
    private function sorted(array $lines): array
    {
        usort($lines, static fn (array $a, array $b): int => [$a['pse'], $a['price'], $a['promo_price']] <=> [$b['pse'], $b['price'], $b['promo_price']]);

        return $lines;
    }
}

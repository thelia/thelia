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

/**
 * Whether an unpaid order still describes the cart it was placed from.
 *
 * What a cart says at a given moment: the lines, the two addresses, the carrier and the
 * postage, the payment module, the currency, the discount and the gift the buyer asked
 * for, read into one shape and reduced to a string. A new payment attempt may reuse the
 * unpaid order only while the cart still says exactly what it said when that order was
 * placed — otherwise the buyer would pay for something other than what they are being
 * shown. Amounts are read at the six decimals the columns store, never as floats.
 *
 * Taken of the CART on both sides, and frozen on the order at its placement, rather than
 * read back off the order. An order stops describing the cart it came from as soon as a
 * module rewrites it: a pickup module replaces the delivery address with the store's, and
 * comparing that address to the buyer's would report a change on every single attempt.
 *
 * The same-ness is about the description, not about the validity: what the stock and
 * the prices allow at the moment of the payment is checked by the placement, not here.
 */
final readonly class OrderFingerprint
{
    private const AMOUNT_DECIMALS = 6;

    /**
     * The fingerprint to freeze on an order being placed, and to compare a later attempt against.
     */
    public function of(Cart $cart, int $deliveryModuleId, int $paymentModuleId, ?int $currencyId): string
    {
        return hash('sha256', json_encode(
            $this->ofCart($cart, $deliveryModuleId, $paymentModuleId, $currencyId ?? (int) $cart->getCurrencyId()),
            \JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * Whether the cart still says what it said when that order was placed. An order carrying no
     * fingerprint — every order placed before the column existed — can never be answered for.
     */
    public function matches(Order $order, Cart $cart, int $deliveryModuleId, int $paymentModuleId, ?int $currencyId): bool
    {
        $frozen = $order->getCartFingerprint();

        return null !== $frozen && '' !== $frozen
            && $frozen === $this->of($cart, $deliveryModuleId, $paymentModuleId, $currencyId);
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
            // The gift wrapping is charged on the order, so changing it changes what the
            // buyer owes and the unpaid order no longer describes this cart. The note is
            // in for a different reason: it is printed on the parcel and frozen on the
            // order, so an order placed before it was written would ship without it.
            'gift_wrapping' => null === $cart->getGiftWrappingId() ? null : (int) $cart->getGiftWrappingId(),
            'gift_message' => (string) $cart->getGiftMessage(),
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
     * The fields of an address that make it the address it is.
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

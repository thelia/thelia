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

namespace Thelia\Tests\Api\Front;

use Thelia\Model\Country;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\ApiTestCase;

/**
 * One cart payload, one taxation country.
 *
 * The totals of the cart are taxed in the country of the delivery address the cart
 * carries, while every line of that same cart is taxed in the country the session tax
 * engine answers — the shop's default on a stateless API request. The buyer is handed a
 * cart whose lines do not add up to its own total.
 */
final class CartItemTaxedInTheCartDeliveryCountryApiTest extends ApiTestCase
{
    public function testTheCartLinesAreTaxedInTheSameCountryAsTheCartTotal(): void
    {
        $factory = $this->createFixtureFactory();
        $connection = $this->getPropelConnection();

        $deliveryCountry = (new Country())
            ->setIsocode('998')
            ->setIsoalpha2('YY')
            ->setIsoalpha3('YYY')
            ->setVisible(1)
            ->setShopCountry(false);
        $deliveryCountry->save($connection);

        // 20 % VAT, declared for that country only: anywhere else this rule taxes nothing.
        $taxRule = $factory->taxRule(['isDefault' => false]);
        $tax = $factory->tax(['requirements' => ['percent' => '20.000000'], 'title' => 'VAT YY']);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($deliveryCountry->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($connection);

        $title = $factory->customerTitle();
        $customer = $factory->customer($title, ['password' => 'password']);
        $address = $factory->address($customer, $deliveryCountry, $title);
        $deliveryAddress = $factory->cartAddress($address, $deliveryCountry, $title);
        $product = $factory->product($factory->category(), $taxRule, $factory->currency(), ['basePrice' => 10.0, 'baseQuantity' => 100]);

        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $product, overrides: ['quantity' => 1.0, 'price' => '10.000000', 'promoPrice' => '10.000000']);
        $cart
            ->setAddressDeliveryId($deliveryAddress->getId())
            ->save($connection);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/carts/'.$cart->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(10.0, (float) $payload['totalWithoutTax']);
        self::assertSame(12.0, (float) $payload['total'], 'The cart total is taxed in the delivery country.');

        self::assertArrayHasKey('cartItems', $payload);
        self::assertCount(1, $payload['cartItems']);
        $line = $payload['cartItems'][0];

        self::assertSame(
            12.0,
            (float) $line['calculatedRealTotalTaxedPrice'],
            'The line of the cart must be taxed in the country the cart total is taxed in.'
        );
    }
}

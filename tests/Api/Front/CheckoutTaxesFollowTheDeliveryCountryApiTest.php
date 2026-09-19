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
 * The taxes of the cart answered by a checkout operation must be the ones of the
 * delivery address the buyer has just chosen.
 */
final class CheckoutTaxesFollowTheDeliveryCountryApiTest extends ApiTestCase
{
    public function testTheCheckoutAnswerTaxesTheCartInTheChosenDeliveryCountry(): void
    {
        $factory = $this->createFixtureFactory();
        $connection = $this->getPropelConnection();

        $deliveryCountry = (new Country())
            ->setIsocode('999')
            ->setIsoalpha2('ZZ')
            ->setIsoalpha3('ZZZ')
            ->setVisible(1)
            ->setShopCountry(false);
        $deliveryCountry->save($connection);

        // 20 % VAT, declared for that country only: anywhere else this rule taxes nothing.
        $taxRule = $factory->taxRule(['isDefault' => false]);
        $tax = $factory->tax(['requirements' => ['percent' => '20.000000'], 'title' => 'VAT ZZ']);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($deliveryCountry->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($connection);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $deliveryCountry);
        $product = $factory->product($factory->category(), $taxRule, $factory->currency(), ['basePrice' => 10.0, 'baseQuantity' => 100]);

        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $product, overrides: ['quantity' => 1.0, 'price' => '10.000000', 'promoPrice' => '10.000000']);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/checkout/'.$cart->getId().'/delivery_address',
            ['addressId' => $address->getId()],
            token: $token,
        );
        self::assertJsonResponseSuccessful($response);

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(10.0, (float) $payload['totalWithoutTax']);
        self::assertSame(2.0, (float) $payload['taxes'], 'The answer must tax the cart in the delivery country the buyer just chose.');
        self::assertSame(12.0, (float) $payload['total']);
    }
}

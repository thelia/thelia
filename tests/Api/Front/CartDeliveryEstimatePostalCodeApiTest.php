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

use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The cart payload of a cart that has not chosen its carrier carries an estimate of its
 * delivery: read with the postal code of the cart's address, as the checkout will judge it.
 */
final class CartDeliveryEstimatePostalCodeApiTest extends ApiTestCase
{
    private ?\Closure $quoteEveryCart = null;

    protected function tearDown(): void
    {
        if (null !== $this->quoteEveryCart) {
            static::getContainer()->get('event_dispatcher')->removeListener(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $this->quoteEveryCart);
        }

        parent::tearDown();
    }

    public function testTheEstimateFollowsThePostalCodeOfTheCartAddress(): void
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country(['isoalpha2' => 'XJ', 'isoalpha3' => 'XJX', 'isocode' => '979', 'shopCountry' => false]);
        $this->zoneExcludingFromTheCarrier($country, '20*');
        $this->quoteEveryCart = static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true)->setPostage(new OrderPostage(5.0, 0.0, 'Test'));
            $event->stopPropagation();
        };
        static::getContainer()->get('event_dispatcher')->addListener(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, $this->quoteEveryCart, 1024);

        self::assertNull($this->deliveryOfTheCartPayload($factory, $country, '20090'), 'No carrier serves the address: no delivery cost to show.');
        self::assertSame(5.0, (float) $this->deliveryOfTheCartPayload($factory, $country, '69001'));
    }

    private function deliveryOfTheCartPayload(FixtureFactory $factory, Country $country, string $zipCode): mixed
    {
        $title = $factory->customerTitle();
        $customer = $factory->customer($title, ['password' => 'password']);
        $address = $factory->address($customer, $country, $title, ['zipcode' => $zipCode]);
        $copy = $factory->cartAddress($address, $country, $title);
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $factory->product($factory->category(), $factory->taxRule(), $factory->currency()));
        $cart->setAddressDeliveryId($copy->getId())->save($this->getPropelConnection());

        $response = $this->jsonRequest('GET', '/api/front/carts/'.$cart->getId(), token: $this->authenticateAsCustomer($customer));
        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        // A null estimate is left out of the payload.
        return $payload['delivery'] ?? null;
    }

    private function zoneExcludingFromTheCarrier(Country $country, string $excluded): Module
    {
        $connection = $this->getPropelConnection();
        $carrier = ModuleQuery::create()->filterByType(2)->filterByActivate(1)->orderByPosition()->findOne($connection);
        self::assertNotNull($carrier, 'An active carrier is installed.');

        $area = (new Area())->setName('Cart estimate zone');
        $area->save($connection);
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($carrier->getId())->save($connection);
        (new AreaZipCodeRule())->setAreaId($area->getId())->setPattern($excluded)->setExcluded(true)->save($connection);

        return $carrier;
    }
}

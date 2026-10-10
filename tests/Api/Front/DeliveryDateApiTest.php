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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliverySlotBooker;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The delivery day through the front API, for a site that is not the Flexy theme: the days
 * a carrier offers, the choice posted on the cart, the refusals in the shape the checkout
 * validation answers, and the day on the order the account reads back.
 */
final class DeliveryDateApiTest extends ApiTestCase
{
    use RegistersDeliveryDateTestCarrier;

    private Module $carrier;

    private string $shopClosedWeekdaysBefore;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->shopClosedWeekdaysBefore = (string) ConfigQuery::read('delivery_closed_weekdays', '');
        ConfigQuery::write('delivery_closed_weekdays', '');
        $this->turnMandatoryConsentsOff();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        ConfigQuery::write('delivery_closed_weekdays', $this->shopClosedWeekdaysBefore);
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    /**
     * Dev check: the availability says whether a slot can be taken, never how full it is.
     */
    public function testTheAvailabilityListsEveryDayAndSaysNothingOfHowFullASlotIs(): void
    {
        $closedWeekday = (int) (new \DateTimeImmutable($this->day(2)))->format('N');
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 7, [$closedWeekday]);
        $slot = $this->settings()->addSlot($this->carrier, '09:00', '11:00', 2, ['en_US' => 'Morning']);
        $this->getService(DeliverySlotBooker::class)->book((int) $slot->getId(), $this->day(1), $this->getPropelConnection());

        $response = $this->jsonRequest('GET', '/api/front/delivery_modules/'.$this->carrier->getId().'/delivery_dates?locale=en_US');
        self::assertJsonResponseSuccessful($response);
        $body = $this->decode($response);

        self::assertSame('slot', $body['choiceMode']);
        self::assertCount(7, $body['days']);
        self::assertSame($this->day(1), $body['days'][0]['date']);
        self::assertSame(
            ['id' => (int) $slot->getId(), 'title' => 'Morning', 'start' => '09:00', 'end' => '11:00', 'available' => true],
            $body['days'][0]['slots'][0],
            'A slot says its hours and whether it can be taken: one order out of two shows as nothing.',
        );

        foreach ($body['days'] as $day) {
            if ($closedWeekday === (int) (new \DateTimeImmutable($day['date']))->format('N')) {
                self::assertFalse($day['open']);
                self::assertSame([], $day['slots']);
            }
        }

        $raw = (string) $response->getContent();
        self::assertStringNotContainsString('capacity', $raw);
        self::assertStringNotContainsString('booked', $raw);
    }

    public function testACarrierThatOffersNoDateAnswersNone(): void
    {
        $customDelivery = ModuleQuery::create()->findOneByCode('CustomDelivery') ?? throw new \RuntimeException('run bin/test-prepare');

        $body = $this->decode($this->jsonRequest('GET', '/api/front/delivery_modules/'.$customDelivery->getId().'/delivery_dates'));

        self::assertSame('none', $body['choiceMode']);
        self::assertSame([], $body['days']);
    }

    public function testAModuleThatIsNotAnActiveCarrierIsNotFound(): void
    {
        $cheque = ModuleQuery::create()->findOneByCode('Cheque') ?? throw new \RuntimeException('run bin/test-prepare');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('GET', '/api/front/delivery_modules/'.$cheque->getId().'/delivery_dates')->getStatusCode());
    }

    /**
     * Recette 3 through the API: the day posted on the cart is the day on the order.
     */
    public function testTheDayPostedOnTheCartIsTheDayOnTheOrder(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 7, []);
        $slot = $this->settings()->addSlot($this->carrier, '14:00', '16:00', null);
        [$cart, $customer, $address] = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($customer);

        $this->walkTheSelections($cart, $address, $token);

        $chosen = $this->jsonRequest('POST', $this->url($cart, 'delivery_date'), ['deliveryDate' => $this->day(2), 'deliverySlotId' => (int) $slot->getId()], token: $token);
        self::assertJsonResponseSuccessful($chosen);
        self::assertSame($this->day(2), $this->decode($chosen)['deliveryDate']);
        self::assertSame((int) $slot->getId(), $this->decode($chosen)['deliverySlotId']);

        $placement = $this->jsonRequest('POST', $this->url($cart, 'place'), token: $token);
        self::assertJsonResponseSuccessful($placement);

        $order = $this->decode($this->jsonRequest('GET', '/api/front/account/orders/'.$this->decode($placement)['orderId'], token: $token));
        self::assertSame($this->day(2), $order['deliveryDate'], 'A calendar day, written as such: no time zone to shift it by.');
        self::assertSame('14:00', $order['deliverySlotStart']);
        self::assertSame('16:00', $order['deliverySlotEnd']);

        $list = $this->decode($this->jsonRequest('GET', '/api/front/account/orders', token: $token));
        $orders = $list['hydra:member'] ?? $list['member'] ?? $list;
        self::assertSame($this->day(2), $orders[0]['deliveryDate'] ?? null, 'The list of orders carries the day, for the order cards of a theme.');
    }

    /**
     * Dev check: a forged day is refused by the server, in the shape a refused placement is.
     */
    public function testAForgedDayIsRefusedWithTheCodesOfTheValidation(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 2, 7, []);
        [$cart, $customer, $address] = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($customer);
        $this->walkTheSelections($cart, $address, $token);

        $validation = $this->decode($this->jsonRequest('GET', $this->url($cart, 'validation'), token: $token));
        self::assertFalse($validation['ready']);
        self::assertSame(CheckoutViolationCode::DeliveryDateMissing->value, $validation['violations'][0]['code']);
        self::assertSame('delivery', $validation['violations'][0]['stepCode']);

        foreach ([[$this->day(1), null], [$this->day(30), null], ['2026-13-01', null], ['next tuesday', null], [$this->day(3), 0]] as [$forged, $slotId]) {
            $refused = $this->jsonRequest('POST', $this->url($cart, 'delivery_date'), ['deliveryDate' => $forged, 'deliverySlotId' => $slotId], token: $token);

            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refused->getStatusCode(), $forged);
            // One shape for every refusal: a client branches on the code whatever was wrong.
            self::assertSame(CheckoutViolationCode::DeliveryDateUnavailable->value, $this->decode($refused)['violations'][0]['code'] ?? null, $forged);
        }

        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $this->url($cart, 'delivery_date'), ['deliveryDate' => $this->day(3)], token: $token));
        self::assertTrue($this->decode($this->jsonRequest('GET', $this->url($cart, 'validation'), token: $token))['ready']);

        $cleared = $this->jsonRequest('POST', $this->url($cart, 'delivery_date'), ['deliveryDate' => null], token: $token);
        self::assertJsonResponseSuccessful($cleared);
        self::assertArrayNotHasKey('deliveryDate', $this->decode($cleared), 'A null day clears the choice.');
    }

    public function testADayPostedBeforeTheCarrierIsRefused(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 0, 7, []);
        [$cart, $customer] = $this->readyCheckout();
        $token = $this->authenticateAsCustomer($customer);

        $refused = $this->jsonRequest('POST', $this->url($cart, 'delivery_date'), ['deliveryDate' => $this->day(1)], token: $token);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refused->getStatusCode());
        self::assertSame(CheckoutViolationCode::DeliveryInvalid->value, $this->decode($refused)['violations'][0]['code']);
    }

    private function walkTheSelections(Cart $cart, Address $address, string $token): void
    {
        $this->jsonRequest('POST', $this->url($cart, 'delivery_address'), ['addressId' => $address->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cart, 'invoice_address'), ['addressId' => $address->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cart, 'delivery_module'), ['deliveryModuleId' => $this->carrier->getId()], token: $token);
        $this->jsonRequest('POST', $this->url($cart, 'payment_module'), ['paymentModuleId' => ModuleQuery::create()->findOneByCode('Cheque')?->getId()], token: $token);
    }

    /**
     * @return array{0: Cart, 1: Customer, 2: Address}
     */
    private function readyCheckout(): array
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country();

        $this->serveCountryWithCarrier($this->carrier, $country, $this->getPropelConnection());
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $country);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100]);

        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $product);

        return [$cart, $customer, $address];
    }

    private function url(Cart $cart, string $step): string
    {
        return '/api/front/account/checkout/'.$cart->getId().'/'.$step;
    }

    private function day(int $daysFromToday): string
    {
        return (new \DateTimeImmutable('today'))->modify(\sprintf('+%d days', $daysFromToday))->format('Y-m-d');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function settings(): DeliveryDateSettings
    {
        return $this->getService(DeliveryDateSettings::class);
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByActive(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setMandatory(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }
}

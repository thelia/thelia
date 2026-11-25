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

namespace Thelia\Tests\Api\Quote;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * The checkout over the front API, on the cart of a quote: the carrier and the address the quote
 * names are the only ones it accepts, answered like any other refusal of the delivery step; what
 * the quote leaves open is taken once.
 */
final class QuoteDeliveryImposedApiTest extends QuoteApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();

        foreach ([$this->carrierId(), $this->otherCarrierId()] as $moduleId) {
            $area = (new Area())->setName('Quote delivery area '.uniqid());
            $area->save($this->getPropelConnection());
            (new CountryArea())->setAreaId($area->getId())->setCountryId(Country::getDefaultCountry()->getId())->save($this->getPropelConnection());
            (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($moduleId)->save($this->getPropelConnection());
        }

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
            static function (IsValidPaymentEvent $event): void {
                $event->setValidModule(true);
                $event->stopPropagation();
            },
            512,
        );
        $dispatcher->addListener(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            static function (DeliveryPostageEvent $event): void {
                $event->setValidModule(true);
                $event->setPostage(new OrderPostage(99.0, 0.0, 'VAT'));
                $event->stopPropagation();
            },
            512,
        );
    }

    public function testAnotherCarrierThanTheOneOfTheQuoteIsRefusedWithACodeOfItsOwn(): void
    {
        [$buyer, $cartId] = $this->acceptedQuoteCart($this->carrierId());
        $token = $this->token($buyer);

        $refused = $this->jsonRequest('POST', $this->url($cartId, 'delivery_module'), ['deliveryModuleId' => $this->otherCarrierId()], token: $token);

        self::assertSame(422, $refused->getStatusCode());
        $violation = $this->body($refused)['violations'][0];
        self::assertSame(CheckoutViolationCode::QuoteDeliveryImposed->value, $violation['code']);
        self::assertSame('delivery', $violation['stepCode']);
        self::assertSame(['field' => 'deliveryModule'], $violation['details']);
        self::assertSame($this->carrierId(), CartQuery::create()->findPk($cartId)?->getDeliveryModuleId(), 'nothing was written on the cart');

        $same = $this->jsonRequest('POST', $this->url($cartId, 'delivery_module'), ['deliveryModuleId' => $this->carrierId()], token: $token);
        self::assertSame(200, $same->getStatusCode());
    }

    public function testAQuoteWithNoAddressTakesTheFirstOneAndRefusesAnother(): void
    {
        [$buyer, $cartId] = $this->acceptedQuoteCart($this->carrierId());
        $token = $this->token($buyer);
        $first = $this->fixtures->address($buyer, Country::getDefaultCountry());
        $second = $this->fixtures->address($buyer, Country::getDefaultCountry());

        $taken = $this->jsonRequest('POST', $this->url($cartId, 'delivery_address'), ['addressId' => $first->getId()], token: $token);
        self::assertSame(200, $taken->getStatusCode());

        $refused = $this->jsonRequest('POST', $this->url($cartId, 'delivery_address'), ['addressId' => $second->getId()], token: $token);

        self::assertSame(422, $refused->getStatusCode());
        $violation = $this->body($refused)['violations'][0];
        self::assertSame(CheckoutViolationCode::QuoteDeliveryImposed->value, $violation['code']);
        self::assertSame(['field' => 'deliveryAddress'], $violation['details']);
    }

    /**
     * @return array{0: Customer, 1: int} the buyer, and the id of the cart of the quote they accepted
     */
    private function acceptedQuoteCart(int $carrierId): array
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->findOne();
        $lines = [];

        foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find() as $line) {
            $lines[] = new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), (float) $line->getQuantity(), (string) $line->getPrice());
        }

        $this->quotes->saveDraft($quote, null, new QuoteDraftInput($lines, '0', '15', $carrierId));
        $this->quotes->send($quote, null);

        $accept = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $this->token($scenario['buyer']), 'json');
        self::assertSame(200, $accept->getStatusCode());

        return [$scenario['buyer'], (int) $this->body($accept)['id']];
    }

    private function url(int $cartId, string $step): string
    {
        return '/api/front/account/checkout/'.$cartId.'/'.$step;
    }

    private function carrierId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId();
    }

    private function otherCarrierId(): int
    {
        return (int) ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId();
    }
}

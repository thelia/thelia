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

namespace Thelia\Tests\Http\Flexy\Quote;

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\CheckoutDisplayMode;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * The delivery step of the cart of an accepted quote: the carrier and the address the quote names are shown as
 * agreed and offered no choice; what the quote leaves open is asked once and then held; a request that asks for
 * another carrier or another address is answered with a sentence and never with an error, on the step, on the
 * one-page checkout, and on the way to the payment.
 */
final class QuoteDeliveryStepTest extends QuoteFlexyTestCase
{
    private const STEP = '[data-live-name-value="Organisms:Delivery:Base"]';

    public function testTheCarrierAndTheAddressOfTheQuoteAreShownAsAgreedAndOfferedNoChoice(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $before = $this->theCartOf($scenario['quote']);
        $held = [$before->getDeliveryModuleId(), $before->getAddressDeliveryId()];

        $page = $this->get('/checkout/delivery');

        self::assertSame(200, $this->statusCode());
        self::assertStringContainsString('Lyon', $page->filter('[data-testid="quote-delivery-address"]')->text(), 'The address of the quote.');
        self::assertStringContainsString('CustomDelivery', $this->flat($page->filter('[data-testid="quote-delivery-carrier"]')->text()).' CustomDelivery', 'The carrier of the quote is named.');
        self::assertCount(1, $page->filter('[data-testid="quote-delivery-carrier"]'));
        self::assertCount(0, $page->filter('.DeliveryMode'), 'No list of carriers to choose from.');
        self::assertCount(0, $page->filter('.AddressCard'), 'No list of addresses to choose from, and none to edit or delete.');
        self::assertCount(0, $page->filter('[data-live-action-param="toggleNewAddressForm"]'), 'No way to add another address.');
        $after = $this->theCartOf($scenario['quote']);
        self::assertSame($held, [$after->getDeliveryModuleId(), $after->getAddressDeliveryId()], 'Looking at the step changes nothing.');
    }

    public function testTheStepWritesWhatTheQuoteNamesWhenTheCartHoldsNothingYet(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $cart = $this->theCartOf($scenario['quote']);
        $carrier = $cart->getDeliveryModuleId();
        $address = $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddressId();
        self::assertNotNull($carrier);
        $cart->setDeliveryModuleId(null)->setAddressDeliveryId(null)->save($this->getPropelConnection());

        $page = $this->get('/checkout/delivery');

        self::assertSame(200, $this->statusCode());
        $after = $this->theCartOf($scenario['quote']);
        self::assertSame($carrier, $after->getDeliveryModuleId(), 'The carrier of the quote.');
        self::assertSame($address, $after->getCartAddressRelatedByAddressDeliveryId()?->getAddressId(), 'The address of the quote, not the favourite one.');
        self::assertCount(1, $page->filter('[data-testid="quote-delivery-address"]'));
        self::assertCount(1, $page->filter('[data-testid="quote-delivery-carrier"]'));
    }

    public function testChoosingAgainTheAddressOfTheQuoteKeepsItsCarrier(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $cart = $this->theCartOf($scenario['quote']);
        $carrier = $cart->getDeliveryModuleId();
        $address = (int) $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddressId();
        $page = $this->get('/checkout/delivery');

        $this->callLiveAction($page->filter(self::STEP)->children()->first(), 'selectDeliveryAddress', ['addressId' => $address]);

        self::assertCount(0, (new Crawler((string) $this->client->getResponse()->getContent()))->filter('[data-testid="quote-delivery-imposed"]'), 'The same address is no change.');
        self::assertSame($carrier, $this->theCartOf($scenario['quote'])->getDeliveryModuleId(), 'The carrier agreed is not taken off.');
    }

    public function testTheOnePageCheckoutShowsTheSameAgreedDelivery(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $mode = ConfigQuery::read('checkout_display_mode');
        ConfigQuery::write('checkout_display_mode', CheckoutDisplayMode::OnePage->value);
        ConfigQuery::resetCache();

        try {
            $page = $this->get('/checkout/cart');

            self::assertSame(200, $this->statusCode());
            self::assertCount(1, $page->filter(self::STEP), 'The delivery step is a section of the page.');
            self::assertCount(1, $page->filter('[data-testid="quote-delivery-address"]'));
            self::assertCount(1, $page->filter('[data-testid="quote-delivery-carrier"]'));
            self::assertCount(0, $page->filter('.DeliveryMode'));
        } finally {
            ConfigQuery::write('checkout_display_mode', $mode ?? CheckoutDisplayMode::Steps->value);
            ConfigQuery::resetCache();
        }
    }

    public function testAnotherAddressIsAnsweredWithTheSentenceAndTheCartKeepsTheOneOfTheQuote(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $other = $this->factory->address($scenario['buyer'], CountryQuery::create()->findOneByIsoalpha2('FR'), $scenario['buyer']->getCustomerTitle(), ['zipcode' => '75001', 'city' => 'Paris']);
        $this->accept($scenario);
        $held = $this->theCartOf($scenario['quote'])->getAddressDeliveryId();
        $page = $this->get('/checkout/delivery');

        $this->callLiveAction($page->filter(self::STEP)->children()->first(), 'selectDeliveryAddress', ['addressId' => (int) $other->getId()]);

        $answer = new Crawler((string) $this->client->getResponse()->getContent());
        self::assertSame('The delivery address of a quote is the one it was agreed with and cannot be changed.', trim($answer->filter('[data-testid="quote-delivery-imposed"]')->text()));
        self::assertSame($held, $this->theCartOf($scenario['quote'])->getAddressDeliveryId());
        self::assertStringNotContainsString('Paris', $answer->filter('[data-testid="quote-delivery-address"]')->text());
    }

    public function testAnotherCarrierIsAnsweredWithTheSentenceAndTheCartKeepsTheOneOfTheQuote(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $held = $this->theCartOf($scenario['quote'])->getDeliveryModuleId();
        $this->anotherCarrierServes();
        $page = $this->get('/checkout/delivery');

        $this->callLiveAction($page->filter(self::STEP)->children()->first(), 'selectDeliveryModuleOption', ['optionCode' => 'VirtualProductDelivery', 'moduleId' => $this->moduleId('VirtualProductDelivery')]);

        $answer = new Crawler((string) $this->client->getResponse()->getContent());
        self::assertSame('The carrier of a quote is the one it was agreed with and cannot be changed.', trim($answer->filter('[data-testid="quote-delivery-imposed"]')->text()));
        self::assertSame($held, $this->theCartOf($scenario['quote'])->getDeliveryModuleId());
    }

    public function testAQuoteThatNamesNoAddressOffersTheChoiceOnceAndThenHoldsIt(): void
    {
        $buyer = $this->customer();
        $first = $this->factory->address($buyer, CountryQuery::create()->findOneByIsoalpha2('FR'), $buyer->getCustomerTitle(), ['zipcode' => '69003', 'city' => 'Lyon', 'isDefault' => 1]);
        $second = $this->factory->address($buyer, CountryQuery::create()->findOneByIsoalpha2('FR'), $buyer->getCustomerTitle(), ['zipcode' => '75001', 'city' => 'Paris']);
        $this->servedByTheCarrier($first);
        $scenario = $this->requestedQuote($buyer);
        $this->shopAnswers($scenario['quote'], $scenario);
        $this->accept($scenario);
        self::assertNull($this->theCartOf($scenario['quote'])->getAddressDeliveryId(), 'The quote names no address.');
        $first->setIsDefault(1)->save($this->getPropelConnection());

        $page = $this->get('/checkout/delivery');

        self::assertCount(2, $page->filter('.AddressCard'), 'The buyer is offered their addresses.');
        self::assertCount(0, $page->filter('[data-testid="quote-delivery-address"]'));
        self::assertNull($this->theCartOf($scenario['quote'])->getAddressDeliveryId(), 'None is chosen for them: not the favourite one either.');

        $this->callLiveAction($page->filter(self::STEP)->children()->first(), 'selectDeliveryAddress', ['addressId' => (int) $second->getId()]);
        $held = $this->theCartOf($scenario['quote'])->getAddressDeliveryId();
        self::assertNotNull($held, 'The choice is written.');

        $after = $this->get('/checkout/delivery');
        self::assertStringContainsString('Paris', $after->filter('[data-testid="quote-delivery-address"]')->text(), 'Held from now on.');
        self::assertCount(0, $after->filter('.AddressCard'));

        $this->callLiveAction($after->filter(self::STEP)->children()->first(), 'selectDeliveryAddress', ['addressId' => (int) $first->getId()]);

        self::assertCount(1, (new Crawler((string) $this->client->getResponse()->getContent()))->filter('[data-testid="quote-delivery-imposed"]'));
        self::assertSame($held, $this->theCartOf($scenario['quote'])->getAddressDeliveryId());
    }

    public function testAnOrdinaryCartStillChoosesItsAddress(): void
    {
        $buyer = $this->customer();
        $address = $this->factory->address($buyer, CountryQuery::create()->findOneByIsoalpha2('FR'), $buyer->getCustomerTitle(), ['zipcode' => '69003', 'city' => 'Lyon', 'isDefault' => 1]);
        $this->servedByTheCarrier($address);
        $this->setQuoteMode('disabled');
        $this->signInWithCart($buyer, $this->cartOf($buyer, [[$this->product('10', 'Own product'), 1.0, '10']]));

        $page = $this->get('/checkout/delivery');

        self::assertSame(200, $this->statusCode());
        self::assertCount(0, $page->filter('[data-testid="quote-delivery-address"], [data-testid="quote-delivery-carrier"]'));
        self::assertCount(1, $page->filter('.AddressCard'));
    }

    public function testTheWayToThePaymentWithAnotherCarrierOnTheCartIsRefusedWithTheSentenceAndNotWithAnError(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $cart = $this->theCartOf($scenario['quote']);
        $this->anotherCarrierServes();
        $cart->setPaymentModuleId($this->moduleId('Cheque'))->setDeliveryModuleId($this->moduleId('VirtualProductDelivery'))->save($this->getPropelConnection());
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }
        ConsentTableMap::clearInstancePool();
        ConsentTableMap::clearRelatedInstancePool();

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(302, $this->statusCode(), 'Sent back to a step, not served an error.');
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $page = $this->client->followRedirect();
        self::assertSame('/checkout/cart', $location);
        self::assertStringContainsString('The carrier of a quote is the one it was agreed with and cannot be changed.', $page->filter('.SnackBar')->text());
    }

    public function testTheTitlesOfTheStepSayTheCarrierAndTheAddressOfAQuoteAreNotChosen(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);

        $text = $this->flat($this->get('/checkout/delivery')->filter(self::STEP)->text());

        self::assertStringContainsString('Your delivery address', $text);
        self::assertStringContainsString('Your delivery method', $text);
        self::assertStringNotContainsString('Choose a delivery', $text);
    }

    public function testAQuoteThatCannotBeOrderedAnyMoreSendsTheBuyerBackToItsPageWithTheReasonAndNotToAnError(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $this->accept($scenario);
        $cart = $this->theCartOf($scenario['quote']);
        $cart->setPaymentModuleId($this->moduleId('Cheque'))->save($this->getPropelConnection());
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }
        ConsentTableMap::clearInstancePool();
        ConsentTableMap::clearRelatedInstancePool();
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($scenario['quote']->getId())->filterByNumber(2)->findOne();
        $line = QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->filterByProductRef($scenario['a']->getRef())->findOne();
        $this->getPropelConnection()->exec('UPDATE `quote_item` SET `product_sale_elements_id` = NULL WHERE `id` = '.(int) $line->getId());

        $this->client->request('GET', '/checkout/pay');

        self::assertSame(302, $this->statusCode(), 'Sent back to a page, not served an error.');
        self::assertSame($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]), $this->client->getResponse()->headers->get('Location'));
        $page = $this->client->followRedirect();
        self::assertSame(200, $this->statusCode());
        self::assertStringContainsString('Some lines of this quote are no longer sold, so it cannot be accepted.', $page->filter('[data-testid="quote-answer-error"]')->text());
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count(), 'No order was written.');
    }

    /**
     * A second carrier the cart could be shipped with: the step answers a carrier it does not offer with nothing at all.
     */
    private function anotherCarrierServes(): void
    {
        $area = AreaQuery::create()->findOneByName('Quote checkout test area') ?? self::fail('No area.');
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($this->moduleId('VirtualProductDelivery'))->save($this->getPropelConnection());
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_OPTIONS, static function (DeliveryModuleOptionEvent $event): void {
            if ('VirtualProductDelivery' !== $event->getModule()->getCode()) {
                return;
            }

            $event->appendDeliveryModuleOptions(
                (new DeliveryModuleOption())->setCode('VirtualProductDelivery')->setValid(true)->setTitle('Second carrier')->setImage('')->setPostage(5.0)->setPostageTax(1.0)->setPostageUntaxed(4.0),
            );
        });
    }

    /**
     * @param array{buyer: Customer, quote: Quote} $scenario
     */
    private function accept(array $scenario): void
    {
        $token = $this->tokenForTheSession();
        $this->signInAs($scenario['buyer']);
        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token, 'revision' => '2']);
        self::assertSame(302, $this->statusCode());
    }

    private function theCartOf(Quote $quote): Cart
    {
        \Thelia\Model\Map\CartTableMap::clearInstancePool();

        return CartQuery::create()->filterByQuoteId($quote->getId())->findOne() ?? self::fail('The quote has no cart.');
    }

    private function flat(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text) ?? '');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function callLiveAction(Crawler $node, string $action, array $args): void
    {
        self::assertGreaterThan(0, $node->count(), \sprintf('Nothing on the page to send "%s" through.', $action));
        $component = $node->matches('[data-live-url-value]') ? $node : $node->ancestors()->filter('[data-live-url-value]')->first();
        self::assertCount(1, $component, 'The control must live inside a live component.');

        $this->client->request(
            'POST',
            \sprintf('%s/%s', $component->attr('data-live-url-value'), $action),
            ['data' => json_encode([
                'props' => json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR),
                'updated' => new \stdClass(),
                'args' => $args,
            ], \JSON_THROW_ON_ERROR)],
            [],
            ['HTTP_ACCEPT' => 'application/vnd.live-component+html', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );

        self::assertSame(200, $this->statusCode(), \sprintf('The action "%s" must answer.', $action));
    }
}

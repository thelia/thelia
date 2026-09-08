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

namespace Thelia\Tests\Http\Flexy;

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;

/**
 * What the delivery step lets a guest do with an address id it was not given.
 *
 * One customer row is shared by everyone who ever ordered on an address — ordering
 * without an account proves nothing about owning the address, so the row is reused
 * rather than duplicated — and the addresses of every buyer before hang off it. The
 * rendered list is narrowed to the addresses of the identification in hand, but the
 * live actions take an id straight from the request, so each of them is asked here for
 * an address belonging to the buyer before.
 *
 * Everything below goes through the real component endpoint, with the props the page
 * itself handed out: this is the request the page makes, with one number changed. The
 * billing block, which only appears on the payment step, is covered by
 * {@see \Thelia\Tests\Integration\Flexy\GuestCheckoutAddressGuardTest}.
 */
final class GuestCheckoutAddressScopeTest extends GuestCheckoutTestCase
{
    private const FIRST_BUYER_STREET = '12 rue du Premier Acheteur';

    private const SECOND_BUYER_STREET = '34 rue du Second Acheteur';

    private const DELIVERY_COMPONENT = 'Organisms:Delivery:Base';

    public function testTheDeliveryStepRefusesToOpenTheAddressOfTheBuyerBefore(): void
    {
        [$firstAddress, $crawler] = $this->twoGuestsOnOneEmail();

        $this->callLiveAction($crawler, self::DELIVERY_COMPONENT, 'setEditingAddress', [
            'addressId' => $firstAddress->getId(),
        ]);

        $this->assertTheActionWasRefused();
    }

    public function testTheDeliveryStepRefusesToDeleteTheAddressOfTheBuyerBefore(): void
    {
        [$firstAddress, $crawler] = $this->twoGuestsOnOneEmail();

        $this->callLiveAction($crawler, self::DELIVERY_COMPONENT, 'deleteAddress', [
            'addressId' => $firstAddress->getId(),
        ]);

        $this->assertTheActionWasRefused();
        self::assertNotNull(
            AddressQuery::create()->findPk($firstAddress->getId()),
            'The address of the buyer before must still be there.',
        );
    }

    public function testTheDeliveryStepRefusesToShipToTheAddressOfTheBuyerBefore(): void
    {
        [$firstAddress, $crawler] = $this->twoGuestsOnOneEmail();

        $this->callLiveAction($crawler, self::DELIVERY_COMPONENT, 'selectDeliveryAddress', [
            'addressId' => $firstAddress->getId(),
        ]);

        $this->assertTheActionWasRefused();
    }

    public function testTheDeliveryStepRefusesToInvoiceTheAddressOfTheBuyerBefore(): void
    {
        [$firstAddress, $crawler] = $this->twoGuestsOnOneEmail();

        $this->callLiveAction($crawler, self::DELIVERY_COMPONENT, 'selectInvoiceAddress', [
            'addressId' => $firstAddress->getId(),
        ]);

        $this->assertTheActionWasRefused();
    }

    /**
     * Two identifications on one email address, from two browsers that share nothing.
     *
     * @return array{0: Address, 1: Crawler} the address of the first buyer, and the
     *                                       delivery page of the second one
     */
    private function twoGuestsOnOneEmail(): array
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $this->identifyAsAGuestLivingAt(self::FIRST_BUYER_STREET);

        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);

        self::assertInstanceOf(Customer::class, $guest);

        $firstAddress = AddressQuery::create()
            ->filterByCustomerId($guest->getId())
            ->filterByAddress1(self::FIRST_BUYER_STREET)
            ->findOne()
        ;

        self::assertInstanceOf(Address::class, $firstAddress, 'The first buyer must have left their address behind.');

        $this->client->restart();
        $this->identifyAsAGuestLivingAt(self::SECOND_BUYER_STREET);

        $crawler = $this->client->request('GET', '/checkout/delivery');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return [$firstAddress, $crawler];
    }

    private function identifyAsAGuestLivingAt(string $street): void
    {
        $this->openASessionWithACart();

        $this->client->submit($this->guestFormOf($this->requestIdentificationPage(), [
            'flexybundle_form_guest_checkout[address1]' => $street,
        ]));

        $this->assertResponseRedirectsTo('/checkout/delivery');
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function callLiveAction(Crawler $crawler, string $component, string $action, array $arguments): void
    {
        [$url, $props] = $this->liveComponentOnThePage($crawler, $component);

        $this->client->request(
            'POST',
            \sprintf('%s/%s', rtrim($url, '/'), $action),
            ['data' => json_encode(['args' => $arguments, 'props' => $props], \JSON_THROW_ON_ERROR)],
        );
    }

    /**
     * The endpoint and the signed props the page itself put on the component.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function liveComponentOnThePage(Crawler $crawler, string $component): array
    {
        $node = $crawler->filter(\sprintf('[data-live-name-value="%s"]', $component));

        self::assertGreaterThan(
            0,
            $node->count(),
            \sprintf('The delivery page must carry the "%s" component for this to mean anything.', $component),
        );

        return [
            (string) $node->attr('data-live-url-value'),
            json_decode((string) $node->attr('data-live-props-value'), true, flags: \JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * A refusal, whichever shape the component runtime gives it: what must never happen
     * is the action going through and answering with a rendered component.
     */
    private function assertTheActionWasRefused(): void
    {
        self::assertGreaterThanOrEqual(
            400,
            $this->client->getResponse()->getStatusCode(),
            'An address that was not typed on this identification must not be reachable.',
        );
    }
}

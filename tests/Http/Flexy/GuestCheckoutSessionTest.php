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

use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\AddressQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;

/**
 * What identifying as a guest actually leaves behind: an account with no password, the
 * address the buyer typed, and a session the checkout accepts — while the account area
 * stays as closed to them as it is to a visitor.
 */
final class GuestCheckoutSessionTest extends GuestCheckoutTestCase
{
    private const FIRST_BUYER_STREET = '12 rue du Premier Acheteur';

    private const SECOND_BUYER_STREET = '34 rue du Second Acheteur';

    public function testTheGuestFormOpensAPasswordlessAccountAndItsAddress(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $this->assertResponseRedirectsTo('/checkout/delivery');

        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);

        self::assertInstanceOf(Customer::class, $guest, 'The submission must open an account for the buyer.');
        self::assertTrue($guest->isGuest(), 'That account carries no password and is marked as a guest.');
        self::assertSame('Jean', $guest->getFirstname());
        self::assertCount(
            1,
            AddressQuery::create()->filterByCustomerId($guest->getId())->find(),
            'The delivery address the buyer typed must be written under that account.',
        );
    }

    public function testTheCartTheVisitorFilledIsHandedToTheGuestAccount(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $cart = $this->openASessionWithACart();

        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);

        self::assertSame(
            $guest?->getId(),
            CartQuery::create()->findPk($cart->getId())?->getCustomerId(),
            'Without this the order is built from a cart that belongs to nobody.',
        );
    }

    public function testTheCheckoutThenLetsTheGuestThroughWhileTheAccountAreaStaysClosed(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $this->client->request('GET', '/checkout/delivery');
        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The delivery step must accept a guest who has just identified themselves.',
        );

        $this->client->request('GET', '/account');
        $this->assertResponseRedirectsTo('/customer/login');

        $this->client->request('GET', '/account/orders');
        $this->assertResponseRedirectsTo('/customer/login');
    }

    public function testOrderingAsAGuestTwiceWithTheSameAddressReusesTheSameAccount(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        $firstGuestId = $this->guestCustomerOf(self::GUEST_EMAIL)?->getId();

        self::assertNotNull($firstGuestId, 'The first visit must open the account under the address that was typed.');

        // A second visit, from a browser that kept nothing of the first one.
        $this->client->restart();
        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        self::assertSame(
            $firstGuestId,
            $this->guestCustomerOf(self::GUEST_EMAIL)?->getId(),
            'Two rows would split one person’s orders in half.',
        );
    }

    /**
     * The other side of reusing the row: the addresses of everyone who ever ordered on
     * that address hang off it, and the delivery step used to list all of them. Ordering
     * without an account takes no credential, so the address alone is not a claim to
     * anything the buyer before typed.
     */
    public function testTheAddressesOfAPreviousGuestOnTheSameEmailAreNotShown(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $this->identifyAsAGuestLivingAt(self::FIRST_BUYER_STREET);

        // A second visit, from a browser that kept nothing of the first one, typing the
        // same address into the email field and a street of their own.
        $this->client->restart();
        $this->identifyAsAGuestLivingAt(self::SECOND_BUYER_STREET);

        $crawler = $this->client->request('GET', '/checkout/delivery');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $shown = $crawler->text();

        self::assertStringContainsString(
            self::SECOND_BUYER_STREET,
            $shown,
            'The address this buyer typed must be there to be delivered to.',
        );
        self::assertStringNotContainsString(
            self::FIRST_BUYER_STREET,
            $shown,
            'The address book of whoever ordered on this email before is not this buyer’s to read.',
        );
    }

    /**
     * The confirmation page is reachable by typing its url, and it used to empty the cart
     * of whoever asked for it — including someone still choosing a delivery method.
     */
    public function testTheConfirmationPageLeavesTheCartOfSomeoneStillCheckingOutAlone(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $this->client->request('GET', '/checkout/confirm');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/checkout/delivery');

        // The delivery step sends an empty cart back to the cart page, so a 200 here is
        // the cart still holding what the visitor put in it.
        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Asking for the confirmation halfway through must not throw the cart away.',
        );
    }

    public function testAnAddressThatAlreadyHasAnAccountIsSentToSignInInstead(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $fixtures = $this->fixtures();
        $fixtures->customer($fixtures->customerTitle(), ['email' => self::GUEST_EMAIL]);

        $this->openASessionWithACart();
        $crawler = $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The page comes back rather than sending the buyer on with an order nobody owns.',
        );
        self::assertStringContainsString(
            'already has an account',
            $crawler->text(),
            'The buyer has to be told why the form did not go through.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('form[name="thelia_customer_login"]')->count(),
            'The way out is the sign-in block, which must be on the page that says so.',
        );
    }

    /**
     * The billing block only exists on the form once the buyer says the two addresses
     * differ, so this posts it the way the page does after that box is unticked.
     */
    public function testABillingAddressOfItsOwnIsWrittenAsASecondAddress(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $this->submitGuestFormWithABillingAddressOfItsOwn();

        $this->assertResponseRedirectsTo('/checkout/delivery');

        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);
        $addresses = AddressQuery::create()->filterByCustomerId($guest?->getId())->find();

        self::assertCount(2, $addresses, 'A billing address of its own is an address in its own right.');

        $cities = array_map(static fn ($address) => $address->getCity(), iterator_to_array($addresses));

        self::assertContains('Paris', $cities);
        self::assertContains('Lyon', $cities, 'The billing address must carry what was typed into it.');
    }

    public function testAnEmptyBillingBlockIsRefusedWhenTheAddressesDiffer(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();

        $this->submitGuestFormWithABillingAddressOfItsOwn([
            'invoice_address1' => '',
            'invoice_city' => '',
            'invoice_zipcode' => '',
        ]);

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The form comes back rather than writing an address nobody can deliver an invoice to.',
        );
        self::assertNull(
            $this->guestCustomerOf(self::GUEST_EMAIL),
            'Nothing may be opened on a submission the form refuses.',
        );
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
     * @param array<string, string> $billingOverrides
     */
    private function submitGuestFormWithABillingAddressOfItsOwn(array $billingOverrides = []): void
    {
        $prefix = 'flexybundle_form_guest_checkout';
        $form = $this->guestFormOf($this->requestIdentificationPage());
        $values = $form->getPhpValues()[$prefix];

        unset($values['invoice_same']);

        $billing = [
            'invoice_title' => $values['title'],
            'invoice_firstname' => 'Jeanne',
            'invoice_lastname' => 'Durand',
            'invoice_address1' => '3 quai Saint-Antoine',
            'invoice_zipcode' => '69002',
            'invoice_city' => 'Lyon',
            'invoice_country' => $values['country'],
            ...$billingOverrides,
        ];

        $this->client->request(
            'POST',
            $form->getUri(),
            [$prefix => [...$values, ...$billing]],
        );
    }
}

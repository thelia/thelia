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

namespace Thelia\Tests\Integration\Flexy;

use FlexyBundle\Components\Forms\Address\Base as AddressForm;
use FlexyBundle\Components\Organisms\Invoice\Base as InvoiceBlock;
use FlexyBundle\Service\GuestCheckoutGate;
use Thelia\Model\Address;
use Thelia\Model\Customer;
use Thelia\Test\IntegrationTestCase;

/**
 * The billing block and the address form, asked for an address of the buyer before.
 *
 * One customer row is shared by everyone who ever ordered on an email address, so the
 * addresses of several people hang off it and belonging to the row proves nothing. What
 * decides is the identification in hand: the addresses typed on it, and no others.
 *
 * The delivery step is covered over HTTP by
 * {@see \Thelia\Tests\Http\Flexy\GuestCheckoutAddressScopeTest}; the billing block only
 * appears on the payment step, so it is asked here directly.
 */
final class GuestCheckoutAddressGuardTest extends IntegrationTestCase
{
    private const FIRST_BUYER_STREET = '12 rue du Premier Acheteur';

    private const SECOND_BUYER_STREET = '34 rue du Second Acheteur';

    /**
     * A skip rather than a failure: the core ships with whichever theme version it is
     * given, and the scope this asks about is decided by the theme. Asking a theme that
     * has no say in it proves nothing.
     */
    protected function setUp(): void
    {
        if (!method_exists(GuestCheckoutGate::class, 'assertVisible')) {
            self::markTestSkipped('The installed theme does not scope the checkout addresses.');
        }

        parent::setUp();
    }

    public function testTheBillingBlockRefusesToOpenTheAddressOfTheBuyerBefore(): void
    {
        [$foreignAddress] = $this->aSharedRowWithTwoBuyers();

        $this->expectException(\Throwable::class);

        $this->invoiceBlock()->setEditingAddress((int) $foreignAddress->getId());
    }

    public function testTheBillingBlockRefusesToInvoiceTheAddressOfTheBuyerBefore(): void
    {
        [$foreignAddress] = $this->aSharedRowWithTwoBuyers();

        $this->expectException(\Throwable::class);

        $this->invoiceBlock()->selectInvoiceAddress((int) $foreignAddress->getId());
    }

    public function testTheBillingBlockStillOpensAnAddressOfThisIdentification(): void
    {
        [, $ownAddress] = $this->aSharedRowWithTwoBuyers();

        $block = $this->invoiceBlock();
        $block->setEditingAddress((int) $ownAddress->getId());

        self::assertSame(
            (int) $ownAddress->getId(),
            $block->editingAddressId,
            'The buyer must still be able to correct what they typed a moment ago.',
        );
    }

    /**
     * The form is where the street, the name and the phone number would actually be
     * read: it fills its fields from the address it is mounted on.
     */
    public function testTheAddressFormDoesNotFillItselfWithTheAddressOfTheBuyerBefore(): void
    {
        [$foreignAddress] = $this->aSharedRowWithTwoBuyers();

        $form = $this->addressForm();
        $form->addressId = (int) $foreignAddress->getId();

        self::assertNotSame(
            self::FIRST_BUYER_STREET,
            $this->streetShownBy($form),
            'The form must not hand back the address of whoever ordered on this email before.',
        );
    }

    public function testTheAddressFormStillFillsItselfWithAnAddressOfThisIdentification(): void
    {
        [, $ownAddress] = $this->aSharedRowWithTwoBuyers();

        $form = $this->addressForm();
        $form->addressId = (int) $ownAddress->getId();

        self::assertSame(
            self::SECOND_BUYER_STREET,
            $this->streetShownBy($form),
            'Correcting the address just typed is the whole point of the edit form.',
        );
    }

    private function streetShownBy(AddressForm $form): mixed
    {
        return $form->getFormView()->children['address1']->vars['value'] ?? null;
    }

    /**
     * One guest row carrying the addresses of two buyers, with the session holding the
     * second identification: the address of the first is on the row and not on the
     * session.
     *
     * @return array{0: Address, 1: Address} the address of the buyer before, then the
     *                                       address of the buyer in the session
     */
    private function aSharedRowWithTwoBuyers(): array
    {
        $fixtures = $this->createFixtureFactory();
        $title = $fixtures->customerTitle();
        $country = $fixtures->country();

        $sharedRow = $fixtures->guestCustomer($title);

        $foreignAddress = $fixtures->address($sharedRow, $country, $title, [
            'address1' => self::FIRST_BUYER_STREET,
        ]);
        $ownAddress = $fixtures->address($sharedRow, $country, $title, [
            'address1' => self::SECOND_BUYER_STREET,
        ]);

        $this->signInAs($sharedRow, [(int) $ownAddress->getId()]);

        return [$foreignAddress, $ownAddress];
    }

    /**
     * @param list<int> $identificationAddressIds
     */
    private function signInAs(Customer $guest, array $identificationAddressIds): void
    {
        /** @var GuestCheckoutGate $gate */
        $gate = $this->getService(GuestCheckoutGate::class);
        $gate->signIn($guest, $identificationAddressIds);
    }

    private function invoiceBlock(): InvoiceBlock
    {
        /** @var InvoiceBlock $block */
        $block = $this->getService(InvoiceBlock::class);

        return $block;
    }

    private function addressForm(): AddressForm
    {
        /** @var AddressForm $form */
        $form = $this->getService(AddressForm::class);

        return $form;
    }
}

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

use FlexyBundle\Components\Forms\Address\Account as AccountForm;
use FlexyBundle\Components\Forms\Address\Base as AddressForm;
use FlexyBundle\Components\Organisms\AddressCard\Base as AddressCard;
use FlexyBundle\Exception\AddressNotInCheckoutException;
use FlexyBundle\Service\GuestCheckoutGate;
use Thelia\Tests\Integration\Company\AddressTestCase;

/**
 * The address book of the checkout for a buyer of a company: their own addresses and the ones their
 * company shares, never one of another company, in the lists, and in the guard of an address id that
 * came in with a request.
 */
final class CompanyCheckoutAddressesTest extends AddressTestCase
{
    /**
     * A skip rather than a failure: the pages belong to the theme, which ships on its own release cycle.
     */
    protected function setUp(): void
    {
        if (!class_exists('FlexyBundle\\Service\\CompanyAccount')) {
            self::markTestSkipped('The installed theme has no company account.');
        }

        parent::setUp();
    }

    public function testTheBuyerChoosesTheSharedAddressAndNeverAnotherCompanysNorAColleaguesPersonalOne(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $personalOfCreator = $this->addressOf($world['creator'], null, 'Home');
        $mine = $this->addressOf($world['colleague'], null, 'Mine');
        $depotOfSecond = $this->addressOf($world['buyerOfSecond'], $world['second'], 'Depot');

        $this->gate()->signIn($world['colleague']);

        self::assertSame([$warehouse->getId(), $mine->getId()], $this->visibleIds());
        self::assertTrue($this->gate()->isVisible((int) $warehouse->getId()));
        self::assertFalse($this->gate()->isVisible((int) $personalOfCreator->getId()));
        self::assertFalse($this->gate()->isVisible((int) $depotOfSecond->getId()));

        $this->expectException(AddressNotInCheckoutException::class);
        $this->gate()->assertVisible((int) $depotOfSecond->getId());
    }

    public function testACustomerWithoutCompanyKeepsTheirOwnBookAndNothingMore(): void
    {
        $world = $this->twoCompanies();
        $alone = $this->customer();
        $mine = $this->addressOf($alone, null, 'Mine');
        $this->addressOf($world['creator'], $world['first'], 'Warehouse');

        $this->gate()->signIn($alone);

        self::assertSame([$mine->getId()], $this->visibleIds());
    }

    public function testTheAddressCardOpensTheAddressesOfTheBuyerAndNothingOfAnotherCompany(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $personalOfCreator = $this->addressOf($world['creator'], null, 'Home');
        $depotOfSecond = $this->addressOf($world['buyerOfSecond'], $world['second'], 'Depot');
        $this->gate()->signIn($world['colleague']);

        $shared = $this->card((int) $warehouse->getId());
        self::assertSame('Warehouse', $shared->address['label'] ?? null);
        self::assertTrue($shared->shared);
        self::assertFalse($shared->canEdit, 'A colleague without the right cannot change it.');
        self::assertFalse($shared->ownedByCustomer);

        foreach ([$personalOfCreator, $depotOfSecond] as $foreign) {
            self::assertNull($this->card((int) $foreign->getId())->address, 'An address the buyer may not use opens nothing.');
        }

        $this->gate()->signIn($world['administratorOfFirst']);
        self::assertTrue($this->card((int) $warehouse->getId())->canEdit, 'The administrator manages the shared addresses.');
    }

    public function testTheAddressFormsOpenOnlyWhatTheBuyerMayChange(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $personalOfCreator = $this->addressOf($world['creator'], null, 'Home');
        $depotOfSecond = $this->addressOf($world['buyerOfSecond'], $world['second'], 'Depot');

        foreach (['colleague' => [], 'administratorOfFirst' => ['Warehouse'], 'creator' => ['Warehouse', 'Home']] as $who => $mayChange) {
            $this->gate()->signIn($world[$who]);

            foreach ([$warehouse, $personalOfCreator, $depotOfSecond] as $address) {
                foreach ([AccountForm::class, AddressForm::class] as $component) {
                    $opened = $this->formOf($component, (int) $address->getId());

                    self::assertSame(
                        \in_array($address->getLabel(), $mayChange, true),
                        'Warehouse' === $opened || 'Home' === $opened,
                        $who.' on '.$address->getLabel().' through '.$component,
                    );
                }
            }
        }
    }

    private function card(int $addressId): AddressCard
    {
        $card = $this->getService(AddressCard::class);
        $card->mount($addressId);

        return $card;
    }

    private function formOf(string $component, int $addressId): ?string
    {
        $form = $this->getService($component);
        $form->addressId = $addressId;

        $instantiate = new \ReflectionMethod($form, 'instantiateForm');

        return $instantiate->invoke($form)->get('label')->getData();
    }

    private function gate(): GuestCheckoutGate
    {
        return $this->getService(GuestCheckoutGate::class);
    }

    /**
     * @return list<int>
     */
    private function visibleIds(): array
    {
        return array_map(static fn (array $address): int => (int) $address['id'], $this->gate()->visibleAddresses());
    }
}

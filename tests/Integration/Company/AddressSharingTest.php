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

namespace Thelia\Tests\Integration\Company;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\State\Processor\CheckoutSelectionProcessor;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Addressing\DTO\AddressDTO;
use Thelia\Domain\Addressing\Service\AddressService;
use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\Exception\DestinationRestrictedException;
use Thelia\Domain\Checkout\Service\ExpressCheckoutTotal;
use Thelia\Domain\Customer\Exception\CustomerException;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;
use Thelia\Domain\Shipping\Service\DeliverySetupService;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\DestinationRestriction;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * An address shared with a company is read and chosen by every buyer of the company, changed by
 * its creator and by the buyers whose role manages the shared ones, and by nobody of another
 * company. A customer who belongs to no company reads their own addresses as they always did.
 */
final class AddressSharingTest extends AddressTestCase
{
    use RecordsSqlQueries;

    public function testABuyerSeesTheSharedAddressOfTheCompanyAndNotThePersonalOnesOfAColleague(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $home = $this->addressOf($world['creator'], null, 'Home');
        $mine = $this->addressOf($world['colleague'], null, 'Mine');

        self::assertSame([$warehouse->getId(), $mine->getId()], $this->idsOf($this->addressFacadeOf($world['colleague'])->getByCustomer()));
        self::assertSame([$warehouse->getId(), $home->getId()], $this->idsOf($this->addressFacadeOf($world['creator'])->getByCustomer()));
        self::assertSame([$warehouse->getId()], $this->idsOf($this->addressFacadeOf($world['administratorOfFirst'])->getByCustomer()));

        self::assertSame($warehouse->getId(), $this->access->find($world['colleague'], $warehouse->getId())?->getId());
        self::assertNull($this->access->find($world['colleague'], $home->getId()), 'A personal address of a colleague is not told apart from a missing one.');
        self::assertNull($this->access->find($world['colleague'], 0));
        self::assertNull($this->access->find(null, $warehouse->getId()));
    }

    public function testTheCrossingOfTwoCompaniesIsClosedInBothDirections(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $depot = $this->addressOf($world['buyerOfSecond'], $world['second'], 'Depot');

        foreach ([$world['administratorOfSecond'], $world['buyerOfSecond']] as $buyerOfSecond) {
            self::assertNull($this->access->find($buyerOfSecond, $warehouse->getId()));
            self::assertNotContains($warehouse->getId(), $this->idsOf($this->addressFacadeOf($buyerOfSecond)->getByCustomer()));
        }

        foreach ([$world['administratorOfFirst'], $world['creator'], $world['colleague']] as $buyerOfFirst) {
            self::assertNull($this->access->find($buyerOfFirst, $depot->getId()));
            self::assertNotContains($depot->getId(), $this->idsOf($this->addressFacadeOf($buyerOfFirst)->getByCustomer()));
        }

        $stranger = $this->customer();

        self::assertNull($this->access->find($stranger, $warehouse->getId()));
        self::assertSame([], $this->idsOf($this->addressFacadeOf($stranger)->getByCustomer()));
    }

    public function testASharedAddressIsChangedAndDeletedByItsCreatorAndByTheAdministratorOnly(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $dto = $this->dto('Renamed');

        foreach ([$world['colleague'], $world['administratorOfSecond'], $this->customer()] as $refused) {
            try {
                $this->addressFacadeOf($refused)->update((int) $warehouse->getId(), $dto);
                self::fail('Customer '.$refused->getId().' changed an address they may not change.');
            } catch (CustomerException) {
                self::assertSame('Warehouse', AddressQuery::create()->findPk($warehouse->getId())?->getLabel());
            }

            try {
                $this->addressFacadeOf($refused)->delete((int) $warehouse->getId());
                self::fail('Customer '.$refused->getId().' deleted an address they may not delete.');
            } catch (CustomerException) {
                self::assertNotNull(AddressQuery::create()->findPk($warehouse->getId()));
            }
        }

        $this->addressFacadeOf($world['administratorOfFirst'])->update((int) $warehouse->getId(), $dto);
        self::assertSame('Renamed', AddressQuery::create()->findPk($warehouse->getId())?->getLabel());

        $this->addressFacadeOf($world['creator'])->update((int) $warehouse->getId(), $this->dto('By its creator'));
        self::assertSame('By its creator', AddressQuery::create()->findPk($warehouse->getId())?->getLabel());

        $this->addressFacadeOf($world['administratorOfFirst'])->delete((int) $warehouse->getId());
        self::assertNull(AddressQuery::create()->findPk($warehouse->getId()));
    }

    public function testTheCreatorDeletesTheirOwnSharedAddress(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $this->addressOf($world['creator'], null, 'Home')->makeItDefault();

        $this->addressFacadeOf($world['creator'])->delete((int) $warehouse->getId());

        self::assertNull(AddressQuery::create()->findPk($warehouse->getId()));
    }

    public function testTheAccountFormsServiceFollowsTheSameRule(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $service = $this->serviceFor($world['colleague']);

        try {
            $service->deleteAddress((int) $warehouse->getId());
            self::fail('A colleague deleted the shared address.');
        } catch (CustomerException) {
            self::assertNotNull(AddressQuery::create()->findPk($warehouse->getId()));
        }

        $this->serviceFor($world['administratorOfFirst'])->deleteAddress((int) $warehouse->getId());
        self::assertNull(AddressQuery::create()->findPk($warehouse->getId()));

        $other = $this->addressOf($world['creator'], $world['first'], 'Second warehouse');

        try {
            $this->serviceFor($world['administratorOfSecond'])->deleteAddress((int) $other->getId());
            self::fail('The administrator of another company deleted the shared address.');
        } catch (CustomerException) {
            self::assertNotNull(AddressQuery::create()->findPk($other->getId()));
        }
    }

    public function testTheAccountFormsServiceKeepsTheDefaultAddressOfItsOwner(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $warehouse->makeItDefault();
        $second = $this->addressOf($world['creator'], $world['first'], 'Second warehouse');
        $country = (int) $second->getCountryId();

        $this->serviceFor($world['administratorOfFirst'])->updateAddress((int) $second->getId(), $this->formOf($country, isDefault: true));

        self::assertSame($warehouse->getId(), $world['creator']->getDefaultAddress()?->getId());
        self::assertNull($world['administratorOfFirst']->getDefaultAddress());

        $this->serviceFor($world['creator'])->updateAddress((int) $second->getId(), $this->formOf($country, isDefault: true));

        self::assertSame($second->getId(), $world['creator']->getDefaultAddress()?->getId(), 'The owner makes it their default one.');
    }

    public function testASharedAddressIsNeverTheDefaultAddressOfAnotherBuyer(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $warehouse->makeItDefault();
        $mine = $this->addressOf($world['colleague'], null, 'Mine');
        $mine->makeItDefault();

        self::assertSame($warehouse->getId(), $world['creator']->getDefaultAddress()?->getId());
        self::assertSame($mine->getId(), $world['colleague']->getDefaultAddress()?->getId());
        self::assertNull($world['administratorOfFirst']->getDefaultAddress());

        try {
            $this->addressFacadeOf($world['colleague'])->setAsDefault((int) $warehouse->getId());
            self::fail('A colleague made a shared address their default one.');
        } catch (CustomerException) {
            self::assertSame($mine->getId(), $world['colleague']->getDefaultAddress()?->getId());
        }

        try {
            $this->addressFacadeOf($world['administratorOfFirst'])->setAsDefault((int) $warehouse->getId());
            self::fail('An administrator made a shared address of a colleague their default one.');
        } catch (CustomerException) {
            self::assertNull($world['administratorOfFirst']->getDefaultAddress());
        }

        $second = $this->addressOf($world['creator'], $world['first'], 'Second warehouse');
        $this->addressFacadeOf($world['administratorOfFirst'])->update(
            (int) $second->getId(),
            new AddressDTO('Second warehouse', 'John', 'Doe', '1 Main Street', '75001', 'Paris', (int) $second->getCountryId(), isDefault: true),
        );

        self::assertNull($world['administratorOfFirst']->getDefaultAddress(), 'Editing a shared address does not make it the default one of the editor.');
        self::assertSame($warehouse->getId(), $world['creator']->getDefaultAddress()?->getId(), 'Nor does it take the default one of its owner.');
        self::assertSame(0, (int) AddressQuery::create()->findPk($second->getId())?->getIsDefault());
    }

    public function testTheLoopListsTheSharedAddressesAndNeverTheDefaultOneOfAnotherBuyer(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $warehouse->makeItDefault();
        $home = $this->addressOf($world['creator'], null, 'Home');
        $mine = $this->addressOf($world['colleague'], null, 'Mine');
        $mine->makeItDefault();

        $asColleague = $this->listedAs($world['colleague'], []);
        self::assertSame([$warehouse->getId(), $mine->getId()], array_keys($asColleague));
        self::assertSame(0, $asColleague[$warehouse->getId()], 'The default flag of the owner is not the one of a colleague.');
        self::assertSame(1, $asColleague[$mine->getId()]);

        self::assertSame([$mine->getId()], array_keys($this->listedAs($world['colleague'], ['default' => 'true'])));
        self::assertSame([$warehouse->getId()], array_keys($this->listedAs($world['colleague'], ['default' => 'false'])));

        $asCreator = $this->listedAs($world['creator'], []);
        self::assertSame([$warehouse->getId(), $home->getId()], array_keys($asCreator));
        self::assertSame(1, $asCreator[$warehouse->getId()]);
        self::assertSame([$warehouse->getId()], array_keys($this->listedAs($world['creator'], ['default' => 'true'])));

        self::assertSame([], $this->listedAs($world['administratorOfSecond'], []));
    }

    public function testABuyerChoosesTheSharedAddressAtTheCheckoutAndRefusesTheOnesOfAnotherCompany(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $home = $this->addressOf($world['creator'], null, 'Home');
        $depot = $this->addressOf($world['buyerOfSecond'], $world['second'], 'Depot');
        $privateOfSecond = $this->addressOf($world['buyerOfSecond'], null, 'Private');
        $selection = $this->getService(CartSelectionService::class);

        $cart = $this->cartOf($world['colleague']);
        $selection->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: (int) $warehouse->getId()));
        self::assertSame($warehouse->getId(), $this->deliveryAddressOf($cart));

        $cart = $this->cartOf($world['colleague']);
        $selection->setInvoiceAddress(new CheckoutDTO($cart, invoiceAddressId: (int) $warehouse->getId()));
        self::assertSame($warehouse->getId(), $this->invoiceAddressOf($cart));

        foreach ([$home, $depot, $privateOfSecond] as $refused) {
            $cart = $this->cartOf($world['colleague']);
            $selection->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: (int) $refused->getId()));
            self::assertNull($this->deliveryAddressOf($cart), 'Address '.$refused->getLabel().' must not reach the cart.');

            $selection->setInvoiceAddress(new CheckoutDTO($cart, invoiceAddressId: (int) $refused->getId()));
            self::assertNull($this->invoiceAddressOf($cart), 'Address '.$refused->getLabel().' must not reach the cart.');
        }

        $cart = $this->cartOf($world['buyerOfSecond']);
        $selection->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: (int) $warehouse->getId()));
        self::assertNull($this->deliveryAddressOf($cart), 'The buyer of another company is told nothing more than for a missing address.');
    }

    public function testTheApiSelectionAnswersTheSameWayForASharedAddressAndForOneOfAnotherCompany(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $depot = $this->addressOf($world['buyerOfSecond'], $world['second'], 'Depot');
        $cart = $this->cartOf($world['colleague']);

        self::assertSame($warehouse->getId(), $this->apiAddressOf($cart, (int) $warehouse->getId())->getId());

        foreach ([(int) $depot->getId(), 999999999] as $unknown) {
            try {
                $this->apiAddressOf($cart, $unknown);
                self::fail('The address '.$unknown.' must be refused.');
            } catch (NotFoundHttpException $refusal) {
                self::assertSame('No such address.', $refusal->getMessage());
            }
        }
    }

    public function testTheAddressAUrlNamesIsReadOnlyWhenTheCustomerMayUseIt(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $home = $this->addressOf($world['creator'], null, 'Home');
        $mine = $this->addressOf($world['colleague'], null, 'Mine');
        $mine->makeItDefault();
        $service = $this->serviceFor($world['colleague']);
        $context = $this->getService(SecurityContext::class);
        $this->signIn($world['colleague']);

        self::assertSame($warehouse->getId(), $service->getDeliveryAddress($this->requestNaming($warehouse), $context)?->getId());
        self::assertSame($mine->getId(), $service->getDeliveryAddress($this->requestNaming($home), $context)?->getId(), 'The personal address of a colleague falls back to the default address.');

        $this->signIn($world['buyerOfSecond']);
        self::assertNull($service->getDeliveryAddress($this->requestNaming($warehouse), $context), 'Nor does a buyer of another company read it.');
    }

    public function testADestinationTheProductsCannotShipToIsRefusedForASharedAddressToo(): void
    {
        $world = $this->twoCompanies();
        [$refused, $served] = CountryQuery::create()->filterByHasStates(0)->orderById()->limit(2)->find()->getData();
        $whisky = $this->fixtures->product($this->fixtures->category(), $this->fixtures->taxRule(), $this->fixtures->currency(), ['title' => 'Whisky', 'baseQuantity' => 10]);
        $restrictions = $this->getService(DestinationRestrictionFacade::class);
        $group = $restrictions->save(null, 'whisky', DestinationRestriction::MODE_DENY, true, ['en_US' => ['title' => 'Whisky', 'reason' => 'Spirits']]);
        $restrictions->addDestination($group->getId(), (int) $refused->getId());
        $restrictions->attachProduct($group->getId(), (int) $whisky->getId());
        $this->getService(DestinationRestrictionChecker::class)->reset();

        $farWarehouse = $this->fixtures->address($world['creator'], $refused, null, ['label' => 'Far warehouse'])->setCompanyId($world['first']->getId());
        $farWarehouse->save($this->getPropelConnection());
        $nearWarehouse = $this->fixtures->address($world['creator'], $served, null, ['label' => 'Near warehouse'])->setCompanyId($world['first']->getId());
        $nearWarehouse->save($this->getPropelConnection());
        $cart = $this->cartOf($world['colleague']);
        $this->fixtures->cartItem($cart, $whisky);
        $selection = $this->getService(CartSelectionService::class);

        try {
            $selection->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: (int) $farWarehouse->getId()));
            self::fail('The shared address cannot receive the whisky.');
        } catch (DestinationRestrictedException) {
            self::assertNull($this->deliveryAddressOf($cart));
        }

        $selection->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: (int) $nearWarehouse->getId()));
        self::assertSame($nearWarehouse->getId(), $this->deliveryAddressOf($cart));
    }

    public function testACartThatShipsNothingIsTaxedAtTheAddressOfTheBuyerAndFailingThatAtTheSharedOne(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $warehouse->makeItDefault();
        $home = $this->addressOf($world['creator'], null, 'Home');
        $mine = $this->addressOf($world['colleague'], null, 'Mine');
        $total = $this->getService(ExpressCheckoutTotal::class);

        $cartOfCreator = $this->virtualCartOf($world['creator']);
        self::assertSame($warehouse->getId(), $total->addressTheCartIsTaxedFor($cartOfCreator)?->getId(), 'The default address of the owner is the shared one.');

        $cartOfColleague = $this->virtualCartOf($world['colleague']);
        self::assertSame($mine->getId(), $total->addressTheCartIsTaxedFor($cartOfColleague)?->getId(), 'A buyer with an address of their own is taxed there.');

        $mine->delete();
        self::assertSame($warehouse->getId(), $total->addressTheCartIsTaxedFor($this->virtualCartOf($world['colleague']))?->getId());

        self::assertNotSame($home->getId(), $total->addressTheCartIsTaxedFor($this->virtualCartOf($world['colleague']))?->getId());
        self::assertNull($total->addressTheCartIsTaxedFor($this->virtualCartOf($world['buyerOfSecond'])));

        $this->getService(DeliverySetupService::class)->setupVirtualDelivery($cartOfColleague = $this->virtualCartOf($world['colleague']));
        $copy = CartQuery::create()->findPk($cartOfColleague->getId())?->getCartAddressRelatedByAddressDeliveryId();
        self::assertSame($warehouse->getId(), $copy?->getAddressId());
    }

    public function testACustomerWithoutCompanyReadsOwnAddressesWithNoQueryOnTheCompanyTables(): void
    {
        $customer = $this->customer();
        $stranger = $this->customer();
        $own = $this->addressOf($customer, null, 'Own');
        $own->makeItDefault();
        $other = $this->addressOf($stranger, null, 'Other');
        $selection = $this->getService(CartSelectionService::class);
        $cart = $this->cartOf($customer);

        $inTheAccount = $this->recordSqlQueries(function () use ($customer, $own, $other, $selection, $cart): void {
            self::assertSame([$own->getId()], $this->idsOf($this->addressFacadeOf($customer)->getByCustomer()));
            self::assertSame($own->getId(), $this->access->find($customer, $own->getId())?->getId());
            self::assertNull($this->access->find($customer, $other->getId()));

            $selection->setInvoiceAddress(new CheckoutDTO($cart, invoiceAddressId: (int) $own->getId()));
            self::assertSame($own->getId(), $this->invoiceAddressOf($cart));

            $selection->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: (int) $other->getId()));
        });
        $inTheLoop = $this->recordSqlQueries(function () use ($customer, $own): void {
            self::assertSame([$own->getId()], array_keys($this->listedAs($customer, [])));
        });

        foreach ([$inTheAccount, $inTheLoop] as $statements) {
            $companyStatements = array_values(array_filter(
                $statements,
                static fn (string $statement): bool => 1 === preg_match('/(FROM|JOIN) `company(_[a-z_]+)?`/', $statement),
            ));

            foreach ($companyStatements as $statement) {
                self::assertStringContainsString('FROM `company_customer`', $statement);
                self::assertStringNotContainsString('FROM `company`', $statement);
                self::assertStringNotContainsString('FROM `company_role`', $statement);
            }

            self::assertCount(1, $companyStatements, 'The membership is read once, from the table of the buyers.');
        }
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<int>
     */
    private function idsOf(array $addresses): array
    {
        $ids = array_map(static fn (Address $address): int => (int) $address->getId(), $addresses);
        sort($ids);

        return $ids;
    }

    private function signIn(Customer $customer): void
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setCustomerUser($customer);
    }

    private function requestNaming(Address $address): Request
    {
        return Request::create('/delivery?addressId='.$address->getId());
    }

    private function formOf(int $countryId, bool $isDefault): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('getData')->willReturn([
            'label' => 'Edited',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => '1 Main Street',
            'address2' => '',
            'zipcode' => '75001',
            'city' => 'Paris',
            'country' => $countryId,
            'is_default' => $isDefault,
        ]);

        return $form;
    }

    private function dto(string $label): AddressDTO
    {
        return new AddressDTO($label, 'John', 'Doe', '1 Main Street', '75001', 'Paris', (int) $this->fixtures->country()->getId());
    }

    private function serviceFor(Customer $customer): AddressService
    {
        $session = $this->createStub(Session::class);
        $session->method('getCustomerUser')->willReturn($customer);

        return new AddressService($this->getService(EventDispatcherInterface::class), $session, $this->access);
    }

    /**
     * @param array<string, string> $arguments
     *
     * @return array<int, int> the default flag the loop shows, by address id
     */
    private function listedAs(Customer $customer, array $arguments): array
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setCustomerUser($customer);
        $this->getService('services_resetter')->reset();

        $listed = [];

        foreach ($this->getService(LoopExecutor::class)->execute('address', $arguments + ['order' => 'normal']) as $row) {
            $listed[(int) $row->getVarVal()['ID']] = (int) $row->getVarVal()['DEFAULT'];
        }

        ksort($listed);

        return $listed;
    }

    private function deliveryAddressOf(Cart $cart): ?int
    {
        return CartQuery::create()->findPk($cart->getId())?->getCartAddressRelatedByAddressDeliveryId()?->getAddressId();
    }

    private function invoiceAddressOf(Cart $cart): ?int
    {
        return CartQuery::create()->findPk($cart->getId())?->getCartAddressRelatedByAddressInvoiceId()?->getAddressId();
    }

    private function apiAddressOf(Cart $cart, int $addressId): Address
    {
        $processor = $this->getService(CheckoutSelectionProcessor::class);

        return (new \ReflectionMethod($processor, 'addressOfTheAccount'))->invoke($processor, $cart, $addressId);
    }

    private function virtualCartOf(Customer $customer): Cart
    {
        $cart = $this->cartOf($customer);
        $product = $this->fixtures->product($this->fixtures->category(), $this->fixtures->taxRule(), $this->fixtures->currency());
        $product->setVirtual(1)->save($this->getPropelConnection());
        $item = $this->fixtures->cartItem($cart, $product);
        $item->getProductSaleElements()->setWeight(0)->save($this->getPropelConnection());

        return CartQuery::create()->findPk($cart->getId()) ?? $cart;
    }
}

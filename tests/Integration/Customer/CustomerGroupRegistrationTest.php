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

namespace Thelia\Tests\Integration\Customer;

use Propel\Runtime\ActiveQuery\QueryExecutor\QueryExecutionException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateMinimalEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\CustomerFacade;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupHasNoRegistrationLinkException;
use Thelia\Domain\Customer\Exception\InvalidRegistrationTokenException;
use Thelia\Domain\Customer\Service\CustomerGroupRegistration;
use Thelia\Domain\Customer\Service\CustomerRegistrationService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerGroupTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The registration link of a group: its token is opened, regenerated and closed by the service, resolved
 * on the server at registration, and the one thing that moves a new account out of the default group.
 */
final class CustomerGroupRegistrationTest extends IntegrationTestCase
{
    private CustomerGroupRegistration $registration;
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registration = $this->getService(CustomerGroupRegistration::class);
        $this->factory = $this->createFixtureFactory();
    }

    public function testOpeningGeneratesASixtyFourHexadecimalTokenAndKeepsItWhenOpenAlready(): void
    {
        $group = $this->factory->customerGroup('pro');
        self::assertNull($this->stored($group)->getRegistrationToken());

        $token = $this->registration->open($group);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertSame($token, $this->stored($group)->getRegistrationToken());
        self::assertSame($token, $group->getRegistrationToken(), 'The group the caller holds is up to date.');
        self::assertSame($token, $this->registration->open($group), 'Opening an open link changes nothing.');
        self::assertSame($token, $this->registration->open($this->stored($group)));
    }

    public function testOpeningAnObjectThatPredatesTheTokenReturnsTheTokenOfTheRow(): void
    {
        $group = $this->factory->customerGroup('stale');
        $stale = CustomerGroupQuery::create()->findPk($group->getId());
        $token = $this->registration->open($group);
        CustomerGroupTableMap::clearInstancePool();
        $staleOnly = CustomerGroupQuery::create()->findPk($group->getId());
        self::assertSame($token, $staleOnly->getRegistrationToken());
        self::assertNull($stale->getRegistrationToken());

        self::assertSame($token, $this->registration->open($stale), 'Two openers end up with the same token.');
    }

    public function testRegeneratingReplacesTheTokenAndTheOldOneOpensNothing(): void
    {
        $group = $this->factory->customerGroup('pro');
        $first = $this->registration->open($group);

        $second = $this->registration->regenerate($group);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $second);
        self::assertNotSame($first, $second);
        self::assertNull($this->registration->groupForToken($first));
        self::assertSame($group->getId(), $this->registration->groupForToken($second)?->getId());
    }

    public function testRegeneratingAClosedLinkOpensIt(): void
    {
        $group = $this->factory->customerGroup('closed');

        $token = $this->registration->regenerate($group);

        self::assertSame($group->getId(), $this->registration->groupForToken($token)?->getId());
    }

    public function testClosingRemovesTheTokenEvenFromAnObjectThatPredatesIt(): void
    {
        $group = $this->factory->customerGroup('pro');
        $stale = CustomerGroupQuery::create()->findPk($group->getId());
        $token = $this->registration->open($group);
        CustomerGroupTableMap::clearInstancePool();

        $this->registration->close($stale);

        self::assertNull($this->stored($group)->getRegistrationToken());
        self::assertNull($stale->getRegistrationToken());
        self::assertNull($this->registration->groupForToken($token));

        $this->registration->close($group);
        self::assertNull($this->stored($group)->getRegistrationToken(), 'Closing a closed link is harmless.');
    }

    public function testTheDefaultGroupGetsNoLink(): void
    {
        $default = CustomerGroupQuery::findDefault();

        foreach ([$this->registration->open(...), $this->registration->regenerate(...)] as $attempt) {
            try {
                $attempt($default);
                self::fail('The default group has no link.');
            } catch (DefaultCustomerGroupHasNoRegistrationLinkException) {
            }
        }

        self::assertNull($this->stored($default)->getRegistrationToken());
    }

    public function testAGroupThatBecomesTheDefaultLosesItsLink(): void
    {
        $group = $this->factory->customerGroup('promoted', openRegistration: true);
        $token = (string) $group->getRegistrationToken();

        $this->stored($group)->setIsDefault(1)->save();

        self::assertNull($this->stored($group)->getRegistrationToken());
        self::assertNull($this->registration->groupForToken($token));
    }

    public function testTwoGroupsCannotShareAToken(): void
    {
        $first = $this->factory->customerGroup('first', openRegistration: true);
        $second = $this->factory->customerGroup('second');

        $this->expectException(QueryExecutionException::class);
        $second->setRegistrationToken((string) $first->getRegistrationToken())->save();
    }

    public function testAFixtureOpensTheLinkOfAGroup(): void
    {
        $open = $this->factory->customerGroup('open', openRegistration: true);
        $closed = $this->factory->customerGroup('closed');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $open->getRegistrationToken());
        self::assertSame($open->getId(), $this->registration->groupForToken((string) $open->getRegistrationToken())?->getId());
        self::assertNull($closed->getRegistrationToken());
    }

    public function testAnUnknownTokenOpensNoGroup(): void
    {
        $this->factory->customerGroup('pro', openRegistration: true);

        self::assertNull($this->registration->groupForToken(bin2hex(random_bytes(32))));
    }

    public function testTheRegistrationPutsTheNewAccountInTheGroupOfTheToken(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $token = $this->registration->open($pro);

        $customer = $this->getService(CustomerRegistrationService::class)->registerCustomer(
            new CustomerRegisterDTO(firstname: 'Pro', lastname: 'Member', email: 'pro.member@test.com', password: 'secret123'),
            $token,
        );

        self::assertSame($pro->getId(), $this->reload($customer->getId())->getCustomerGroupId());
    }

    public function testTheFacadeRegistersThroughTheTokenToo(): void
    {
        $pro = $this->factory->customerGroup('pro', openRegistration: true);

        $customer = $this->getService(CustomerFacade::class)->register(
            new CustomerRegisterDTO(firstname: 'Pro', lastname: 'Facade', email: 'pro.facade@test.com', password: 'secret123'),
            $pro->getRegistrationToken(),
        );

        self::assertSame($pro->getId(), $this->reload($customer->getId())->getCustomerGroupId());
    }

    public function testTheRegistrationWithoutATokenKeepsTheDefaultGroup(): void
    {
        $this->factory->customerGroup('pro', openRegistration: true);

        $customer = $this->getService(CustomerRegistrationService::class)->registerCustomer(
            new CustomerRegisterDTO(firstname: 'Plain', lastname: 'Member', email: 'plain.member@test.com', password: 'secret123'),
        );

        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $this->reload($customer->getId())->getCustomerGroupId());
    }

    public function testATokenThatOpensNothingRefusesTheRegistrationAndCreatesNothing(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $stale = $this->registration->open($pro);
        $this->registration->regenerate($pro);
        $closed = $this->factory->customerGroup('closed', openRegistration: true);
        $closedToken = (string) $closed->getRegistrationToken();
        $this->registration->close($closed);

        foreach ([$stale, $closedToken, '', 'garbage', strtoupper($stale)] as $token) {
            try {
                $this->getService(CustomerRegistrationService::class)->registerCustomer(
                    new CustomerRegisterDTO(firstname: 'Never', lastname: 'Created', email: 'never.created@test.com', password: 'secret123'),
                    $token,
                );
                self::fail('A token that opens no group refuses the registration: '.$token);
            } catch (InvalidRegistrationTokenException $exception) {
                self::assertSame('This registration link is not valid.', $exception->getMessage());
            }
        }

        self::assertNull(CustomerQuery::create()->findOneByEmail('never.created@test.com'));
    }

    public function testAGroupPostedWithTheRegistrationDataStillIsIgnoredWhenATokenIsKnown(): void
    {
        $sneaky = $this->factory->customerGroup('sneaky');
        $pro = $this->factory->customerGroup('pro', openRegistration: true);

        $event = new CustomerCreateOrUpdateMinimalEvent();
        $event
            ->bindArray([
                'customerGroupId' => $sneaky->getId(),
                'customer_group_id' => $sneaky->getId(),
                'customerGroup' => $sneaky->getId(),
                'registrationToken' => $pro->getRegistrationToken(),
            ])
            ->setTitle($this->factory->customerTitle()->getId())
            ->setFirstname('Sneaky')
            ->setLastname('Signup')
            ->setEmail('sneaky.token@test.com')
            ->setPassword('secret123');
        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        self::assertNull($event->getCustomerGroupId());
        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $this->reload($event->getCustomer()->getId())->getCustomerGroupId());
    }

    public function testAGuestRecordCompletedByTheRegistrationKeepsItsGroup(): void
    {
        $pro = $this->factory->customerGroup('pro', openRegistration: true);
        $guest = $this->factory->guestCustomer($this->factory->customerTitle(), ['email' => 'guest.record@test.com']);
        $guestGroupId = $guest->getCustomerGroupId();

        $customer = $this->getService(CustomerRegistrationService::class)->registerCustomer(
            new CustomerRegisterDTO(firstname: 'Guest', lastname: 'Record', email: 'guest.record@test.com', password: 'secret123'),
            $pro->getRegistrationToken(),
        );

        self::assertSame($guest->getId(), $customer->getId(), 'The record is completed, not duplicated.');
        self::assertSame($guestGroupId, $this->reload($customer->getId())->getCustomerGroupId());
    }

    private function stored(CustomerGroup $group): CustomerGroup
    {
        CustomerGroupTableMap::clearInstancePool();

        return CustomerGroupQuery::create()->findPk($group->getId()) ?? throw new \LogicException('The group is gone.');
    }

    private function reload(int $customerId): Customer
    {
        CustomerTableMap::clearInstancePool();

        return CustomerQuery::create()->findPk($customerId) ?? throw new \LogicException('The customer is gone.');
    }
}

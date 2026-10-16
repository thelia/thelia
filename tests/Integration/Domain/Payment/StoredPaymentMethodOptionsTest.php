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

namespace Thelia\Tests\Integration\Domain\Payment;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\PaymentModuleOptionEvent;
use Thelia\Api\Resource\PaymentModule;
use Thelia\Api\Resource\PaymentModuleOptionGroup;
use Thelia\Api\Resource\PaymentModuleStoredMethodOption;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Module\Payment\PaymentModuleService;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Domain\Payment\EventListener\StoredPaymentMethodOptionsListener;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;
use Thelia\Tests\Support\Payment\UsesStoredMethodsPaymentModule;

/**
 * The stored payment methods offered next to the module that keeps them, in the payment
 * module listing of the checkout.
 */
final class StoredPaymentMethodOptionsTest extends IntegrationTestCase
{
    use UsesStoredMethodsPaymentModule;

    private FixtureFactory $factory;

    private Module $module;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->module = $this->installTheStoredMethodsPaymentModule(static::getContainer(), $this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->uninstallTheStoredMethodsPaymentModule(static::getContainer());

        parent::tearDown();
    }

    public function testTheUsableMethodsOfTheBuyerAreOfferedWithTheChoiceOfANewOne(): void
    {
        $customer = $this->customer();
        $usable = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31', default: true);
        $this->storedMethodRow($customer, $this->module, 'Mastercard ending 1111', '2020-01-31');
        $this->storedMethodRow($customer, $this->chequeModule(), 'Mandate FR76 …', null);

        $group = $this->theStoredMethodsGroup($this->optionGroupsFor($this->factory->cart($customer)));

        self::assertSame(1, $group->getMinimumSelectedOptions());
        self::assertSame(1, $group->getMaximumSelectedOptions());

        $options = $group->getOptions();
        self::assertCount(2, $options, 'The expired method and the method of another module are left out.');

        [$stored, $new] = $options;
        self::assertInstanceOf(PaymentModuleStoredMethodOption::class, $stored);
        self::assertSame('stored_method_'.$usable->getId(), $stored->getCode());
        self::assertSame($usable->getId(), $stored->getCustomerPaymentMethodId());
        self::assertSame('Visa ending 4242', $stored->getTitle());
        self::assertSame('2030-12-31', $stored->getExpiresAt());
        self::assertTrue($stored->isDefault());

        self::assertInstanceOf(PaymentModuleStoredMethodOption::class, $new);
        self::assertSame(PaymentModuleStoredMethodOption::NEW_METHOD_CODE, $new->getCode());
        self::assertNull($new->getCustomerPaymentMethodId());
    }

    /**
     * The expiry is the last day the method pays: on that day it is still offered.
     */
    public function testAMethodExpiringTodayIsStillOffered(): void
    {
        $customer = $this->customer();
        $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', (new \DateTimeImmutable('today'))->format('Y-m-d'));

        self::assertCount(2, $this->theStoredMethodsGroup($this->optionGroupsFor($this->factory->cart($customer)))->getOptions());
    }

    public function testAGuestIsOfferedNoStoredMethod(): void
    {
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());
        $this->storedMethodRow($guest, $this->module, 'Visa ending 4242', '2030-12-31');

        self::assertSame([], $this->optionGroupsFor($this->factory->cart($guest)));
    }

    public function testAModuleThatKeepsNoMethodOffersNone(): void
    {
        $customer = $this->customer();
        $cheque = $this->chequeModule();
        $this->storedMethodRow($customer, $cheque, 'Mandate FR76 …', null);

        self::assertSame([], $this->optionGroupsFor($this->factory->cart($customer), $cheque));
    }

    public function testAModuleThatNoLongerOffersStoredMethodsOffersNone(): void
    {
        $customer = $this->customer();
        $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        StoredMethodsPaymentModule::$offersStoredMethods = false;

        self::assertSame([], $this->optionGroupsFor($this->factory->cart($customer)));
    }

    public function testABuyerWithNoUsableMethodHasNothingToChooseBetween(): void
    {
        $customer = $this->customer();
        $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2020-01-31');

        self::assertSame([], $this->optionGroupsFor($this->factory->cart($customer)));
    }

    /**
     * The listing resolves a module by the rule the registration does: a module that may
     * register a stored method is offered with it, whether or not the compiled container
     * knows it, a module recorded after the compilation for instance.
     */
    public function testAModuleOutsideTheContainerIsOfferedAsTheRegistrationResolvesIt(): void
    {
        $customer = $this->customer();
        $recorded = (new Module())
            ->setCode(StoredMethodsPaymentModule::getModuleCode().'Recorded')
            ->setFullNamespace(StoredMethodsPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $recorded->save($this->getPropelConnection());
        $this->storedMethodRow($customer, $recorded, 'Visa ending 4242', '2030-12-31');

        self::assertCount(2, $this->theStoredMethodsGroup($this->optionGroupsFor($this->factory->cart($customer), $recorded))->getOptions());
        self::assertTrue($this->getService(CustomerPaymentMethodService::class)->offersStoredMethods($recorded));
    }

    /**
     * Through the listing the checkout reads, down to what the serializer writes: the
     * option carries what the buyer recognizes the method by, and never the reference the
     * provider keeps it under.
     */
    public function testTheListingSerializesTheOptionsWithoutTheProviderReference(): void
    {
        $customer = $this->customer();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31', default: true);

        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        // The listing reads the cart of the session, which hands back only a cart of the
        // customer signed in.
        $session->setCustomerUser($customer);
        $session->setSessionCart($this->factory->cart($customer));

        try {
            $modules = $this->getService(PaymentModuleService::class)->getPaymentModules($this->module->getId());
        } finally {
            $session->setSessionCart(null);
            $session->setCustomerUser(null);
        }

        self::assertCount(1, $modules);

        $normalized = static::getContainer()->get('serializer')->normalize(
            $modules[0],
            'json',
            ['groups' => [PaymentModule::GROUP_FRONT_READ]],
        );

        self::assertSame(StoredPaymentMethodOptionsListener::GROUP_CODE, $normalized['optionGroups'][0]['code']);
        self::assertSame(
            [
                'customerPaymentMethodId' => $method->getId(),
                'expiresAt' => '2030-12-31',
                'default' => true,
                'code' => 'stored_method_'.$method->getId(),
                'valid' => true,
                'title' => 'Visa ending 4242',
                'description' => '',
                'image' => '',
            ],
            $normalized['optionGroups'][0]['options'][0],
        );
        self::assertStringNotContainsString(
            (string) $method->getToken(),
            json_encode($normalized, \JSON_THROW_ON_ERROR),
            'The provider reference is nowhere in the listing.',
        );
    }

    /**
     * @return array<int, PaymentModuleOptionGroup>
     */
    private function optionGroupsFor(Cart $cart, ?Module $module = null): array
    {
        $event = new PaymentModuleOptionEvent($module ?? $this->module, $cart);
        $this->dispatcher()->dispatch($event, TheliaEvents::MODULE_PAYMENT_GET_OPTIONS);

        return $event->getPaymentModuleOptionGroups();
    }

    /**
     * @param array<int, PaymentModuleOptionGroup> $groups
     */
    private function theStoredMethodsGroup(array $groups): PaymentModuleOptionGroup
    {
        self::assertCount(1, $groups);
        self::assertSame(StoredPaymentMethodOptionsListener::GROUP_CODE, $groups[0]->getCode());

        return $groups[0];
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function chequeModule(): Module
    {
        return ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No Cheque module installed — run bin/test-prepare.');
    }

    private function listen(string $eventName, callable $listener, int $priority = 512): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}

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

namespace Thelia\Tests\Integration\Domain\Module;

use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Domain\Module\Payment\PaymentModuleService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The payment modules offered to the customer of the session cart, by the API provider
 * (PaymentModuleService) and by the payment loop, are the ones on the list of the group of
 * that customer, or every module while that group has no list. The session is what the theme reads them through: a bearer
 * token call of the front API has none, and is answered 400 before this code is reached.
 */
final class PaymentModuleCustomerGroupTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<callable> */
    private array $listeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->listeners as $listener) {
            static::getContainer()->get('event_dispatcher')->removeListener(TheliaEvents::MODULE_PAYMENT_IS_VALID, $listener);
        }
        $this->listeners = [];
        $this->session()->setSessionCart(null);
        $this->session()->setCustomerUser(null);
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();

        parent::tearDown();
    }

    public function testTheApiListingOfAGroupWithAListOffersThatListOnly(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $this->signInAsCustomerOf($pro);

        self::assertSame([(int) $cheque->getId()], $this->apiModuleIds());
        self::assertNotContains((int) $other->getId(), $this->apiModuleIds());
    }

    public function testNoListOffersEveryModule(): void
    {
        [$first, $second] = $this->twoPaymentModules();
        $this->factory->customerGroup('pro');
        $this->signInAsCustomerOf();

        $offered = $this->apiModuleIds();

        self::assertContains((int) $first->getId(), $offered);
        self::assertContains((int) $second->getId(), $offered);
    }

    public function testTheApiListingOfAVisitorWithNoAccountIsTheDefaultGroupOne(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $this->session()->setSessionCart($this->factory->cart());

        $offered = $this->apiModuleIds();

        self::assertContains((int) $cheque->getId(), $offered, 'The list of pro closes nothing to the default group.');
        self::assertContains((int) $other->getId(), $offered);
    }

    public function testTwoCustomersOfDifferentGroupsAreOfferedDifferentModules(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);

        $this->signInAsCustomerOf($pro);
        $seenByTheProfessional = $this->apiModuleIds();
        $this->signInAsCustomerOf();
        $seenByTheConsumer = $this->apiModuleIds();

        self::assertNotContains((int) $other->getId(), $seenByTheProfessional);
        self::assertContains((int) $other->getId(), $seenByTheConsumer);
        self::assertContains((int) $cheque->getId(), $seenByTheConsumer);
    }

    public function testTheListingsOfAGroupWhoseOnlyListedModuleWasDeletedAreEmptyNotOpen(): void
    {
        $listed = $this->factory->paymentModule();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $listed->getId()]);
        ModuleQuery::create()->filterById($listed->getId())->delete($this->getPropelConnection());
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();
        $this->signInAsCustomerOf($pro);
        $this->answerEveryModuleValid();

        self::assertSame([], $this->apiModuleIds());
        self::assertSame([], $this->loopModuleIds());
    }

    public function testTheLoopOfAGroupWithAListShowsThatListOnly(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $this->signInAsCustomerOf($pro);
        $this->answerEveryModuleValid();

        self::assertSame([(int) $cheque->getId()], $this->loopModuleIds());
        self::assertNotContains((int) $other->getId(), $this->loopModuleIds());
    }

    public function testTheLoopOfTheDefaultGroupWithNoListShowsEveryModule(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $this->signInAsCustomerOf();
        $this->answerEveryModuleValid();

        self::assertContains((int) $cheque->getId(), $this->loopModuleIds());
        self::assertContains((int) $other->getId(), $this->loopModuleIds());
    }

    /**
     * @return list<int>
     */
    private function apiModuleIds(): array
    {
        return array_map(
            static fn ($module): int => (int) $module->getId(),
            $this->getService(PaymentModuleService::class)->getPaymentModules(),
        );
    }

    /**
     * @return list<int>
     */
    private function loopModuleIds(): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $result = $this->getService(LoopExecutor::class)->execute('payment', ['lang' => $lang->getId()]);

        $ids = [];
        foreach ($result as $row) {
            $ids[] = (int) $row->getVarVal()['ID'];
        }

        return $ids;
    }

    private function signInAsCustomerOf(?CustomerGroup $group = null): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), null === $group ? [] : ['customerGroup' => $group]);

        // Reread, as the session of a signed-in customer carries the row.
        $this->session()->setCustomerUser($customer);
        $this->session()->setSessionCart($this->factory->cart($customer));

        return $customer;
    }

    private function answerEveryModuleValid(): void
    {
        $listener = static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        };
        static::getContainer()->get('event_dispatcher')->addListener(TheliaEvents::MODULE_PAYMENT_IS_VALID, $listener, 512);
        $this->listeners[] = $listener;
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }

    /**
     * @return array{0: Module, 1: Module}
     */
    private function twoPaymentModules(): array
    {
        $modules = ModuleQuery::create()->filterByType(3)->filterByActivate(1)->orderById()->find()->getData();

        if (\count($modules) < 2) {
            self::markTestSkipped('Two active payment modules are needed — run bin/test-prepare.');
        }

        return [$modules[0], $modules[1]];
    }
}

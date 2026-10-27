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

namespace Thelia\Tests\Integration\Domain\Cart;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupPaymentModule;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The payment module the cart names must be on the list of the group of the customer the
 * cart belongs to, when that group has one, even when a module answers the validity of the
 * payment on its own and stops the event: the judge of the event is no place for this rule.
 */
final class CartGuardCustomerGroupPaymentTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CartGuard $guard;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->guard = $this->getService(CartGuard::class);
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();

        parent::tearDown();
    }

    public function testAModuleOffTheListOfTheGroupIsRefusedEvenWhenAListenerAcceptsAndStopsTheEvent(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $this->answerPaymentValidityWithYes();
        $cart = $this->cartPaidWith($other, $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]));

        $this->expectException(InvalidPaymentException::class);
        $this->guard->checkValidPayment($cart);
    }

    public function testAModuleOnTheListOfTheGroupIsAccepted(): void
    {
        [$cheque] = $this->twoPaymentModules();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $this->answerPaymentValidityWithYes();
        $cart = $this->cartPaidWith($cheque, $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]));

        $this->guard->checkValidPayment($cart);

        $this->addToAssertionCount(1);
    }

    public function testTheDefaultGroupWithNoListPaysWithAnyModuleWhateverTheOthersList(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        $this->factory->customerGroup('pro', paymentModuleIds: [(int) $cheque->getId()]);
        $this->answerPaymentValidityWithYes();

        $this->guard->checkValidPayment($this->cartPaidWith($other, $this->factory->customer($this->factory->customerTitle())));
        $this->guard->checkValidPayment($this->cartPaidWith($other, null));

        $this->addToAssertionCount(1);
    }

    public function testTheCartOfAVisitorIsJudgedAsTheDefaultGroup(): void
    {
        [$cheque, $other] = $this->twoPaymentModules();
        (new CustomerGroupPaymentModule())
            ->setCustomerGroupId((int) CustomerGroupQuery::findDefault()->getId())
            ->setModuleId((int) $cheque->getId())
            ->save();
        CustomerGroupQuery::findDefault()->setRestrictsPaymentModules(1)->save();
        $this->answerPaymentValidityWithYes();
        $cart = $this->cartPaidWith($other, null);

        $this->expectException(InvalidPaymentException::class);
        $this->guard->checkValidPayment($cart);
    }

    private function cartPaidWith(Module $module, ?Customer $customer): Cart
    {
        $cart = $this->factory->cart($customer);
        $cart->setPaymentModuleId($module->getId())->save();

        return $cart;
    }

    /**
     * @return array{0: Module, 1: Module}
     */
    private function twoPaymentModules(): array
    {
        $modules = ModuleQuery::create()->filterByType(3)->filterByActivate(1)->orderById()->limit(2)->find()->getData();

        if (2 !== \count($modules)) {
            throw new \RuntimeException('Two payment modules are needed — run bin/test-prepare.');
        }

        return [$modules[0], $modules[1]];
    }

    private function answerPaymentValidityWithYes(): void
    {
        $listener = static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        };
        $this->dispatcher()->addListener(TheliaEvents::MODULE_PAYMENT_IS_VALID, $listener, 512);
        $this->registeredListeners[] = [TheliaEvents::MODULE_PAYMENT_IS_VALID, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }
}

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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Customer\CustomerPaymentMethodEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

/**
 * Deleting a customer drops their stored payment methods with the account: the
 * providers are told, and the deletion of each method is announced, the way the
 * anonymization does it, instead of the rows vanishing under the foreign key cascade.
 */
final class CustomerDeletionForgetsStoredPaymentMethodsTest extends ActionIntegrationTestCase
{
    /**
     * @var list<callable>
     */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        StoredMethodsPaymentModule::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as $listener) {
            $this->dispatcher->removeListener(TheliaEvents::CUSTOMER_PAYMENT_METHOD_DELETE, $listener);
        }
        $this->registeredListeners = [];
        StoredMethodsPaymentModule::reset();

        parent::tearDown();
    }

    public function testDeletingACustomerTellsTheProviderAndAnnouncesTheDeletion(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $method = $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $this->paymentModuleRow(), 'pm_1', 'Visa ending 1111', null);
        $methodId = (int) $method->getId();

        $announced = [];
        $listener = static function (CustomerPaymentMethodEvent $event) use (&$announced): void {
            $announced[] = (int) $event->getPaymentMethod()->getId();
        };
        $this->dispatcher->addListener(TheliaEvents::CUSTOMER_PAYMENT_METHOD_DELETE, $listener);
        $this->registeredListeners[] = $listener;

        $this->dispatch(new CustomerEvent($customer), TheliaEvents::CUSTOMER_DELETEACCOUNT);

        self::assertNull(CustomerQuery::create()->findPk($customer->getId()), 'The customer is gone.');
        self::assertNull(CustomerPaymentMethodQuery::create()->findPk($methodId), 'Their stored method with them.');
        self::assertSame([$methodId], StoredMethodsPaymentModule::$forgottenMethodIds, 'The provider was asked to forget it.');
        self::assertSame([$methodId], $announced, 'Its deletion was announced.');
    }

    private function paymentModuleRow(): Module
    {
        $code = StoredMethodsPaymentModule::getModuleCode();
        $existing = ModuleQuery::create()->findOneByCode($code);

        if (null !== $existing) {
            return $existing;
        }

        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace(StoredMethodsPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }
}

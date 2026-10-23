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

use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\DeferredForgettingStoredMethodsPaymentModule;

/**
 * The anonymization and the deletion of a customer drop the stored methods first and
 * tell the providers once committed. A module that needs data going with the row reads
 * it before the drop, and its provider is then told through the call it prepared.
 */
final class StoredMethodForgottenAfterItsDropTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DeferredForgettingStoredMethodsPaymentModule::reset();
    }

    protected function tearDown(): void
    {
        DeferredForgettingStoredMethodsPaymentModule::reset();

        parent::tearDown();
    }

    public function testTheAnonymizationLetsTheModuleReadTheMethodBeforeItsDrop(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $methodId = $this->storedMethodOf($customer);

        $this->dispatch(new CustomerAnonymizeEvent($customer), TheliaEvents::CUSTOMER_ANONYMIZE);

        $this->assertForgottenThroughThePreparedCall($methodId);
    }

    public function testTheDeletionOfTheCustomerLetsTheModuleReadTheMethodBeforeItsDrop(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $methodId = $this->storedMethodOf($customer);

        $this->dispatch(new CustomerEvent($customer), TheliaEvents::CUSTOMER_DELETEACCOUNT);

        $this->assertForgottenThroughThePreparedCall($methodId);
    }

    private function assertForgottenThroughThePreparedCall(int $methodId): void
    {
        self::assertNull(CustomerPaymentMethodQuery::create()->findPk($methodId), 'The method is dropped.');
        self::assertSame(
            [$methodId => true],
            DeferredForgettingStoredMethodsPaymentModule::$storedWhenPrepared,
            'The module prepared while the method was still stored.',
        );
        self::assertSame(
            [$methodId => false],
            DeferredForgettingStoredMethodsPaymentModule::$storedWhenForgotten,
            'Its prepared call ran once the drop was committed.',
        );
        self::assertSame(
            [],
            DeferredForgettingStoredMethodsPaymentModule::$forgottenWithoutPreparation,
            'The prepared call replaces the plain one.',
        );
    }

    private function storedMethodOf(Customer $customer): int
    {
        return (int) $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $this->paymentModuleRow(), 'pm_deferred', 'Visa ending 4242', null)
            ->getId();
    }

    private function paymentModuleRow(): Module
    {
        $code = DeferredForgettingStoredMethodsPaymentModule::getModuleCode();
        $existing = ModuleQuery::create()->findOneByCode($code);

        if (null !== $existing) {
            return $existing;
        }

        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace(DeferredForgettingStoredMethodsPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }
}

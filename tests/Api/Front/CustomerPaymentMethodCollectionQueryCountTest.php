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

namespace Thelia\Tests\Api\Front;

use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

/**
 * Every member of the customer's stored payment methods names the code and the title of
 * its module. Read method by method, that is two queries more per method on the page.
 */
final class CustomerPaymentMethodCollectionQueryCountTest extends ApiTestCase
{
    use ForgetsPooledModels;
    use RecordsSqlQueries;

    protected function setUp(): void
    {
        parent::setUp();

        StoredMethodsPaymentModule::reset();
    }

    protected function tearDown(): void
    {
        StoredMethodsPaymentModule::reset();

        parent::tearDown();
    }

    /**
     * Three methods through three modules rule out the instance pool quietly hiding a
     * query per row behind rows that all point at the same module.
     */
    public function testTheListReadsTheModulesAndTheirTitlesOnceWhateverTheNumberOfMethods(): void
    {
        $factory = $this->createFixtureFactory();
        $service = $this->getService(CustomerPaymentMethodService::class);
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $expected = [];

        foreach (['A', 'B', 'C'] as $suffix) {
            $module = $this->paymentModuleRow(StoredMethodsPaymentModule::getModuleCode().'Count'.$suffix, 'Provider '.$suffix);
            $method = $service->register($customer, $module, 'pm_'.$suffix, 'Visa ending 000'.$suffix, null);
            $expected[(int) $method->getId()] = [(string) $module->getCode(), 'Provider '.$suffix];
        }

        $token = $this->authenticateAsCustomer($customer);

        $response = null;
        $statements = $this->recordSqlQueriesWithoutPooledModels(function () use (&$response, $token): void {
            $response = $this->jsonRequest('GET', '/api/front/account/payment_methods', token: $token);
        });

        self::assertJsonResponseSuccessful($response);

        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [];
        self::assertCount(3, $members);

        foreach ($members as $member) {
            self::assertSame(
                $expected[$member['id']] ?? null,
                [$member['paymentModuleCode'] ?? null, $member['paymentModuleTitle'] ?? null],
                'Each method must still name its own module.',
            );
        }

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'module'),
            'The modules of the page must be read in one statement, not one by one.',
        );
        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'module_i18n'),
            'The module titles of the page must be read in one statement, not one by one.',
        );
    }

    /**
     * The owner is the admin listing's business: the front returns it nowhere, so it
     * does not build it for each method either.
     */
    public function testTheListDoesNotBuildTheOwnerOfEachMethod(): void
    {
        $factory = $this->createFixtureFactory();
        $service = $this->getService(CustomerPaymentMethodService::class);
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $module = $this->paymentModuleRow(StoredMethodsPaymentModule::getModuleCode().'Owner', 'Provider');

        foreach (['A', 'B', 'C'] as $suffix) {
            $service->register($customer, $module, 'pm_'.$suffix, 'Visa ending 000'.$suffix, null);
        }

        $token = $this->authenticateAsCustomer($customer);

        $builtCustomers = 0;
        $countBuiltCustomers = static function (ModelToResourceEvent $event) use (&$builtCustomers): void {
            if ($event->getResource() instanceof CustomerResource) {
                ++$builtCustomers;
            }
        };
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(ModelToResourceEvent::AFTER_TRANSFORM, $countBuiltCustomers);

        try {
            $response = $this->jsonRequest('GET', '/api/front/account/payment_methods', token: $token);
        } finally {
            $dispatcher->removeListener(ModelToResourceEvent::AFTER_TRANSFORM, $countBuiltCustomers);
        }

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(3, $response);
        self::assertSame(0, $builtCustomers);
    }

    private function paymentModuleRow(string $code, string $title): Module
    {
        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace(StoredMethodsPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setTitle($title);
        $module->save($this->getPropelConnection());

        return $module;
    }
}

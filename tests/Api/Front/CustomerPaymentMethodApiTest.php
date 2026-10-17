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

use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

/**
 * /api/front/account/payment_methods: a customer's own stored payment
 * methods, without their provider token.
 *
 * Ownership is enforced by CustomerPaymentMethodOwnershipExtension rather
 * than by the `security:` expression, which only asks for a customer, so
 * another customer's id reads as 404, never 403 — see the resource's own
 * docblock for why.
 */
final class CustomerPaymentMethodApiTest extends ApiTestCase
{
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

    public function testCollectionOnlyListsTheAuthenticatedCustomersMethods(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $victim = $factory->customer($factory->customerTitle());
        $service->register($victim, $module, 'pm_victim', 'Visa ending 1111', null);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $service->register($customer, $module, 'pm_owner', 'Visa ending 2222', null);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/payment_methods', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
    }

    public function testCustomerCannotReadAnotherCustomersPaymentMethod(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $victim = $factory->customer($factory->customerTitle());
        $victimMethod = $service->register($victim, $module, 'pm_victim', 'Visa ending 1111', null);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/payment_methods/'.$victimMethod->getId(), token: $token);

        // Not 403: a foreign id must read exactly like a missing one.
        self::assertSame(404, $response->getStatusCode());
    }

    public function testCustomerCannotDeleteAnotherCustomersPaymentMethod(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $victim = $factory->customer($factory->customerTitle());
        $victimMethod = $service->register($victim, $module, 'pm_victim', 'Visa ending 1111', null);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('DELETE', '/api/front/account/payment_methods/'.$victimMethod->getId(), token: $token);

        self::assertSame(404, $response->getStatusCode());
        self::assertNotNull(
            CustomerPaymentMethodQuery::create()->findPk($victimMethod->getId()),
            'The row must survive an attempt by someone who does not own it.',
        );
        self::assertSame([], StoredMethodsPaymentModule::$forgottenMethodIds, 'The module must not even be asked.');
    }

    public function testCustomerCannotSetAnotherCustomersMethodAsDefault(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $victim = $factory->customer($factory->customerTitle());
        $victimMethod = $service->register($victim, $module, 'pm_victim', 'Visa ending 1111', null);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/front/account/payment_methods/'.$victimMethod->getId(),
            ['isDefault' => true],
            $token,
            format: 'merge-patch+json',
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeletingOwnMethodCallsTheModuleAndRemovesIt(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $method = $service->register($customer, $module, 'pm_owner', 'Visa ending 2222', null);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('DELETE', '/api/front/account/payment_methods/'.$method->getId(), token: $token);

        self::assertContains($response->getStatusCode(), [200, 204]);
        self::assertNull(CustomerPaymentMethodQuery::create()->findPk($method->getId()));
        self::assertSame([$method->getId()], StoredMethodsPaymentModule::$forgottenMethodIds);
    }

    public function testDeletingAMethodTheProviderRefusesKeepsItAndAnswers409(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $method = $service->register($customer, $module, 'pm_owner', 'Visa ending 2222', null);
        $token = $this->authenticateAsCustomer($customer);

        StoredMethodsPaymentModule::$providerRefusesToForget = true;

        $response = $this->jsonRequest('DELETE', '/api/front/account/payment_methods/'.$method->getId(), token: $token);

        self::assertSame(409, $response->getStatusCode());
        self::assertNotNull(
            CustomerPaymentMethodQuery::create()->findPk($method->getId()),
            'A refusal from the provider must keep the row.',
        );
    }

    public function testPatchingIsDefaultTrueLeavesAtMostOneDefault(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $first = $service->register($customer, $module, 'pm_first', 'Visa ending 1111', null);
        $second = $service->register($customer, $module, 'pm_second', 'Visa ending 2222', null);
        self::assertTrue(CustomerPaymentMethodQuery::create()->findPk($first->getId())->getIsDefault());
        self::assertFalse(CustomerPaymentMethodQuery::create()->findPk($second->getId())->getIsDefault());

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/front/account/payment_methods/'.$second->getId(),
            ['isDefault' => true],
            $token,
            format: 'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);
        self::assertFalse(CustomerPaymentMethodQuery::create()->findPk($first->getId())->getIsDefault());
        self::assertTrue(CustomerPaymentMethodQuery::create()->findPk($second->getId())->getIsDefault());
    }

    public function testPatchingIsDefaultFalseIsRejected(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $method = $service->register($customer, $module, 'pm_owner', 'Visa ending 2222', null);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/front/account/payment_methods/'.$method->getId(),
            ['isDefault' => false],
            $token,
            format: 'merge-patch+json',
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertTrue(CustomerPaymentMethodQuery::create()->findPk($method->getId())->getIsDefault());
    }

    public function testAGuestCannotReachTheCollection(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/account/payment_methods');

        // Firewall-level rejection of the /front/account prefix: no token at all
        // answers 401, an authenticated-but-not-customer token answers 403. Either
        // way the resource's own logic, and the token it would have exposed, is
        // never reached.
        self::assertContains($response->getStatusCode(), [401, 403]);
    }

    /**
     * The token is the one thing this API must never leak, success or failure.
     */
    public function testNoResponseEverContainsTheProviderToken(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $method = $service->register($customer, $module, 'pm_super_secret_token_value', 'Visa ending 2222', null);
        $token = $this->authenticateAsCustomer($customer);

        $secret = 'pm_super_secret_token_value';

        $collectionResponse = $this->jsonRequest('GET', '/api/front/account/payment_methods', token: $token);
        $itemResponse = $this->jsonRequest('GET', '/api/front/account/payment_methods/'.$method->getId(), token: $token);

        self::assertStringNotContainsString($secret, (string) $collectionResponse->getContent());
        self::assertStringNotContainsString($secret, (string) $itemResponse->getContent());

        StoredMethodsPaymentModule::$providerRefusesToForget = true;
        $refusedDeleteResponse = $this->jsonRequest('DELETE', '/api/front/account/payment_methods/'.$method->getId(), token: $token);
        self::assertSame(409, $refusedDeleteResponse->getStatusCode());
        self::assertStringNotContainsString($secret, (string) $refusedDeleteResponse->getContent());
    }

    /**
     * The module is named by its code and its title, which is what the account page
     * shows; no link to the module resource, which only the back-office can follow.
     */
    public function testAMethodNamesItsModuleByCodeAndTitleOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $method = $service->register($customer, $module, 'pm_owner', 'Visa ending 2222', null);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/payment_methods/'.$method->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($module->getCode(), $body['paymentModuleCode'] ?? null);
        self::assertArrayNotHasKey('paymentModule', $body, 'The module resource is an admin one: the front has no use for its IRI.');
    }

    /**
     * A provider refusal quotes what it was handed, the token included: the 409 must
     * answer with the core's own wording, never with the provider's.
     */
    public function testARefusalQuotingTheTokenKeepsItOutOfTheResponse(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModuleRow();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $method = $service->register($customer, $module, 'pm_secret', 'Visa ending 2222', null);
        $token = $this->authenticateAsCustomer($customer);

        StoredMethodsPaymentModule::$providerRefusesToForget = true;
        StoredMethodsPaymentModule::$refusalReason = "No such payment_method: 'pm_secret'";

        $response = $this->jsonRequest('DELETE', '/api/front/account/payment_methods/'.$method->getId(), token: $token);

        self::assertSame(409, $response->getStatusCode());
        self::assertStringNotContainsString('pm_secret', (string) $response->getContent());
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
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }
}

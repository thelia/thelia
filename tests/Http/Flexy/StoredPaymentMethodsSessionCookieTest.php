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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Core\HttpFoundation\Session\SessionFactory;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Map\CustomerPaymentMethodTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

/**
 * The stored payment methods read in process follow the customer of the session, so the
 * HTTP endpoints must not: a browser holding a signed-in session cookie and no bearer
 * token is refused by the account firewall and by the operations' own `security:`,
 * whatever the session says.
 */
final class StoredPaymentMethodsSessionCookieTest extends GuestCheckoutTestCase
{
    private const COLLECTION = '/api/front/account/payment_methods';

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

    public function testASignedInSessionCookieWithoutABearerTokenReachesNoOperation(): void
    {
        $account = $this->signInAsARealAccount();
        $sessionName = $this->getService(SessionFactory::class)->createSession()->getName();
        self::assertNotNull($this->client->getCookieJar()->get($sessionName), 'The browser holds the session cookie of the signed-in account.');

        $service = $this->getService(CustomerPaymentMethodService::class);
        $module = $this->paymentModuleRow();
        $own = $service->register($account, $module, 'pm_own', 'Visa ending 1111', null);
        // The first method of a customer is its default already: the PATCH aims at the second.
        $second = $service->register($account, $module, 'pm_second', 'Visa ending 2222', null);
        self::assertTrue($own->getIsDefault());
        self::assertFalse($second->getIsDefault(), 'The PATCH has something to change.');
        $other = $this->fixtures()->customer($this->fixtures()->customerTitle());
        $foreign = $service->register($other, $module, 'pm_foreign', 'Visa ending 9999', null);

        $requests = [
            ['GET', self::COLLECTION, null],
            ['GET', self::COLLECTION.'/'.$own->getId(), null],
            ['GET', self::COLLECTION.'/'.$foreign->getId(), null],
            ['PATCH', self::COLLECTION.'/'.$second->getId(), '{"isDefault":true}'],
            ['DELETE', self::COLLECTION.'/'.$own->getId(), null],
        ];

        foreach ($requests as [$method, $url, $body]) {
            $this->client->request(
                $method,
                $url,
                server: [
                    'HTTP_ACCEPT' => 'application/ld+json',
                    'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
                ],
                content: $body,
            );

            $response = $this->client->getResponse();

            self::assertContains($response->getStatusCode(), [401, 403], \sprintf('%s %s must refuse a session cookie on its own.', $method, $url));
            self::assertStringNotContainsString('Visa ending', (string) $response->getContent(), $url);
            self::assertStringNotContainsString('pm_', (string) $response->getContent(), $url);
        }

        self::assertNotNull(CustomerPaymentMethodQuery::create()->findPk($own->getId()), 'The DELETE must not have reached the row.');
        self::assertNotNull(CustomerPaymentMethodQuery::create()->findPk($foreign->getId()));
        self::assertSame([], StoredMethodsPaymentModule::$forgottenMethodIds, 'Nobody asked the provider to forget anything.');

        CustomerPaymentMethodTableMap::clearInstancePool();
        self::assertFalse(CustomerPaymentMethodQuery::create()->findPk($second->getId())?->getIsDefault(), 'The PATCH must not have reached the row either.');
        self::assertTrue(CustomerPaymentMethodQuery::create()->findPk($own->getId())->getIsDefault());
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

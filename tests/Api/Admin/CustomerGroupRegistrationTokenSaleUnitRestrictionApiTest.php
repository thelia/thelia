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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Tests\Api\RateLimit\RateLimitTestEnvironment;

/**
 * An administrator restricted to a sale unit may not put a customer in another group: moving one
 * (PATCH) or creating one in another group (POST /admin/customers) answers 403, and opening or
 * regenerating a registration link is refused too. The token of an open link is that very
 * capability: whoever holds it registers accounts in the group. A restricted administrator must
 * not be handed it by the read of the group, or it signs up accounts in a group it may not
 * assign.
 */
final class CustomerGroupRegistrationTokenSaleUnitRestrictionApiTest extends ApiTestCase
{
    use RateLimitTestEnvironment;

    private const array ALL = [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE];

    private FixtureFactory $factory;
    private string $previousEmailConfirmation = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->previousEmailConfirmation = (string) ConfigQuery::read('customer_email_confirmation', '0');
        ConfigQuery::write('customer_email_confirmation', '0');
        $this->clearRateLimiterCounters();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('customer_email_confirmation', $this->previousEmailConfirmation);

        parent::tearDown();
    }

    public function testARestrictedAdministratorIsNotHandedTheTokenOfARegistrationLink(): void
    {
        $pro = $this->factory->customerGroup('v3-pro', discount: 30.0, openRegistration: true);
        $admin = $this->factory->restrictedAdmin([
            AdminResources::CUSTOMER => self::ALL,
            AdminResources::CUSTOMER_GROUP => self::ALL,
        ]);
        $this->factory->adminSaleUnit($admin, $this->factory->saleUnit());
        $token = $this->authenticateAsAdmin($admin);

        // The same administrator is refused the link operations themselves.
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/customer_groups/'.$pro->getId().'/registration_link/regenerate', token: $token)->getStatusCode());

        $read = $this->jsonRequest('GET', '/api/admin/customer_groups/'.$pro->getId(), token: $token);
        self::assertSame(200, $read->getStatusCode(), (string) $read->getContent());
        $leaked = json_decode((string) $read->getContent(), true, flags: \JSON_THROW_ON_ERROR)['registrationToken'] ?? null;

        if (\is_string($leaked)) {
            // What the token buys: an account in the group, which the administrator could not create.
            $registered = $this->jsonRequest('POST', '/api/front/customers', [
                'firstname' => 'Side',
                'lastname' => 'Door',
                'email' => 'v3-side-door@test.com',
                'password' => 'secret123',
                'registrationToken' => $leaked,
            ]);
            self::assertSame(Response::HTTP_CREATED, $registered->getStatusCode(), (string) $registered->getContent());
            self::assertSame($pro->getId(), CustomerQuery::create()->findOneByEmail('v3-side-door@test.com')?->getCustomerGroupId());
        }

        self::assertNull($leaked, 'A restricted administrator read the registration token of a group it may not assign customers to, and registered an account in it.');
    }
}

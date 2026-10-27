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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Map\CustomerGroupTableMap;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The registration link of a group is opened, regenerated and closed by explicit operations, read
 * by the administration alone and never written with the group.
 */
final class CustomerGroupRegistrationLinkApiTest extends ApiTestCase
{
    private const string TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testAnAdministratorOpensRegeneratesAndClosesTheLink(): void
    {
        $group = $this->factory->customerGroup('pro');
        $token = $this->authenticateAsAdmin();
        $uri = '/api/admin/customer_groups/'.$group->getId().'/registration_link';

        self::assertNull($this->readBody($group->getId(), $token)['registrationToken'] ?? null, 'A new group has a closed link.');

        $opened = $this->jsonRequest('POST', $uri, token: $token);
        self::assertSame(200, $opened->getStatusCode());
        $body = json_decode((string) $opened->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('pro', $body['code']);
        self::assertMatchesRegularExpression(self::TOKEN_PATTERN, $body['registrationToken']);
        $first = $body['registrationToken'];
        self::assertSame($first, $this->storedToken($group->getId()));

        // Open again: the link stays what it is.
        $again = json_decode((string) $this->jsonRequest('POST', $uri, token: $token)->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($first, $again['registrationToken']);

        $regenerated = $this->jsonRequest('POST', $uri.'/regenerate', token: $token);
        self::assertSame(200, $regenerated->getStatusCode());
        $second = json_decode((string) $regenerated->getContent(), true, flags: \JSON_THROW_ON_ERROR)['registrationToken'];
        self::assertMatchesRegularExpression(self::TOKEN_PATTERN, $second);
        self::assertNotSame($first, $second);
        self::assertSame($second, $this->storedToken($group->getId()));
        self::assertSame($second, $this->readBody($group->getId(), $token)['registrationToken'] ?? null);

        $closed = $this->jsonRequest('DELETE', $uri, token: $token);
        self::assertSame(204, $closed->getStatusCode());
        self::assertSame('', (string) $closed->getContent());
        self::assertNull($this->storedToken($group->getId()));
        self::assertNull($this->readBody($group->getId(), $token)['registrationToken'] ?? null);

        // Closing a closed link is not an error.
        self::assertSame(204, $this->jsonRequest('DELETE', $uri, token: $token)->getStatusCode());
    }

    public function testRegeneratingAClosedLinkOpensIt(): void
    {
        $group = $this->factory->customerGroup('closed');
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/customer_groups/'.$group->getId().'/registration_link/regenerate', token: $token);

        self::assertSame(200, $response->getStatusCode());
        self::assertMatchesRegularExpression(self::TOKEN_PATTERN, $this->storedToken($group->getId()) ?? '');
    }

    public function testTheTokenIsReadOnTheSingleItemOnlyAndNeverOnTheCollection(): void
    {
        $group = $this->factory->customerGroup('listed', openRegistration: true);
        $token = $this->authenticateAsAdmin();

        $list = json_decode((string) $this->jsonRequest('GET', '/api/admin/customer_groups', token: $token)->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $members = $list['hydra:member'] ?? $list['member'];
        self::assertNotEmpty($members);
        foreach ($members as $member) {
            self::assertArrayNotHasKey('registrationToken', $member);
        }

        self::assertSame($this->storedToken($group->getId()), $this->readBody($group->getId(), $token)['registrationToken'] ?? null);
    }

    public function testNoWriteOfTheGroupSetsTheToken(): void
    {
        $group = $this->factory->customerGroup('guarded', openRegistration: true);
        $stored = $this->storedToken($group->getId());
        $forged = str_repeat('a', 64);
        $token = $this->authenticateAsAdmin();
        $uri = '/api/admin/customer_groups/'.$group->getId();

        $patch = $this->jsonRequest('PATCH', $uri, ['registrationToken' => $forged, 'discount' => 5.0], $token, 'merge-patch+json');
        self::assertSame(200, $patch->getStatusCode());
        self::assertSame($stored, $this->storedToken($group->getId()), 'A PATCH does not write the token.');

        $put = $this->jsonRequest('PUT', $uri, ['code' => 'guarded', 'registrationToken' => $forged], $token);
        self::assertSame(200, $put->getStatusCode());
        self::assertSame($stored, $this->storedToken($group->getId()), 'A PUT does not write the token, nor empties it.');

        $created = $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'created-open', 'title' => 'Created', 'registrationToken' => $forged], $token);
        self::assertSame(201, $created->getStatusCode());
        self::assertNull(CustomerGroupQuery::create()->findOneByCode('created-open')?->getRegistrationToken(), 'A new group has a closed link whatever the body says.');
    }

    public function testTheDefaultGroupHasNoLink(): void
    {
        $default = CustomerGroupQuery::findDefault();
        $token = $this->authenticateAsAdmin();
        $uri = '/api/admin/customer_groups/'.$default->getId().'/registration_link';

        self::assertSame(409, $this->jsonRequest('POST', $uri, token: $token)->getStatusCode());
        self::assertSame(409, $this->jsonRequest('POST', $uri.'/regenerate', token: $token)->getStatusCode());
        self::assertNull($this->storedToken($default->getId()));
        // Closing it is harmless.
        self::assertSame(204, $this->jsonRequest('DELETE', $uri, token: $token)->getStatusCode());
    }

    public function testAnUnknownGroupIsNotFound(): void
    {
        $token = $this->authenticateAsAdmin();

        self::assertSame(404, $this->jsonRequest('POST', '/api/admin/customer_groups/999999/registration_link', token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('POST', '/api/admin/customer_groups/999999/registration_link/regenerate', token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('DELETE', '/api/admin/customer_groups/999999/registration_link', token: $token)->getStatusCode());
    }

    public function testAnAdministratorRestrictedToASaleUnitMayNotManageTheLink(): void
    {
        $group = $this->factory->customerGroup('restricted-link', openRegistration: true);
        $stored = $this->storedToken($group->getId());
        $admin = $this->factory->restrictedAdmin($this->allRightsOnGroups());
        $this->factory->adminSaleUnit($admin, $this->factory->saleUnit());
        $token = $this->authenticateAsAdmin($admin);
        $uri = '/api/admin/customer_groups/'.$group->getId().'/registration_link';

        self::assertSame(403, $this->jsonRequest('POST', $uri, token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', $uri.'/regenerate', token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', $uri, token: $token)->getStatusCode());
        self::assertSame($stored, $this->storedToken($group->getId()));
    }

    public function testTheLinkAnswersToTheRightToUpdateGroups(): void
    {
        $group = $this->factory->customerGroup('right-link', openRegistration: true);
        $stored = $this->storedToken($group->getId());
        $uri = '/api/admin/customer_groups/'.$group->getId().'/registration_link';

        $readerOnly = $this->authenticateAsAdmin($this->factory->restrictedAdmin([AdminResources::CUSTOMER_GROUP => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::DELETE]]));
        self::assertSame(403, $this->jsonRequest('POST', $uri.'/regenerate', token: $readerOnly)->getStatusCode(), 'Creating or deleting groups is not updating them.');
        self::assertSame(403, $this->jsonRequest('DELETE', $uri, token: $readerOnly)->getStatusCode());
        self::assertSame($stored, $this->storedToken($group->getId()));

        $updater = $this->authenticateAsAdmin($this->factory->restrictedAdmin([AdminResources::CUSTOMER_GROUP => [AccessManager::VIEW, AccessManager::UPDATE]]));
        self::assertSame(200, $this->jsonRequest('POST', $uri.'/regenerate', token: $updater)->getStatusCode());
        self::assertSame(204, $this->jsonRequest('DELETE', $uri, token: $updater)->getStatusCode());
    }

    public function testAVisitorAndACustomerCannotManageTheLink(): void
    {
        $group = $this->factory->customerGroup('front-link', openRegistration: true);
        $stored = $this->storedToken($group->getId());
        $uri = '/api/admin/customer_groups/'.$group->getId().'/registration_link';
        $customerToken = $this->authenticateAsCustomer($this->factory->customer($this->factory->customerTitle(), ['password' => 'password']));

        foreach ([null, $customerToken] as $token) {
            self::assertContains($this->jsonRequest('POST', $uri, token: $token)->getStatusCode(), [401, 403]);
            self::assertContains($this->jsonRequest('DELETE', $uri, token: $token)->getStatusCode(), [401, 403]);
        }

        self::assertSame($stored, $this->storedToken($group->getId()));
    }

    /**
     * @return array<string, mixed>
     */
    private function readBody(int $groupId, string $token): array
    {
        $response = $this->jsonRequest('GET', '/api/admin/customer_groups/'.$groupId, token: $token);
        self::assertSame(200, $response->getStatusCode());

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function storedToken(int $groupId): ?string
    {
        CustomerGroupTableMap::clearInstancePool();

        return CustomerGroupQuery::create()->findPk($groupId)?->getRegistrationToken();
    }

    /**
     * @return array<string, list<string>>
     */
    private function allRightsOnGroups(): array
    {
        return [
            AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE],
            AdminResources::CUSTOMER_GROUP => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE],
        ];
    }
}

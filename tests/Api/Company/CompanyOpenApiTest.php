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

namespace Thelia\Tests\Api\Company;

/**
 * What the published documentation says about the companies: exactly the operations meant, and no
 * more. A resource declared loosely shows up here with a complete CRUD nobody asked for.
 */
final class CompanyOpenApiTest extends CompanyApiTestCase
{
    private const EXPECTED = [
        'delete /api/admin/companies/{id}',
        'delete /api/admin/company_customers/{id}',
        'delete /api/admin/company_roles/{id}',
        'delete /api/front/account/company/customers/{id}',
        'get /api/admin/companies',
        'get /api/admin/companies/{id}',
        'get /api/admin/company_customers',
        'get /api/admin/company_customers/{id}',
        'get /api/admin/company_roles',
        'get /api/admin/company_roles/{id}',
        'get /api/front/account/company',
        'get /api/front/account/company/customers',
        'get /api/front/account/company/customers/{id}',
        'get /api/front/account/company/roles',
        'get /api/front/company-invitations/{token}',
        'patch /api/admin/companies/{id}',
        'patch /api/admin/company_customers/{id}',
        'patch /api/admin/company_roles/{id}',
        'patch /api/front/account/company',
        'patch /api/front/account/company/customers/{id}',
        'post /api/admin/companies',
        'post /api/admin/company_customers',
        'post /api/admin/company_roles',
        'post /api/front/account/company/invitations',
        'post /api/front/account/company/invitations/accept',
        'put /api/admin/companies/{id}',
    ];

    public function testTheDocumentationHoldsTheCompanyOperationsAndNoOther(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);
        $documentation = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $found = [];

        foreach ($documentation['paths'] as $path => $operations) {
            if (!str_contains((string) $path, 'compan')) {
                continue;
            }

            foreach (array_keys($operations) as $method) {
                if (\in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $found[] = $method.' '.$path;
                }
            }
        }

        sort($found);
        $expected = self::EXPECTED;
        sort($expected);

        self::assertSame($expected, $found);
    }

    public function testTheOrdersCollectionDocumentsItsScopeParameter(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);
        $documentation = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $names = array_column($documentation['paths']['/api/front/account/orders']['get']['parameters'] ?? [], 'name');

        self::assertContains('scope', $names);
    }
}

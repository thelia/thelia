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

namespace Thelia\Tests\Integration\Api;

use ApiPlatform\Metadata\Put;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\Customer;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Api\RecordingCustomerAddon;

/**
 * A module extends a resource with an addon, and the addon is written from the
 * JSON body of the request. Its fields carry serialization groups like the
 * resource's own: a field the operation cannot write must not reach the addon.
 * Without that, `PUT /api/front/account/customers/{id}` let a customer write a
 * field the shop reserves to its administration (the family of the customer,
 * an order block).
 */
final class PersistProcessorAddonGroupsTest extends IntegrationTestCase
{
    private const ADDON = 'RecordingCustomerAddon';

    protected function setUp(): void
    {
        parent::setUp();
        RecordingCustomerAddon::$saved = [];
    }

    public function testTheFrontOperationOnlyPassesTheFieldsItCanWrite(): void
    {
        $this->persist([Customer::GROUP_FRONT_WRITE], ['accountManager' => 'self-assigned', 'note' => 'call after 5pm']);

        self::assertSame([['note' => 'call after 5pm']], RecordingCustomerAddon::$saved);
    }

    public function testAnAddonSentWithReservedFieldsOnlyIsNotSaved(): void
    {
        $this->persist([Customer::GROUP_FRONT_WRITE], ['accountManager' => 'self-assigned']);

        self::assertSame([], RecordingCustomerAddon::$saved);
    }

    public function testTheAdministrationWritesEveryFieldItsGroupsAllow(): void
    {
        $this->persist(
            [Customer::GROUP_ADMIN_WRITE, Customer::GROUP_ADMIN_WRITE_UPDATE],
            ['accountManager' => 'Jane', 'note' => 'key account', 'unknown' => 'dropped'],
        );

        self::assertSame([['accountManager' => 'Jane', 'note' => 'key account']], RecordingCustomerAddon::$saved);
    }

    /**
     * @param list<string>         $denormalizationGroups
     * @param array<string, mixed> $addonPayload
     */
    private function persist(array $denormalizationGroups, array $addonPayload): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $resource = new Customer();

        $transformer = $this->createMock(ApiResourcePropelTransformerService::class);
        $transformer->method('getResourceAddonDefinitions')->willReturn([self::ADDON => RecordingCustomerAddon::class]);
        $transformer->method('resourceToModel')->willReturn($customer);
        $transformer->method('modelToResource')->willReturn($resource);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create(
            '/api/front/account/customers/'.$customer->getId(),
            'PUT',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['firstname' => 'John', self::ADDON => $addonPayload], \JSON_THROW_ON_ERROR),
        ));

        $processor = new PropelPersistProcessor(
            $transformer,
            $requestStack,
            $this->getService(ClassMetadataFactoryInterface::class),
        );

        $processor->process(
            $resource,
            new Put(denormalizationContext: ['groups' => $denormalizationGroups]),
            ['id' => $customer->getId()],
        );
    }
}

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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Address as AddressResource;
use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Api\Service\API\MetadataService;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Customer;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Integration\Company\AddressTestCase;

/**
 * The `security` rule of each front operation on an order or an address, asked on its own: the
 * query scope already keeps what is out of reach from being read, and this rule is the second lock,
 * which must refuse the same things when the first one is not there.
 */
final class CompanyOperationSecurityTest extends AddressTestCase
{
    use LogsInAsCustomer;

    public function testTheReadOfAnOrderIsRefusedToWhoseRoleDoesNotReadIt(): void
    {
        $companies = $this->twoCompanies();
        $approver = $this->buyerOf($companies['first'], CompanyRoleCode::APPROVER);
        $order = $this->orderOf($companies['creator'], $companies['first'], $companies['creator']);

        self::assertTrue($this->allowed(OrderResource::class, 'GET', '/front/account/orders/{id}', $companies['creator'], $order));
        self::assertTrue($this->allowed(OrderResource::class, 'GET', '/front/account/orders/{id}', $approver, $order));
        self::assertFalse($this->allowed(OrderResource::class, 'GET', '/front/account/orders/{id}', $companies['colleague'], $order));
        self::assertFalse($this->allowed(OrderResource::class, 'GET', '/front/account/orders/{id}', $companies['administratorOfSecond'], $order));
    }

    public function testAnAddressIsReadByTheCompanyAndChangedByItsCreatorAndAdministratorsOnly(): void
    {
        $companies = $this->twoCompanies();
        $shared = $this->addressOf($companies['creator'], $companies['first']);
        $personal = $this->addressOf($companies['creator']);
        $read = ['GET', '/front/account/addresses/{id}'];
        $change = ['PUT', '/front/account/addresses/{id}'];
        $delete = ['DELETE', '/front/account/addresses/{id}'];

        self::assertTrue($this->allowed(AddressResource::class, ...[...$read, $companies['colleague'], $shared]));
        self::assertFalse($this->allowed(AddressResource::class, ...[...$read, $companies['colleague'], $personal]));
        self::assertFalse($this->allowed(AddressResource::class, ...[...$read, $companies['administratorOfSecond'], $shared]));

        foreach ([$change, $delete] as $operation) {
            self::assertFalse($this->allowed(AddressResource::class, ...[...$operation, $companies['colleague'], $shared]), implode(' ', $operation));
            self::assertTrue($this->allowed(AddressResource::class, ...[...$operation, $companies['creator'], $shared]), implode(' ', $operation));
            self::assertTrue($this->allowed(AddressResource::class, ...[...$operation, $companies['administratorOfFirst'], $shared]), implode(' ', $operation));
            self::assertFalse($this->allowed(AddressResource::class, ...[...$operation, $companies['administratorOfSecond'], $shared]), implode(' ', $operation));
        }
    }

    /**
     * @param class-string $resourceClass
     */
    private function allowed(string $resourceClass, string $method, string $uriTemplate, Customer $customer, ActiveRecordInterface $model): bool
    {
        $operation = $this->operation($resourceClass, $method, $uriTemplate);
        $resource = $this->getService(ApiResourcePropelTransformerService::class)->modelToResource($resourceClass, $model, []);
        $this->loginAsCustomerInSession($customer);

        return $this->getService(MetadataService::class)->canUserAccessResource(
            $resourceClass,
            '/api'.$uriTemplate,
            $method,
            $operation,
            ['extra_variables' => ['object' => $resource]],
        );
    }

    /**
     * @param class-string $resourceClass
     */
    private function operation(string $resourceClass, string $method, string $uriTemplate): Operation
    {
        $factory = $this->getService(ResourceMetadataCollectionFactoryInterface::class);

        foreach ($factory->create($resourceClass) as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if ($operation->getMethod() === $method && $operation->getUriTemplate() === $uriTemplate) {
                    return $operation;
                }
            }
        }

        throw new \LogicException(\sprintf('No %s %s on %s.', $method, $uriTemplate, $resourceClass));
    }
}

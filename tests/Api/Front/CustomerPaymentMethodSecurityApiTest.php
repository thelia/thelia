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

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Thelia\Api\Resource\CustomerPaymentMethod;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * Two barriers stand in front of the stored payment methods of the account: the
 * firewall rule on /api/front/account, and a `security:` expression on each operation,
 * so that neither one alone decides. The ownership extension then scopes what is left
 * to the caller, which keeps another customer's id a 404.
 */
final class CustomerPaymentMethodSecurityApiTest extends ApiTestCase
{
    use RegistersGuestCustomers;

    public function testEveryFrontOperationRequiresACustomerOnItsOwn(): void
    {
        $factory = static::getContainer()->get('api_platform.metadata.resource.metadata_collection_factory');
        self::assertInstanceOf(ResourceMetadataCollectionFactoryInterface::class, $factory);

        $frontOperations = 0;

        foreach ($factory->create(CustomerPaymentMethod::class) as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if (!str_starts_with((string) $operation->getUriTemplate(), '/front/')) {
                    continue;
                }

                ++$frontOperations;
                self::assertSame(
                    'is_granted("ROLE_CUSTOMER")',
                    $operation->getSecurity(),
                    \sprintf('%s %s must not rely on the firewall alone.', $operation->getMethod(), $operation->getUriTemplate()),
                );
            }
        }

        self::assertSame(4, $frontOperations, 'The collection, the item, the default choice and the removal.');
    }

    public function testAGuestTokenReachesNoneOfTheOperations(): void
    {
        $this->enableGuestCheckout();
        [, $guest] = $this->registerGuest();
        $token = (string) ($guest['token'] ?? '');
        self::assertNotSame('', $token, 'The guest registration hands back a token.');

        $requests = [
            ['GET', '/api/front/account/payment_methods', null],
            ['GET', '/api/front/account/payment_methods/1', null],
            ['PATCH', '/api/front/account/payment_methods/1', ['isDefault' => true]],
            ['DELETE', '/api/front/account/payment_methods/1', null],
        ];

        foreach ($requests as [$method, $url, $payload]) {
            $response = $this->jsonRequest(
                $method,
                $url,
                $payload ?? [],
                $token,
                format: 'PATCH' === $method ? 'merge-patch+json' : 'jsonld',
            );

            self::assertSame(403, $response->getStatusCode(), \sprintf('%s %s must refuse a guest.', $method, $url));
        }
    }
}

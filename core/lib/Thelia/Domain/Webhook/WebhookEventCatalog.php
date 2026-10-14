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

namespace Thelia\Domain\Webhook;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Api\Resource\Customer;
use Thelia\Api\Resource\Order;
use Thelia\Api\Resource\Product;

final readonly class WebhookEventCatalog
{
    private const array CORE_EVENTS = [
        'order.created' => ['resource' => Order::class, 'groups' => [Order::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE]],
        'order.status_changed' => ['resource' => Order::class, 'groups' => [Order::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE]],
        'order.paid' => ['resource' => Order::class, 'groups' => [Order::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE]],
        'customer.created' => ['resource' => Customer::class, 'groups' => [Customer::GROUP_ADMIN_READ, Customer::GROUP_ADMIN_READ_SINGLE]],
        'product.updated' => ['resource' => Product::class, 'groups' => [Product::GROUP_ADMIN_READ, Product::GROUP_ADMIN_READ_SINGLE]],
    ];

    /** @var array<string, array{resource: class-string, groups: list<string>}> */
    private array $events;

    /**
     * @param array<string, array{resource: class-string, groups: list<string>}> $moduleEvents
     */
    public function __construct(
        #[Autowire(param: 'thelia.webhook.events')]
        array $moduleEvents = [],
    ) {
        $this->events = array_merge(self::CORE_EVENTS, $moduleEvents);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->events);
    }

    public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    /**
     * @return class-string
     */
    public function resource(string $name): string
    {
        return $this->definition($name)['resource'];
    }

    /**
     * @return list<string>
     */
    public function groups(string $name): array
    {
        return $this->definition($name)['groups'];
    }

    /**
     * @return array{resource: class-string, groups: list<string>}
     */
    private function definition(string $name): array
    {
        return $this->events[$name] ?? throw new \InvalidArgumentException(\sprintf('"%s" is not a published webhook event.', $name));
    }
}

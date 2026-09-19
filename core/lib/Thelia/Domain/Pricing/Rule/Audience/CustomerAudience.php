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

namespace Thelia\Domain\Pricing\Rule\Audience;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\Customer;

/**
 * The reserved rules a visitor is entitled to, all audience modes together, asked
 * of the database once per customer and request.
 *
 * A visitor without an account is entitled to none, and so is a guest account: a
 * guest row is reusable by e-mail, which is no proof of anything.
 */
class CustomerAudience implements ResetInterface
{
    /** @var array<int, list<int>> */
    private array $entitledByCustomer = [];

    /**
     * @param iterable<AudienceResolverInterface> $resolvers
     */
    public function __construct(
        #[AutowireIterator(AudienceResolverInterface::TAG)]
        private readonly iterable $resolvers,
    ) {
    }

    /**
     * @return list<int>
     */
    public function entitledRuleIds(?Customer $customer): array
    {
        if (null === $customer || $customer->getIsGuest()) {
            return [];
        }

        return $this->entitledByCustomer[$customer->getId()] ??= $this->resolve($customer);
    }

    public function reset(): void
    {
        $this->entitledByCustomer = [];
    }

    /**
     * @return list<int>
     */
    private function resolve(Customer $customer): array
    {
        $ids = [];

        foreach ($this->resolvers as $resolver) {
            $ids = [...$ids, ...$resolver->entitledRuleIds($customer)];
        }

        return array_values(array_unique($ids));
    }
}

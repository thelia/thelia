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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Api\Resource\CustomerGroup as CustomerGroupResource;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Model\Customer;

/**
 * The storefront reads the group the shop prices the customer by, not the column of the row.
 *
 * Prices, the visibility of the catalogue and the voter of `/api/front/customer_groups/{id}`
 * all ask {@see CustomerGroupResolver}, which gives a guest the default group whatever a row
 * reused by e-mail says. Handing out the raw column would show a guest the group it does not
 * belong to, and an IRI the voter then refuses (403) to the very customer it was given to.
 * The administration still reads the column: it is what it edits.
 */
final readonly class CustomerGroupResolvedListener implements EventSubscriberInterface
{
    public function __construct(
        private CustomerGroupResolver $customerGroupResolver,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function exposeTheGroupThePricesAreRead(ModelToResourceEvent $event): void
    {
        $resource = $event->getResource();
        $customer = $event->getModel();
        $context = $event->getContext();

        if (!$resource instanceof CustomerResource || !$customer instanceof Customer || !self::isFrontAccountRead($context)) {
            return;
        }

        $group = $this->customerGroupResolver->groupOf($customer);

        if ($resource->getCustomerGroup()?->getId() === $group->getId()) {
            return;
        }

        /** @var CustomerGroupResource $groupResource */
        $groupResource = $this->transformer->modelToResource(
            CustomerGroupResource::class,
            $group,
            $context,
            withRelation: false,
        );

        $resource->setCustomerGroup($groupResource);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function isFrontAccountRead(array $context): bool
    {
        $groups = $context['groups'] ?? [];

        if (!\is_array($groups)) {
            $groups = [$groups];
        }

        foreach ($groups as $group) {
            if (\is_string($group) && str_starts_with($group, 'admin:')) {
                return false;
            }
        }

        return \in_array(CustomerResource::GROUP_FRONT_READ_SINGLE, $groups, true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['exposeTheGroupThePricesAreRead', 0],
            ],
        ];
    }
}

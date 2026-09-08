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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class PaginationExtension implements QueryResultCollectionExtensionInterface
{
    public function __construct(
        private readonly ?Pagination $pagination,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
    }

    private function getPagination(?Operation $operation, array $context): ?array
    {
        if (!$this->pagination->isEnabled($operation, $context)) {
            return null;
        }

        // Pagination reads a total only to walk back from the end of a GraphQL
        // `last` window, and that is the offset getResult() throws away below.
        // Counting here therefore answered nothing, and ran the heaviest
        // statement of the page a second time: the pager counts the rows itself,
        // and that is the total the response carries.
        return $this->pagination->getPagination($operation, $context);
    }

    public function supportsResult(string $resourceClass, ?Operation $operation = null, array $context = []): bool
    {
        return $this->pagination->isEnabled($operation, $context);
    }

    public function getResult(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = [])
    {
        if (null === $pagination = $this->getPagination($operation, $context)) {
            return $query->find();
        }

        [$page, , $limit] = $pagination;

        return $query->paginate($page, $limit);
    }
}

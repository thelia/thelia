<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class PaginationExtension implements QueryResultCollectionExtensionInterface
{
    public function __construct(
        private readonly ?Pagination $pagination
    )
    {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
    }

    private function getPagination(ModelCriteria $query, ?Operation $operation, array $context): ?array
    {
        $enabled = $this->pagination->isEnabled($operation, $context);

        if (!$enabled) {
            return null;
        }

        $context = $this->addCountToContext($query, $context);

        return $this->pagination->getPagination($operation, $context);
    }

    private function addCountToContext(ModelCriteria $query, array $context): array
    {
        $context['count'] = $query->count();

        return $context;
    }

    public function supportsResult(string $resourceClass, Operation $operation = null, array $context = []): bool
    {
        return $this->pagination->isEnabled($operation, $context);
    }

    public function getResult(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = [])
    {
        if (null === $pagination = $this->getPagination($query, $operation, $context)) {
            return $query->find();
        }

        [$page, , $limit] = $pagination;

        return $query->paginate($page, $limit);
    }
}

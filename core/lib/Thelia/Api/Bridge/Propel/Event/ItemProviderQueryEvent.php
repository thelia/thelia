<?php

namespace Thelia\Api\Bridge\Propel\Event;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Contracts\EventDispatcher\Event;

class ItemProviderQueryEvent extends Event
{
    public function __construct(
        private ModelCriteria $query,
        private Operation $operation,
        private array $uriVariables = [],
        private array $context = [],
        private ?string $resourceClass = null
    )
    {
    }

    public function getQuery(): ModelCriteria
    {
        return $this->query;
    }

    public function setQuery(ModelCriteria $query): ItemProviderQueryEvent
    {
        $this->query = $query;
        return $this;
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function setOperation(Operation $operation): ItemProviderQueryEvent
    {
        $this->operation = $operation;
        return $this;
    }

    public function getUriVariables(): array
    {
        return $this->uriVariables;
    }

    public function setUriVariables(array $uriVariables): ItemProviderQueryEvent
    {
        $this->uriVariables = $uriVariables;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): ItemProviderQueryEvent
    {
        $this->context = $context;
        return $this;
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): ItemProviderQueryEvent
    {
        $this->resourceClass = $resourceClass;
        return $this;
    }
}

<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    ) {
    }

    public function getQuery(): ModelCriteria
    {
        return $this->query;
    }

    public function setQuery(ModelCriteria $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function setOperation(Operation $operation): self
    {
        $this->operation = $operation;

        return $this;
    }

    public function getUriVariables(): array
    {
        return $this->uriVariables;
    }

    public function setUriVariables(array $uriVariables): self
    {
        $this->uriVariables = $uriVariables;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): self
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }
}

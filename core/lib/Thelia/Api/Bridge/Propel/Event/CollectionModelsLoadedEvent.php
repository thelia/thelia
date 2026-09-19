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

namespace Thelia\Api\Bridge\Propel\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * A page of models has been read for a collection and is about to be transformed,
 * one by one, into resources.
 *
 * What a listener of {@see ModelToResourceEvent} would otherwise look up per member
 * can be looked up here for the whole page: the transform then finds it in memory.
 */
class CollectionModelsLoadedEvent extends Event
{
    /**
     * @param class-string         $resourceClass
     * @param list<object>         $models
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $resourceClass,
        private readonly array $models,
        private readonly array $context,
    ) {
    }

    /**
     * @return class-string
     */
    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * @return list<object>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A model was written or deleted through the API.
 *
 * The API processors save and delete the Propel model directly, without going
 * through the Thelia\Action\* listeners the back office dispatches: whatever
 * those listeners keep in step with the catalog - stored prices, caches - would
 * otherwise never hear of a write made through the API. This is the one event a
 * listener subscribes to for that.
 */
class ResourcePersistedEvent extends Event
{
    public const OPERATION_WRITE = 'write';

    public const OPERATION_DELETE = 'delete';

    /**
     * @param class-string $resourceClass
     */
    public function __construct(
        private readonly string $resourceClass,
        private readonly ActiveRecordInterface $model,
        private readonly string $operation,
    ) {
    }

    /**
     * @return class-string
     */
    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    public function getModel(): ActiveRecordInterface
    {
        return $this->model;
    }

    public function isDelete(): bool
    {
        return self::OPERATION_DELETE === $this->operation;
    }
}

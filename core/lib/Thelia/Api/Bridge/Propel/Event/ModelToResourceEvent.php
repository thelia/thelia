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
use Thelia\Api\Resource\PropelResourceInterface;

class ModelToResourceEvent extends Event
{
    public const BEFORE_TRANSFORM = 'api_before_model_to_resource';
    public const AFTER_TRANSFORM = 'api_after_model_to_resource';

    private PropelResourceInterface $resource;

    public function __construct(
        private ActiveRecordInterface $model,
        private ?ActiveRecordInterface $parentModel = null,
    ) {
    }

    public function getResource(): PropelResourceInterface
    {
        return $this->resource;
    }

    public function setResource(PropelResourceInterface $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    public function getModel(): ActiveRecordInterface
    {
        return $this->model;
    }

    public function setModel(ActiveRecordInterface $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getParentModel(): ActiveRecordInterface
    {
        return $this->parentModel;
    }

    public function setParentModel(ActiveRecordInterface $parentModel): self
    {
        $this->parentModel = $parentModel;

        return $this;
    }
}

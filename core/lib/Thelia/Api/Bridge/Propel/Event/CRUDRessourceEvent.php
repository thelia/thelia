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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Api\Resource\PropelResourceInterface;

class CRUDRessourceEvent extends Event
{
    public function __construct(
        private ActiveRecordInterface|array $model,
        private PropelResourceInterface|array $resource,
    ) {
    }

    public function getModel(): ActiveRecordInterface|array
    {
        return $this->model;
    }

    public function getResource(): PropelResourceInterface|array
    {
        return $this->resource;
    }

    public function setModel(ActiveRecordInterface|array $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function setResource(array|PropelResourceInterface $resource): self
    {
        $this->resource = $resource;

        return $this;
    }
}

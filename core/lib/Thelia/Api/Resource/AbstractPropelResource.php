<?php

namespace Thelia\Api\Resource;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

abstract class AbstractPropelResource implements PropelResourceInterface
{
    private ?ActiveRecordInterface $propelModel = null;

    public function setPropelModel(?ActiveRecordInterface $propelModel = null): PropelResourceInterface
    {
        $this->propelModel = $propelModel;
        return $this;
    }

    public function getPropelModel(): ?ActiveRecordInterface
    {
        return $this->propelModel;
    }
}

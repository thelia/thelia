<?php

namespace Thelia\Api\Resource;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

interface PropelResourceInterface
{
    public function setPropelModel(ActiveRecordInterface $propelModel): PropelResourceInterface;
    public function getPropelModel(): ?ActiveRecordInterface;

    public static function getPropelModelClass(): string;
}

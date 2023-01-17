<?php

namespace Thelia\Api\Resource;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

interface PropelResourceInterface
{
    public function getId();

    public function setId(int $id);

    public function setPropelModel(ActiveRecordInterface $propelModel): PropelResourceInterface;
    public function getPropelModel(): ?ActiveRecordInterface;

    public static function getPropelModelClass(): string;
}

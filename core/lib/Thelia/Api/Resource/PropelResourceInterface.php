<?php

namespace Thelia\Api\Resource;

interface PropelResourceInterface
{
    public function getId();

    public function setId(int $id);

    public static function getPropelModelClass(): string;
}

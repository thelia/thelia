<?php

namespace Thelia\Api\Resource;

interface TranslatableResourceInterface
{
    public function setI18ns(array $i18ns): self;

    public static function getTranslatableFields(): array;

    public static function getI18nResourceClass(): string;
}

<?php

namespace Thelia\Api\Resource;

interface TranslatableResourceInterface
{
    public function setI18ns(array $i18ns): self;

    public function addI18n(I18n $i18n, string $locale): self;

    public function getI18ns(): I18nCollection;

    public static function getTranslatableFields(): array;

    public static function getI18nResourceClass(): string;
}

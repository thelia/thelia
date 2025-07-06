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
namespace Thelia\Api\Resource;

interface TranslatableResourceInterface
{
    public function setI18ns(array $i18ns): self;

    public function addI18n(I18n $i18n, string $locale): self;

    public function getI18ns(): I18nCollection;

    public static function getI18nResourceClass(): string;
}

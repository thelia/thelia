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

namespace Thelia\Api\Resource;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Ignore;

interface PropelResourceInterface
{
    public function __get(string $property);

    public function setPropelModel(ActiveRecordInterface $propelModel): self;

    public function getPropelModel(): ?ActiveRecordInterface;

    public function getResourceAddons(): array;

    public function getResourceAddon(string $addonName): ?ResourceAddonInterface;

    public function setResourceAddon(string $addonName, ?ResourceAddonInterface $addon): self;

    #[Ignore]
    public static function getPropelRelatedTableMap(): ?TableMap;
}

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

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Ignore;

interface ResourceAddonInterface
{
    #[Ignore]
    public static function getResourceParent(): string;

    #[Ignore]
    public static function getPropelRelatedTableMap(): ?TableMap;

    public static function extendQuery(ModelCriteria $query, ?Operation $operation = null, array $context = []): void;

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): self;

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): self;

    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void;

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void;
}

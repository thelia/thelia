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

interface PropelResourceInterface
{
    public function setPropelModel(ActiveRecordInterface $propelModel): self;

    public function getPropelModel(): ?ActiveRecordInterface;

    public static function getPropelModelClass(): string;
}

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

    public function afterModelToResource(array $context): void
    {
    }
}

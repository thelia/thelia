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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\DestinationRestriction as BaseDestinationRestriction;
use Thelia\Model\Tools\PositionManagementTrait;

/**
 * A group of products that cannot ship to some destinations (mode 0), or only to some
 * (mode 1). A group turned off refuses nothing.
 */
class DestinationRestriction extends BaseDestinationRestriction
{
    use PositionManagementTrait;

    public const MODE_DENY = 0;
    public const MODE_ALLOW = 1;

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }
}

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
use Thelia\Model\Base\Attribute as BaseAttribute;
use Thelia\Model\Tools\PositionManagementTrait;

class Attribute extends BaseAttribute
{
    use PositionManagementTrait;

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }
}

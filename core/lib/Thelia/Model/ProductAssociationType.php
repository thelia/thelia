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
use Thelia\Model\Base\ProductAssociationType as BaseProductAssociationType;
use Thelia\Model\Tools\PositionManagementTrait;

class ProductAssociationType extends BaseProductAssociationType
{
    use PositionManagementTrait;

    public const CODE_ACCESSORY = 'accessory';

    public const CODE_CROSS_SELLING = 'cross_selling';

    public const CODE_UP_SELLING = 'up_selling';

    public const UNDELETABLE_CODES = [
        self::CODE_ACCESSORY,
    ];

    public function isDeletable(): bool
    {
        return !\in_array($this->getCode(), self::UNDELETABLE_CODES, true);
    }

    public function isVisible(): bool
    {
        return 1 === $this->getVisible();
    }

    public function isReciprocal(): bool
    {
        return 1 === $this->getReciprocal();
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }
}

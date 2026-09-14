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
use Thelia\Model\Base\CheckoutStep as BaseCheckoutStep;
use Thelia\Model\Tools\PositionManagementTrait;

class CheckoutStep extends BaseCheckoutStep
{
    use PositionManagementTrait;

    public const CODE_CART = 'cart';
    public const CODE_DELIVERY = 'delivery';
    public const CODE_PAYMENT = 'payment';
    public const CODE_CONFIRMATION = 'confirmation';

    /**
     * The steps a checkout has no shape without: it opens on the cart, it takes the
     * money next to last and it ends on the confirmation. A configuration missing any
     * of them is not a shorter tunnel, it is a broken one — hence the back office
     * refusing to turn them off, and the progression falling back to the list the code
     * declares when the table says otherwise anyway.
     */
    public const REQUIRED_CODES = [
        self::CODE_CART,
        self::CODE_PAYMENT,
        self::CODE_CONFIRMATION,
    ];

    public function isActive(): bool
    {
        return 1 === $this->getActive();
    }

    public function isMandatory(): bool
    {
        return 1 === $this->getMandatory();
    }

    /**
     * A row created without a position lands at the end of the list. A row that names
     * one keeps it: a module declaring a step says where in the tunnel it belongs, and
     * the synchronisation writes exactly that.
     */
    public function preInsert(?ConnectionInterface $con = null): bool
    {
        if (0 === $this->getPosition()) {
            $this->setPosition($this->getNextPosition());
        }

        parent::preInsert($con);

        return true;
    }
}

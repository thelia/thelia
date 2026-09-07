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
use Thelia\Model\Base\Consent as BaseConsent;
use Thelia\Model\Tools\PositionManagementTrait;

class Consent extends BaseConsent
{
    use PositionManagementTrait;

    /**
     * The terms and conditions of sale. Created by the installer and by the update,
     * always mandatory, and refused deletion: a shop without it would take orders
     * nobody agreed to any terms for.
     */
    public const CODE_TERMS_AND_CONDITIONS = 'terms_and_conditions';

    /**
     * The codes a shop may not delete, whatever the back office lets it click.
     */
    public const UNDELETABLE_CODES = [
        self::CODE_TERMS_AND_CONDITIONS,
    ];

    public function isDeletable(): bool
    {
        return !\in_array($this->getCode(), self::UNDELETABLE_CODES, true);
    }

    public function isMandatory(): bool
    {
        return 1 === $this->getMandatory();
    }

    public function isActive(): bool
    {
        return 1 === $this->getActive();
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }
}

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

namespace Thelia\Condition;

/**
 * Manage how Condition could interact with each other.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
interface ConditionOrganizerInterface
{
    /**
     * Organize ConditionInterface.
     *
     * @param array $conditions Array of ConditionInterface
     *
     * @return array Array of ConditionInterface sorted
     */
    public function organize(array $conditions): array;
}

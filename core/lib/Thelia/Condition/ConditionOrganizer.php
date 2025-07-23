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
 * Manage how Condition could interact with each others.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class ConditionOrganizer implements ConditionOrganizerInterface
{
    /**
     * Organize ConditionInterface.
     */
    public function organize(array $conditions): array
    {
        // @todo: Implement organize() method.
        return $conditions;
    }
}

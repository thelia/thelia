<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Condition;

/**
 * Manage how Condition could interact with each others
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionOrganizer implements ConditionOrganizerInterface
{
    /**
     * Organize ConditionInterface
     *
     * @param array $conditions Array of ConditionInterface
     *
     * @return array Array of ConditionInterface sorted
     */
    public function organize(array $conditions)
    {
        // @todo: Implement organize() method.
    }
}

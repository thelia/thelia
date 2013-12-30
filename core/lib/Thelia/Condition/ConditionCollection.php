<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Condition;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\Implementation\ConditionInterface;

/**
 * Manage a set of ConditionInterface
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionCollection
{
    /** @var array Array of ConditionInterface */
    protected $conditions = array();

    /**
     * Get Conditions
     *
     * @return array Array of ConditionInterface
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Add a ConditionInterface to the Collection
     *
     * @param ConditionInterface $condition Condition
     *
     * @return $this
     */
    public function add(ConditionInterface $condition)
    {
        $this->conditions[] = $condition;

        return $this;
    }

    /**
     * Check if there is at least one condition in the collection
     *
     * @return bool
     */
    public function isEmpty()
    {
        return (empty($this->conditions));
    }

    /**
     * Allow to compare 2 set of conditions
     *
     * @return string Jsoned data
     */
    public function __toString()
    {
        $arrayToSerialize = array();
        /** @var ConditionInterface $condition */
        foreach ($this->getConditions() as $condition) {
            $arrayToSerialize[] = $condition->getSerializableCondition();
        }

        return json_encode($arrayToSerialize);
    }


}
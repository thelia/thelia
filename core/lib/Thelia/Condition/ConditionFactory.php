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
use Thelia\Coupon\FacadeInterface;
use Thelia\Condition\ConditionCollection;


/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Manage how Condition could interact with the current application state (Thelia)
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionFactory
{
    /** @var ContainerInterface Service Container */
    protected $container = null;

    /** @var  FacadeInterface Provide necessary value from Thelia */
    protected $adapter;

    /** @var array ConditionCollection to process*/
    protected $conditions = null;

    /**
     * Constructor
     *
     * @param ContainerInterface $container Service container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->adapter = $container->get('thelia.facade');
    }

    /**
     * Serialize a collection of conditions
     *
     * @param ConditionCollection $collection A collection of conditions
     *
     * @return string A ready to be stored Condition collection
     */
    public function serializeConditionCollection(ConditionCollection $collection)
    {
        if ($collection->isEmpty()) {
            /** @var ConditionManagerInterface $conditionNone */
            $conditionNone = $this->container->get(
                'thelia.condition.match_for_everyone'
            );
            $collection->add($conditionNone);
        }
        $serializableConditions = array();
        $conditions = $collection->getConditions();
        if ($conditions !== null) {
            /** @var $condition ConditionManagerInterface */
            foreach ($conditions as $condition) {
                $serializableConditions[] = $condition->getSerializableCondition();
            }
        }

        return base64_encode(json_encode($serializableConditions));
    }

    /**
     * Unserialize a collection of conditions
     *
     * @param string $serializedConditions Serialized Conditions
     *
     * @return ConditionCollection Conditions ready to be processed
     */
    public function unserializeConditionCollection($serializedConditions)
    {
        $unserializedConditions = json_decode(base64_decode($serializedConditions));

        $collection = new ConditionCollection();

        if (!empty($unserializedConditions) && !empty($unserializedConditions)) {
            /** @var SerializableCondition $condition */
            foreach ($unserializedConditions as $condition) {
                if ($this->container->has($condition->conditionServiceId)) {
                    /** @var ConditionManagerInterface $conditionManager */
                    $conditionManager = $this->build(
                        $condition->conditionServiceId,
                        (array) $condition->operators,
                        (array) $condition->values
                    );
                    $collection->add(clone $conditionManager);
                }
            }
        }

        return $collection;
    }


    /**
     * Build a Condition from form
     *
     * @param string $conditionServiceId Condition class name
     * @param array  $operators          Condition Operator (<, >, = )
     * @param array  $values             Values setting this Condition
     *
     * @throws \InvalidArgumentException
     * @return ConditionManagerInterface Ready to use Condition or false
     */
    public function build($conditionServiceId, array $operators, array $values)
    {
        if (!$this->container->has($conditionServiceId)) {
            return false;
        }

        /** @var ConditionManagerInterface $condition */
        $condition = $this->container->get($conditionServiceId);
        $condition->setValidatorsFromForm($operators, $values);

        return $condition;
    }

    /**
     * Get Condition inputs from serviceId
     *
     * @param string $conditionServiceId ConditionManager class name
     *
     * @return array Ready to be drawn condition inputs
     */
    public function getInputs($conditionServiceId)
    {
        if (!$this->container->has($conditionServiceId)) {
            return false;
        }

        /** @var ConditionManagerInterface $condition */
        $condition = $this->container->get($conditionServiceId);

        return $condition->getValidators();
    }
}
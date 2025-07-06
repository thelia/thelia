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

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Coupon\FacadeInterface;

/**
 * Manage how Condition could interact with the current application state (Thelia).
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class ConditionFactory
{
    /** @var array ConditionCollection to process */
    protected $conditions;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container Service container
     */
    public function __construct(
        protected ContainerInterface $container,
        /** @var FacadeInterface Provide necessary value from Thelia */
        protected FacadeInterface $adapter
    )
    {
    }

    /**
     * Serialize a collection of conditions.
     *
     * @param ConditionCollection $collection A collection of conditions
     *
     * @return string A ready to be stored Condition collection
     */
    public function serializeConditionCollection(ConditionCollection $collection): string
    {
        if ($collection->count() == 0) {
            /** @var ConditionInterface $conditionNone */
            $conditionNone = $this->container->get(
                'thelia.condition.match_for_everyone'
            );
            $collection[] = $conditionNone;
        }

        $serializableConditions = [];
        /** @var $condition ConditionInterface */
        foreach ($collection as $condition) {
            $serializableConditions[] = $condition->getSerializableCondition();
        }

        return base64_encode(json_encode($serializableConditions));
    }

    /**
     * Unserialize a collection of conditions.
     *
     * @param string $serializedConditions Serialized Conditions
     *
     * @return ConditionCollection Conditions ready to be processed
     */
    public function unserializeConditionCollection(string $serializedConditions): ConditionCollection
    {
        $unserializedConditions = json_decode(base64_decode($serializedConditions));

        $collection = new ConditionCollection();

        if (!empty($unserializedConditions)) {
            /** @var SerializableCondition $condition */
            foreach ($unserializedConditions as $condition) {
                if ($this->container->has($condition->conditionServiceId)) {
                    /** @var ConditionInterface $conditionManager */
                    $conditionManager = $this->build(
                        $condition->conditionServiceId,
                        (array) $condition->operators,
                        (array) $condition->values
                    );
                    $collection[] = clone $conditionManager;
                }
            }
        }

        return $collection;
    }

    /**
     * Build a Condition from form.
     *
     * @param string $conditionServiceId Condition class name
     * @param array  $operators          Condition Operator (<, >, = )
     * @param array  $values             Values setting this Condition
     *
     * @throws InvalidArgumentException
     *
     * @return ConditionInterface Ready to use Condition or false
     */
    public function build(string $conditionServiceId, array $operators, array $values)
    {
        $conditionServiceId = urldecode($conditionServiceId);

        if (!$this->container->has($conditionServiceId)) {
            return false;
        }

        /** @var ConditionInterface $condition */
        $condition = $this->container->get($conditionServiceId);
        $condition->setValidatorsFromForm($operators, $values);

        return clone $condition;
    }

    /**
     * Get Condition inputs from serviceId.
     *
     * @param string $conditionServiceId ConditionManager class name
     *
     * @return array Ready to be drawn condition inputs
     */
    public function getInputsFromServiceId(string $conditionServiceId)
    {
        if (!$this->container->has($conditionServiceId)) {
            return false;
        }

        /** @var ConditionInterface $condition */
        $condition = $this->container->get($conditionServiceId);

        return $this->getInputsFromConditionInterface($condition);
    }

    /**
     * Get Condition inputs from serviceId.
     *
     * @param ConditionInterface $condition ConditionManager
     *
     * @return array Ready to be drawn condition inputs
     */
    public function getInputsFromConditionInterface(ConditionInterface $condition)
    {
        return $condition->getValidators();
    }
}

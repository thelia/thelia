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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\SerializableCondition;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionOperatorException;
use Thelia\Exception\InvalidConditionValueException;

/**
 * Manage how the application checks its state in order to check if it matches the implemented condition
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
interface ConditionInterface
{
    /**
     * Constructor
     *
     * @param FacadeInterface $adapter Service adapter
     */
    public function __construct(FacadeInterface $adapter);

    /**
     * Get Condition Service id
     *
     * @return string
     */
    public function getServiceId();

    /**
     * Check validators relevancy and store them
     *
     * @param array $operators an array of operators (greater than, less than, etc.) entered in the condition parameter input form, one for each condition defined by the Condition
     * @param array $values    an array of values entered in in the condition parameter input form, one for each condition defined by the Condition
     *
     * @throws InvalidConditionOperatorException
     * @throws InvalidConditionValueException
     *
     * @return $this
     */
    public function setValidatorsFromForm(array $operators, array $values);

    /**
     * Test if the current application state matches conditions
     *
     * @return bool
     */
    public function isMatching();

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName();

    /**
     * Get I18n tooltip
     * Explain in detail what the Condition checks
     *
     * @return string
     */
    public function getToolTip();

    /**
     * Get I18n summary
     * Explain briefly the condition with given values
     *
     * @return string
     */
    public function getSummary();

    /**
     * Return all validators
     *
     * @return array
     */
    public function getValidators();

    /**
     * Return a serializable Condition
     *
     * @return SerializableCondition
     */
    public function getSerializableCondition();

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs();
}

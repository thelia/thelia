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

use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Manage how the application checks its state in order to check if it matches the implemented condition
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
interface ConditionManagerInterface
{
    /**
     * Constructor
     *
     * @param FacadeInterface $adapter Service adapter
     */
    function __construct(FacadeInterface $adapter);

    /**
     * Get Condition Service id
     *
     * @return string
     */
    public function getServiceId();

    /**
     * Check validators relevancy and store them
     *
     * @param array $operators Operators the Admin set in BackOffice
     * @param array $values    Values the Admin set in BackOffice
     *
     * @throws \InvalidArgumentException
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
     * Return all available Operators for this condition
     *
     * @return array Operators::CONST
     */
    public function getAvailableOperators();


    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName();

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip();

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

}

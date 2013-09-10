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

namespace Thelia\Constraint\Rule;

use Thelia\Core\Translation\Translator;
use Thelia\Coupon\CouponAdapterInterface;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represents a condition of whether the Rule is applied or not
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
interface CouponRuleInterface
{
    /**
     * Constructor
     *
     * @param CouponAdapterInterface $adapter Service adapter
     */
    function __construct(CouponAdapterInterface $adapter);

    /**
     * Get Rule Service id
     *
     * @return string
     */
    public function getServiceId();

//    /**
//     * Check if backoffice inputs are relevant or not
//     *
//     * @return bool
//     */
//    public function checkBackOfficeInput();

//    /**
//     * Check if Checkout inputs are relevant or not
//     *
//     * @return bool
//     */
//    public function checkCheckoutInput();

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

//    /**
//     * Check if the current Checkout matches this condition
//     *
//     * @return bool
//     */
//    public function isMatching();

    /**
     * Test if Customer meets conditions
     *
     * @return bool
     */
    public function isMatching();

    /**
     * Return all available Operators for this Rule
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

//    /**
//     * Populate a Rule from a form admin
//     *
//     * @param array $operators Rule Operator set by the Admin
//     * @param array $values    Rule Values set by the Admin
//     *
//     * @return bool
//     */
//    public function populateFromForm(array$operators, array $values);


    /**
     * Return a serializable Rule
     *
     * @return SerializableRule
     */
    public function getSerializableRule();





}

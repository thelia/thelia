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

namespace Thelia\Condition\Implementation;

/**
 * Allow every one, perform no check
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForEveryone extends ConditionAbstract
{
    /** @var string Service Id from Resources/config.xml  */
    protected $serviceId = 'thelia.condition.match_for_everyone';

    /** @var array Available Operators (Operators::CONST) */
    protected $availableOperators = array();

    /**
     * Check validators relevancy and store them
     *
     * @param array $operators Operators the Admin set in BackOffice
     * @param array $values    Values the Admin set in BackOffice
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->setValidators();

        return $this;
    }

    /**
     * Check validators relevancy and store them
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    protected function setValidators()
    {
        $this->operators = array();
        $this->values = array();

        return $this;
    }

    /**
     * Test if Customer meets conditions
     *
     * @return bool
     */
    public function isMatching()
    {
        return true;
    }

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->translator->trans(
            'Everybody can use it (no condition)',
            array(),
            'condition'
        );
    }

    /**
     * Get I18n tooltip
     * Explain in detail what the Condition checks
     *
     * @return string
     */
    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'Will return always true',
            array(),
            'condition'
        );

        return $toolTip;
    }

    /**
     * Get I18n summary
     * Explain briefly the condition with given values
     *
     * @return string
     */
    public function getSummary()
    {
        $toolTip = $this->translator->trans(
            'Will return always true',
            array(),
            'condition'
        );

        return $toolTip;
    }

    /**
     * Generate inputs ready to be drawn
     *
     * @return array
     */
    protected function generateInputs()
    {
        return array();
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon Conditions
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs()
    {
        // No input
        return '';
    }

}

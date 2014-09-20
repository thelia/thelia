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

use Thelia\Coupon\FacadeInterface;

/**
 * Allow every one, perform no check
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForEveryone extends ConditionAbstract
{
    /**
     * @inheritdoc
     */
    public function __construct(FacadeInterface $facade)
    {
        // Define the allowed comparison operators
        $this->availableOperators = [];

        parent::__construct($facade);
    }

    /**
     * @inheritdoc
     */
    public function getServiceId()
    {
        return 'thelia.condition.match_for_everyone';
    }

    /**
     * @inheritdoc
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->operators = [];
        $this->values = [];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isMatching()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->translator->trans(
            'Unconditional usage',
            []
        );
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'This condition is always true',
            []
        );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function getSummary()
    {
        $toolTip = $this->translator->trans(
            'Unconditionnal usage',
            []
        );

        return $toolTip;
    }

    /**
     * @inheritdoc
    */
    protected function generateInputs()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        // No input
        return '';
    }
}

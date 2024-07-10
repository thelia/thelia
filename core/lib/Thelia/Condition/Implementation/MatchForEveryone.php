<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Condition\Implementation;

use Thelia\Coupon\FacadeInterface;

/**
 * Allow every one, perform no check.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class MatchForEveryone extends ConditionAbstract
{
    public function __construct(FacadeInterface $facade)
    {
        // Define the allowed comparison operators
        $this->availableOperators = [];

        parent::__construct($facade);
    }

    public function getServiceId()
    {
        return 'thelia.condition.match_for_everyone';
    }

    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->operators = [];
        $this->values = [];

        return $this;
    }

    public function isMatching()
    {
        return true;
    }

    public function getName()
    {
        return $this->translator->trans(
            'Unconditional usage',
            []
        );
    }

    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'This condition is always true',
            []
        );

        return $toolTip;
    }

    public function getSummary()
    {
        $toolTip = $this->translator->trans(
            'Unconditionnal usage',
            []
        );

        return $toolTip;
    }

    protected function generateInputs()
    {
        return [];
    }

    public function drawBackOfficeInputs()
    {
        // No input
        return '';
    }
}

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

use Thelia\Condition\Operators;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Tools\DateTimeFormat;

/**
 * Check a Checkout against its Product number
 *
 * @package Condition
 * @author  Franck Allimant <franck@cqfdev.fr>
 *
 */
class StartDate extends ConditionAbstract
{
    const START_DATE = 'start_date';

    /**
     * @inheritdoc
     */
    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = [
            self::START_DATE => [
                Operators::SUPERIOR_OR_EQUAL
            ]
        ];

        parent::__construct($facade);
    }

    /**
     * @inheritdoc
     */
    public function getServiceId()
    {
        return 'thelia.condition.start_date';
    }

    /**
     * @inheritdoc
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->checkComparisonOperatorValue($operators, self::START_DATE);

        if (! isset($values[self::START_DATE])) {
            $values[self::START_DATE] = time();
        }

        // Parse the entered date to get a timestamp, if we don't already have one
        if (! is_int($values[self::START_DATE])) {
            $date = \DateTime::createFromFormat($this->getDateFormat(), $values[self::START_DATE]);

            // Check that the date is valid
            if (false === $date) {
                throw new InvalidConditionValueException(
                    get_class(),
                    self::START_DATE
                );
            }

            $timestamp = $date->getTimestamp();
        } else {
            $timestamp = $values[self::START_DATE];
        }

        $this->operators = [ self::START_DATE => $operators[self::START_DATE] ];
        $this->values    = [ self::START_DATE => $timestamp ];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isMatching()
    {
        return $this->conditionValidator->variableOpComparison(
            time(),
            $this->operators[self::START_DATE],
            $this->values[self::START_DATE]
        );
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->translator->trans(
            'Start date',
            []
        );
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'The coupon is valid after a given date',
            []
        );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function getSummary()
    {
        $date = new \DateTime();
        $date->setTimestamp($this->values[self::START_DATE]);
        $strDate = $date->format($this->getDateFormat());

        $toolTip = $this->translator->trans(
            'Valid only from %date% to the coupon expiration date',
            [
                '%date%' => $strDate,
            ],
            'condition'
        );

        return $toolTip;
    }

    private function getDateFormat()
    {
        return DateTimeFormat::getInstance($this->facade->getRequest())->getFormat("date");
    }

    /**
     * @inheritdoc
     */
    protected function generateInputs()
    {
        return array(
            self::START_DATE => array(
                'availableOperators' => $this->availableOperators[self::START_DATE],
                'value' => '',
                'selectedOperator' => Operators::SUPERIOR_OR_EQUAL
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        if (isset($this->values[self::START_DATE])) {
            $date = new \DateTime();

            $date->setTimestamp($this->values[self::START_DATE]);

            $strDate = $date->format($this->getDateFormat());
        } else {
            $strDate = '';
        }

        return $this->facade->getParser()->render('coupon/condition-fragments/start-date-condition.html', [
                'fieldName'    => self::START_DATE,
                'criteria'     => Operators::SUPERIOR_OR_EQUAL,
                'dateFormat'   => $this->getDateFormat(),
                'currentValue' => $strDate
        ]);
    }
}

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

use Thelia\Condition\Operators;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Tools\DateTimeFormat;

/**
 * Check a Checkout against its Product number.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class StartDate extends ConditionAbstract
{
    public const START_DATE = 'start_date';

    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = [
            self::START_DATE => [
                Operators::SUPERIOR_OR_EQUAL,
            ],
        ];

        parent::__construct($facade);
    }

    public function getServiceId()
    {
        return 'thelia.condition.start_date';
    }

    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->checkComparisonOperatorValue($operators, self::START_DATE);

        if (!isset($values[self::START_DATE])) {
            $values[self::START_DATE] = time();
        }

        // Parse the entered date to get a timestamp, if we don't already have one
        if (!\is_int($values[self::START_DATE])) {
            $date = \DateTime::createFromFormat($this->getDateFormat(), $values[self::START_DATE]);

            // Check that the date is valid
            if (false === $date) {
                throw new InvalidConditionValueException(
                    __CLASS__,
                    self::START_DATE
                );
            }

            $timestamp = $date->getTimestamp();
        } else {
            $timestamp = $values[self::START_DATE];
        }

        $this->operators = [self::START_DATE => $operators[self::START_DATE]];
        $this->values = [self::START_DATE => $timestamp];

        return $this;
    }

    public function isMatching()
    {
        return $this->conditionValidator->variableOpComparison(
            time(),
            $this->operators[self::START_DATE],
            $this->values[self::START_DATE]
        );
    }

    public function getName()
    {
        return $this->translator->trans(
            'Start date',
            []
        );
    }

    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'The coupon is valid after a given date',
            []
        );

        return $toolTip;
    }

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
        return DateTimeFormat::getInstance($this->facade->getRequest())->getFormat('date');
    }

    protected function generateInputs()
    {
        return [
            self::START_DATE => [
                'availableOperators' => $this->availableOperators[self::START_DATE],
                'value' => '',
                'selectedOperator' => Operators::SUPERIOR_OR_EQUAL,
            ],
        ];
    }

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
                'fieldName' => self::START_DATE,
                'criteria' => Operators::SUPERIOR_OR_EQUAL,
                'dateFormat' => $this->getDateFormat(),
                'currentValue' => $strDate,
        ]);
    }
}

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
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;

/**
 * Check a Checkout against its Product number
 *
 * @package Condition
 * @author  Franck Allimant <franck@cqfdev.fr>
 *
 */
abstract class AbstractMatchCountries extends ConditionAbstract
{
    /** Condition 1st parameter : quantity */
    const COUNTRIES_LIST = 'countries';

    /**
     * @inheritdoc
     */
    public function __construct(FacadeInterface $facade)
    {
        $this->availableOperators = [
            self::COUNTRIES_LIST => [
                Operators::IN,
                Operators::OUT
            ]
        ];

        parent::__construct($facade);
    }

    abstract protected function getSummaryLabel($cntryStrList, $i18nOperator);

    abstract protected function getFormLabel();

    /**
     * @inheritdoc
     */
    public function setValidatorsFromForm(array $operators, array $values)
    {
        $this->checkComparisonOperatorValue($operators, self::COUNTRIES_LIST);

        // Use default values if data is not defined.
        if (! isset($operators[self::COUNTRIES_LIST]) || ! isset($values[self::COUNTRIES_LIST])) {
            $operators[self::COUNTRIES_LIST] = Operators::IN;
            $values[self::COUNTRIES_LIST] = [];
        }

        // Be sure that the value is an array, make one if required
        if (! is_array($values[self::COUNTRIES_LIST])) {
            $values[self::COUNTRIES_LIST] = array($values[self::COUNTRIES_LIST]);
        }

        // Check that at least one category is selected
        if (empty($values[self::COUNTRIES_LIST])) {
            throw new InvalidConditionValueException(
                get_class(),
                self::COUNTRIES_LIST
            );
        }

        $this->operators = [ self::COUNTRIES_LIST => $operators[self::COUNTRIES_LIST] ];
        $this->values    = [ self::COUNTRIES_LIST => $values[self::COUNTRIES_LIST] ];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isMatching()
    {
        // The delivery address should match one of the selected countries.

        /* TODO !!!! */

        return $this->conditionValidator->variableOpComparison(
            $this->facade->getNbArticlesInCart(),
            $this->operators[self::COUNTRIES_LIST],
            $this->values[self::COUNTRIES_LIST]
        );
    }

    /**
     * @inheritdoc
     */
    public function getSummary()
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::COUNTRIES_LIST]
        );

        $cntryStrList = '';

        $cntryIds = $this->values[self::COUNTRIES_LIST];

        if (null !== $cntryList = CountryQuery::create()->findPks($cntryIds)) {
            /** @var Country $cntry */
            foreach ($cntryList as $cntry) {
                $cntryStrList .= $cntry->setLocale($this->getCurrentLocale())->getTitle() . ', ';
            }

            $cntryStrList = rtrim($cntryStrList, ', ');
        }

        return $this->getSummaryLabel($cntryStrList, $i18nOperator);
    }

    /**
     * @inheritdoc
     */
    protected function generateInputs()
    {
        return array(
            self::COUNTRIES_LIST => array(
                'availableOperators' => $this->availableOperators[self::COUNTRIES_LIST],
                'value' => '',
                'selectedOperator' => Operators::IN
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->facade->getParser()->render(
            'coupon/condition-fragments/countries-condition.html',
            [
                'operatorSelectHtml'   => $this->drawBackOfficeInputOperators(self::COUNTRIES_LIST),
                'countries_field_name' => self::COUNTRIES_LIST,
                'values'               => isset($this->values[self::COUNTRIES_LIST]) ? $this->values[self::COUNTRIES_LIST] : array(),
                'countryLabel'         => $this->getFormLabel()
            ]
        );
    }
}

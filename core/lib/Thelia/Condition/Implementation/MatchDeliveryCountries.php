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

use Thelia\Core\Translation\Translator;
use Thelia\Exception\UnmatchableConditionException;

/**
 * Check a Checkout against its Product number.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class MatchDeliveryCountries extends AbstractMatchCountries
{
    public function getServiceId()
    {
        return 'thelia.condition.match_delivery_countries';
    }

    public function isMatching()
    {
        if (null === $this->facade->getCustomer()) {
            throw new UnmatchableConditionException(UnmatchableConditionException::getMissingCustomerMessage());
        }

        if (null === $deliveryAddress = $this->facade->getDeliveryAddress()) {
            throw new UnmatchableConditionException(Translator::getInstance()->trans('You must choose a delivery address before using this coupon.'));
        }

        return $this->conditionValidator->variableOpComparison(
            $deliveryAddress->getCountryId(),
            $this->operators[self::COUNTRIES_LIST],
            $this->values[self::COUNTRIES_LIST]
        );
    }

    public function getName()
    {
        return $this->translator->trans(
            'Delivery country',
            []
        );
    }

    public function getToolTip()
    {
        $toolTip = $this->translator->trans(
            'The coupon applies to the selected delivery countries',
            []
        );

        return $toolTip;
    }

    protected function getSummaryLabel($cntryStrList, $i18nOperator)
    {
        return $this->translator->trans(
            'Only if order shipping country is %op% <strong>%countries_list%</strong>',
            [
                '%countries_list%' => $cntryStrList,
                '%op%' => $i18nOperator,
            ]
        );
    }

    protected function getFormLabel()
    {
        return $this->translator->trans(
            'Delivery country is',
            []
        );
    }
}

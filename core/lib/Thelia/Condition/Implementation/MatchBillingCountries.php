<?php

declare(strict_types=1);

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
use Thelia\Model\Customer;

/**
 * Check a Checkout against its Product number.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class MatchBillingCountries extends AbstractMatchCountries
{
    public function getServiceId(): string
    {
        return 'thelia.condition.match_billing_countries';
    }

    public function isMatching(): bool
    {
        if (!($customer = $this->facade->getCustomer()) instanceof Customer) {
            throw new UnmatchableConditionException(UnmatchableConditionException::getMissingCustomerMessage());
        }

        if (null === $billingAddress = $customer->getDefaultAddress()) {
            throw new UnmatchableConditionException(Translator::getInstance()->trans('You must choose a billing address before using this coupon.'));
        }

        return $this->conditionValidator->variableOpComparison(
            $billingAddress->getCountryId(),
            $this->operators[self::COUNTRIES_LIST],
            $this->values[self::COUNTRIES_LIST],
        );
    }

    public function getName(): string
    {
        return $this->translator->trans(
            'Billing country',
            [],
        );
    }

    public function getToolTip(): string
    {
        return $this->translator->trans(
            'The coupon applies to the selected billing countries',
            [],
        );
    }

    protected function getSummaryLabel($cntryStrList, $i18nOperator): string
    {
        return $this->translator->trans(
            'Only if order billing country is %op% <strong>%countries_list%</strong>',
            [
                '%countries_list%' => $cntryStrList,
                '%op%' => $i18nOperator,
            ],
        );
    }

    protected function getFormLabel(): string
    {
        return $this->translator->trans(
            'Billing country is',
            [],
        );
    }
}

<?php

namespace Thelia\Model;

use Thelia\Exception\TaxEngineException;
use Thelia\Model\Base\Tax as BaseTax;

class Tax extends BaseTax
{
    public function calculateTax($amount)
    {
        if(false === filter_var($amount, FILTER_VALIDATE_FLOAT)) {
            throw new TaxEngineException('BAD AMOUNT FORMAT', TaxEngineException::BAD_AMOUNT_FORMAT);
        }

        $rate = $this->getRate();

        if($rate === null) {
            return 0;
        }

        return $amount * $rate * 0.01;
    }

    public function getTaxRuleCountryPosition()
    {
        try {
             $taxRuleCountryPosition = $this->getVirtualColumn(TaxRuleQuery::ALIAS_FOR_TAX_RULE_COUNTRY_POSITION);
        } catch(PropelException $e) {
            throw new PropelException("Virtual column `" . TaxRuleQuery::ALIAS_FOR_TAX_RULE_COUNTRY_POSITION . "` does not exist in Tax::getTaxRuleCountryPosition");
        }

        return $taxRuleCountryPosition;
    }

    public function getTaxRuleRateSum()
    {
        try {
            $taxRuleRateSum = $this->getVirtualColumn(TaxRuleQuery::ALIAS_FOR_TAX_RATE_SUM);
        } catch(PropelException $e) {
            throw new PropelException("Virtual column `" . TaxRuleQuery::ALIAS_FOR_TAX_RATE_SUM . "` does not exist in Tax::getTaxRuleRateSum");
        }

        return $taxRuleRateSum;
    }
}

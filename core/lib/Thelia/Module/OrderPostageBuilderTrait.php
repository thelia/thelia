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

namespace Thelia\Module;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorResolverTrait;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

/**
 * Turns an untaxed shipping cost into an OrderPostage, applying the tax rule
 * that governs this delivery module's postage.
 *
 * The rule is resolved in this order:
 *
 *  1. the tax rule id the module passes explicitly to buildOrderPostage();
 *  2. this module's own POSTAGE_TAX_RULE_CONFIG_KEY setting;
 *  3. the shop-wide `taxrule_id_delivery_module` setting.
 *
 * Without any of them, postage is left untaxed.
 */
trait OrderPostageBuilderTrait
{
    use TaxCalculatorResolverTrait;

    /**
     * Name of the module_config entry holding the tax rule applied to this
     * delivery module's postage. An empty or missing value means "use the
     * shop-wide delivery tax rule".
     */
    public const POSTAGE_TAX_RULE_CONFIG_KEY = 'postage_tax_rule_id';

    public function buildOrderPostage(float $untaxedPostage, Country $country, $locale, $taxRuleId = null)
    {
        $taxRule = $this->resolvePostageTaxRule((int) $taxRuleId ?: $this->getPostageTaxRuleId());

        $orderPostage = new OrderPostage();

        if (!$taxRule instanceof TaxRule) {
            $orderPostage->setAmount($untaxedPostage);
            $orderPostage->setAmountTax(0);
            $orderPostage->setTaxRuleTitle(null);

            return $orderPostage;
        }

        $taxCalculator = $this->createTaxCalculator();
        $taxCalculator->loadTaxRuleWithoutProduct($taxRule, $country);

        $orderPostage->setAmount($taxCalculator->getTaxedPrice($untaxedPostage));
        $orderPostage->setAmountTax($taxCalculator->getTaxAmountFromUntaxedPrice($untaxedPostage));
        $orderPostage->setTaxRuleTitle($taxRule->setLocale($locale)->getTitle());

        return $orderPostage;
    }

    /**
     * Tax rule this delivery module applies to its postage: its own setting
     * when one is configured, the shop-wide delivery tax rule otherwise.
     */
    public function getPostageTaxRuleId(): ?int
    {
        return $this->getModulePostageTaxRuleId() ?: ((int) ConfigQuery::read('taxrule_id_delivery_module') ?: null);
    }

    private function getModulePostageTaxRuleId(): ?int
    {
        $module = ModuleQuery::create()->findOneByCode($this->getCode());

        if (null === $module) {
            return null;
        }

        $taxRuleId = (int) ModuleConfigQuery::create()
            ->getConfigValue($module->getId(), self::POSTAGE_TAX_RULE_CONFIG_KEY, '0');

        return $taxRuleId ?: null;
    }

    private function resolvePostageTaxRule(?int $taxRuleId): ?TaxRule
    {
        if (!$taxRuleId) {
            return null;
        }

        return TaxRuleQuery::create()
            ->filterById($taxRuleId)
            ->orderByIsDefault(Criteria::DESC)
            ->findOne();
    }
}

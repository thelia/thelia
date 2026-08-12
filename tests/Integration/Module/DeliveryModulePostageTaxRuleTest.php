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

namespace Thelia\Tests\Integration\Module;

use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfig;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Module\AbstractDeliveryModuleWithState;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

/**
 * A shop that ships books at 5.5 % and gadgets at 20 % needs more than the one
 * shop-wide postage tax rule: each delivery module must be able to carry its own.
 */
final class DeliveryModulePostageTaxRuleTest extends IntegrationTestCase
{
    private const MODULE_CODE = 'PostageTaxRuleTestDelivery';

    protected function tearDown(): void
    {
        // ConfigQuery memoizes every variable it reads in a static cache that
        // outlives the transaction rollback. Left alone, the value written here
        // would answer the next test of the process while its row is gone.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testShopWideRuleAppliesWhenTheModuleHasNoSetting(): void
    {
        $country = $this->createFixtureFactory()->country();
        $shopWide = $this->createTaxRule($country, '20', 'VAT 20');
        ConfigQuery::write('taxrule_id_delivery_module', (string) $shopWide->getId());

        $postage = $this->buildPostage($country);

        self::assertSame('VAT 20', $postage->getTaxRuleTitle());
        self::assertEqualsWithDelta(12.0, $postage->getAmount(), 0.0001);
        self::assertEqualsWithDelta(2.0, $postage->getAmountTax(), 0.0001);
    }

    public function testModuleSettingOverridesTheShopWideRule(): void
    {
        $country = $this->createFixtureFactory()->country();
        $shopWide = $this->createTaxRule($country, '20', 'VAT 20');
        $moduleRule = $this->createTaxRule($country, '5.5', 'VAT 5.5');
        ConfigQuery::write('taxrule_id_delivery_module', (string) $shopWide->getId());

        $module = $this->createDeliveryModuleRow();
        $this->setModulePostageTaxRule($module, $moduleRule);

        $postage = $this->buildPostage($country);

        self::assertSame('VAT 5.5', $postage->getTaxRuleTitle());
        self::assertEqualsWithDelta(10.55, $postage->getAmount(), 0.0001);
        self::assertEqualsWithDelta(0.55, $postage->getAmountTax(), 0.0001);
    }

    public function testExplicitTaxRuleArgumentStillWins(): void
    {
        $country = $this->createFixtureFactory()->country();
        $shopWide = $this->createTaxRule($country, '20', 'VAT 20');
        $moduleRule = $this->createTaxRule($country, '5.5', 'VAT 5.5');
        ConfigQuery::write('taxrule_id_delivery_module', (string) $shopWide->getId());

        $module = $this->createDeliveryModuleRow();
        $this->setModulePostageTaxRule($module, $moduleRule);

        // Delivery modules that already carry their own tax rule setting pass it
        // as the fourth argument. That call must keep the exact same result.
        $postage = $this->buildPostage($country, $shopWide->getId());

        self::assertSame('VAT 20', $postage->getTaxRuleTitle());
        self::assertEqualsWithDelta(12.0, $postage->getAmount(), 0.0001);
    }

    public function testPostageStaysUntaxedWithoutAnyRule(): void
    {
        $country = $this->createFixtureFactory()->country();
        ConfigQuery::write('taxrule_id_delivery_module', '0');

        $postage = $this->buildPostage($country);

        self::assertNull($postage->getTaxRuleTitle());
        self::assertEqualsWithDelta(10.0, $postage->getAmount(), 0.0001);
        self::assertEqualsWithDelta(0.0, $postage->getAmountTax(), 0.0001);
    }

    private function buildPostage(Country $country, ?int $taxRuleId = null): OrderPostage
    {
        return $this->createDeliveryModule()->buildOrderPostage(10.0, $country, 'en_US', $taxRuleId);
    }

    private function createDeliveryModule(): AbstractDeliveryModuleWithState
    {
        return new class(self::MODULE_CODE) extends AbstractDeliveryModuleWithState {
            public function __construct(private readonly string $code)
            {
            }

            public function getCode(): string
            {
                return $this->code;
            }

            public function isValidDelivery(Country $country, ?State $state = null): bool
            {
                return true;
            }

            public function getPostage(Country $country, ?State $state = null): float
            {
                return 10.0;
            }
        };
    }

    private function createDeliveryModuleRow(): Module
    {
        $module = new Module();
        $module
            ->setCode(self::MODULE_CODE)
            ->setType(BaseModule::DELIVERY_MODULE_TYPE)
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace(self::MODULE_CODE.'\\'.self::MODULE_CODE)
            ->save($this->getPropelConnection());

        return $module;
    }

    private function setModulePostageTaxRule(Module $module, TaxRule $taxRule): void
    {
        (new ModuleConfig())
            ->setModuleId($module->getId())
            ->setName(AbstractDeliveryModuleWithState::POSTAGE_TAX_RULE_CONFIG_KEY)
            ->setValue((string) $taxRule->getId())
            ->save($this->getPropelConnection());
    }

    private function createTaxRule(Country $country, string $percent, string $title): TaxRule
    {
        $factory = $this->createFixtureFactory();
        // A non-empty override array forces a rule of its own instead of reusing the seeded one.
        $taxRule = $factory->taxRule(['isDefault' => false]);
        $taxRule->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        $tax = $factory->tax(['requirements' => ['percent' => $percent], 'title' => $title]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }
}

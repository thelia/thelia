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

namespace Thelia\Tests\Integration\Quote;

use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\DTO\QuoteTotals;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Model\TaxRuleQuery;

/**
 * What a revision comes to, with the tax engine of the shop and the way a cart and an order do the
 * sums: the discount with taxes comes off the total with taxes, the postage is taxed after it.
 */
final class QuoteTotalsTest extends QuoteDomainTestCase
{
    public function testTheSendingOfTheShopComesToSixteenHundredAndTwenty(): void
    {
        $scenario = $this->requestedScenario();

        $revision = $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));

        // (1 080 + 180 + 100 = 1 360 + 15 of postage) x 1.2 - 30
        self::assertEquals(
            new QuoteTotals('1360.000000', '272.000000', '15.000000', '3.000000', '30.000000', '1620.000000', '1350.000000'),
            $this->quotes->totals($revision),
        );
    }

    public function testTheNewSendingComesToFifteenHundredSeventySixAndEightyCents(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef())->getId(), null, '85')]);

        $revision = $this->quotes->saveDraft($quote, null, $this->draftFrom(
            $this->revisionOf($quote, 2),
            [$scenario['a']->getRef() => ['price' => '87']],
            discount: '30',
            postage: '15',
        ));

        // (1 044 + 180 + 100 = 1 324 + 15) x 1.2 - 30
        self::assertEquals(
            new QuoteTotals('1324.000000', '264.800000', '15.000000', '3.000000', '30.000000', '1576.800000', '1314.000000'),
            $this->quotes->totals($revision),
        );
    }

    public function testTheTotalsFrozenOnTheRevisionAreThoseOfTheSending(): void
    {
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));
        $this->quotes->send($scenario['quote'], null);

        $revision = $this->revisionOf($scenario['quote'], 2);

        self::assertSame('1620.000000', $revision->getTotalTaxed());
        self::assertSame('1350.000000', $revision->getTotalUntaxed());
    }

    public function testThePostageIsTaxedByTheRuleOfTheDeliveryModuleBeforeTheRuleOfTheShop(): void
    {
        $scenario = $this->requestedScenario();
        $module = ModuleQuery::create()->findOneByCode('CustomDelivery');
        self::assertNotNull($module);
        $reduced = $this->taxRuleOf('5.000000', 'VAT 5');
        ModuleConfigQuery::create()->setConfigValue((int) $module->getId(), 'postage_tax_rule_id', (string) $reduced->getId());
        ModuleConfigQuery::resetConfigCache();

        $withModule = $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom($this->revisionOf($scenario['quote'], 1), postage: '100', deliveryModuleId: (int) $module->getId()));
        $totals = $this->quotes->totals($withModule);

        self::assertSame('5.000000', $totals->postageTax, 'the rule of the module, 5 %, not the 20 % of the shop');
        self::assertSame('1545.000000', $totals->totalTaxed, 'items 1 440 + postage 100 + 5');

        $withoutModule = $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom($this->revisionOf($scenario['quote'], 1), postage: '100'));

        self::assertSame('20.000000', $this->quotes->totals($withoutModule)->postageTax, 'the shop-wide rule, 20 %');
    }

    public function testThePostageIsNotTaxedWhenNoRuleAppliesToIt(): void
    {
        $scenario = $this->requestedScenario();
        ConfigQuery::write('taxrule_id_delivery_module', '0');
        ConfigQuery::resetCache();

        $draft = $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom($this->revisionOf($scenario['quote'], 1), postage: '100'));

        $totals = $this->quotes->totals($draft);
        self::assertSame('0.000000', $totals->postageTax);
        self::assertSame('1540.000000', $totals->totalTaxed);
    }

    public function testThePostageFollowsTheGoodsWhenTheShopAsksForIt(): void
    {
        $buyer = $this->customer();
        $reduced = $this->taxRuleOf('5.000000', 'VAT 5');
        $standard = $this->product('100', 'Standard');
        $book = $this->fixtures->product($this->fixtures->category(), $reduced, $this->fixtures->currency(), ['basePrice' => 100.0, 'baseQuantity' => 100, 'title' => 'Book']);
        $quote = $this->quotes->requestFromCart($this->cartOf($buyer, [[$standard, 1.0, '100'], [$book, 1.0, '100']]), $buyer, new QuoteRequestInput());
        $draft = fn (string $postage) => $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1), postage: $postage));

        ConfigQuery::write('postage_tax_strategy', 'pro_rata');
        ConfigQuery::resetCache();
        self::assertSame('2.500000', $this->quotes->totals($draft('20'))->postageTax, '10 at 20 %, 10 at 5 %');

        ConfigQuery::write('postage_tax_strategy', 'highest_rate');
        ConfigQuery::resetCache();
        self::assertSame('4.000000', $this->quotes->totals($draft('20'))->postageTax, 'all of it at 20 %');

        ConfigQuery::write('postage_tax_strategy', 'single_rule');
        ConfigQuery::resetCache();
        self::assertSame('4.000000', $this->quotes->totals($draft('20'))->postageTax, 'the shop-wide rule, 20 %');
    }

    public function testTheSumsOfADraftLeaveNoLineOfACartOnTheProductsEveryoneElseReads(): void
    {
        $buyer = $this->customer();
        $standard = $this->product('100', 'Standard');
        $quote = $this->quotes->requestFromCart($this->cartOf($buyer, [[$standard, 1.0, '100']]), $buyer, new QuoteRequestInput());
        $draft = $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1), postage: '20'));
        ConfigQuery::write('postage_tax_strategy', 'pro_rata');
        ConfigQuery::resetCache();

        // A product that is in the pool is the one the sums read: whatever they leave on it stays for every later reader.
        $this->readThroughTheInstancePool();
        $pooled = ProductQuery::create()->findPk($standard->getId());
        self::assertNotNull($pooled);

        self::assertSame('4.000000', $this->quotes->totals($draft)->postageTax, 'the postage follows the goods');
        self::assertNull((new \ReflectionProperty($pooled, 'collCartItems'))->getValue($pooled), 'no line of a cart that was never saved');
    }

    public function testTheTaxesAreThoseOfTheCountryOfTheDeliveryAddress(): void
    {
        $buyer = $this->customer();
        $abroad = (new Country())->setIsocode('998')->setIsoalpha2('YY')->setIsoalpha3('YYY')->setVisible(1)->setShopCountry(false);
        $abroad->save($this->getPropelConnection());
        $address = $this->fixtures->address($buyer, $abroad);

        $quote = $this->request($buyer, null, new QuoteRequestInput(deliveryAddressId: $address->getId()));

        // The 20 % rule of the shop only covers its own country.
        self::assertSame('200.000000', $this->revisionOf($quote, 1)->getTotalTaxed());
        self::assertSame('0.000000', $this->quotes->totals($this->revisionOf($quote, 1))->itemsTax);

        $at = (new TaxRuleCountry())->setTaxRuleId($this->vatRule()->getId())->setCountryId($abroad->getId())->setTaxId($this->vatRule()->getTaxRuleCountries()->getFirst()->getTaxId())->setPosition(1);
        $at->save($this->getPropelConnection());
        TaxRuleQuery::resetCache();

        self::assertSame('0.000000', $this->quotes->totals($this->revisionOf($quote, 1))->itemsTax, 'the revision sent keeps the taxes it was frozen with');

        $draft = $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 1)));
        self::assertSame('40.000000', $this->quotes->totals($draft)->itemsTax, 'the same rule, declared for that country, on what is worked out now');
    }

    public function testTheLinesAreTotalledTheWayTheCartTotalsThem(): void
    {
        $totals = [];

        foreach ([ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS] as $roundingMode) {
            ConfigQuery::write('order_rounding_mode', (string) $roundingMode);
            ConfigQuery::resetCache();
            $buyer = $this->customer();
            $cart = $this->cartOf($buyer, [[$this->product('33.33', 'Odd'), 3.0, '33.33']]);
            $quote = $this->quotes->requestFromCart($cart, $buyer, new QuoteRequestInput());

            $quoteTotals = $this->quotes->totals($this->revisionOf($quote, 1));
            $totals[$roundingMode] = $cart->getTaxedAmount(Country::getDefaultCountry(), false);

            self::assertSame($totals[$roundingMode], round((float) $quoteTotals->itemsUntaxed + (float) $quoteTotals->itemsTax, 2), 'rounding mode '.$roundingMode);
        }

        self::assertNotSame($totals[ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS], $totals[ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS], 'the two modes disagree on this line: the check is not an empty one');
    }

    public function testADiscountHigherThanTheLinesIsRefusedAtTheSendingAndNeverGoesBelowZero(): void
    {
        $scenario = $this->requestedScenario();
        $draft = $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom($this->revisionOf($scenario['quote'], 1), discount: '5000', postage: '10'));

        $totals = $this->quotes->totals($draft);
        self::assertSame('1440.000000', $totals->discount, 'the discount that applies cannot exceed the lines');
        self::assertSame('12.000000', $totals->totalTaxed, 'the postage 10 + 2 of tax is still due');

        try {
            $this->quotes->send($scenario['quote'], null);
            self::fail('The sending was not refused.');
        } catch (QuoteDraftRefusedException $refused) {
            self::assertSame(QuoteDraftRefusedException::DISCOUNT_ABOVE_TOTAL, $refused->reason());
        }
    }

    private function taxRuleOf(string $percent, string $title): TaxRule
    {
        $rule = $this->fixtures->taxRule(['isDefault' => false]);
        $tax = $this->fixtures->tax(['requirements' => ['percent' => $percent], 'title' => $title]);
        (new TaxRuleCountry())
            ->setTaxRuleId($rule->getId())
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $rule;
    }
}

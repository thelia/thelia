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

namespace Thelia\Tests\Integration\Domain\Taxation;

use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItem;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\CountryTableMap;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

final class VatExemptionResolverQueriesTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testPricingTheLinesOfACartReadsTheCountriesOnceNotOncePerLine(): void
    {
        $factory = $this->createFixtureFactory();
        $belgium = $factory->country(['isocode' => 'BE', 'isoalpha2' => 'BE', 'isoalpha3' => 'BEX']);
        $france = $factory->country(['isocode' => 'FR', 'isoalpha2' => 'FR', 'isoalpha3' => 'FRX']);
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $france->getId());

        $cart = $factory->cart();
        $address = (new CartAddress())
            ->setCustomerTitleId($factory->customerTitle()->getId())
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setAddress1('1 Main Street')
            ->setAddress2('')
            ->setAddress3('')
            ->setZipcode('1000')
            ->setCity('Brussels')
            ->setCompany('Acme')
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-1 day'))
            ->setCountryId($belgium->getId());
        $address->save($this->getPropelConnection());
        $cart->setAddressInvoiceId($address->getId())->save($this->getPropelConnection());

        $currency = $factory->currency();
        $category = $factory->category();
        $taxRule = TaxRuleQuery::create()->findOne();
        self::assertNotNull($taxRule);

        for ($line = 0; $line < 6; ++$line) {
            $factory->cartItem($cart, $factory->product($category, $taxRule, $currency, ['baseQuantity' => 100]));
        }

        CartTableMap::clearInstancePool();
        CartItemTableMap::clearInstancePool();
        CartAddressTableMap::clearInstancePool();
        CountryTableMap::clearInstancePool();

        $items = CartQuery::create()->findPk($cart->getId())?->getCartItems();
        self::assertNotNull($items);
        self::assertCount(6, $items);

        $statements = $this->recordSqlQueries(static function () use ($items, $belgium): void {
            /** @var CartItem $item */
            foreach ($items as $item) {
                $item->getTaxedPrice($belgium);
            }
        });

        self::assertLessThanOrEqual(2, self::countSqlQueriesSelectingFrom($statements, 'country'), implode("\n", $statements));
    }

    public function testPricingAWholeCartDecidesTheExemptionOnceWhateverItsLines(): void
    {
        $oneLine = $this->readsWhilePricing(1);
        $sixLines = $this->readsWhilePricing(6);

        self::assertSame($oneLine['cart'], $sixLines['cart'], 'Reads of cart grow with the lines.');
        self::assertSame($oneLine['cart_address'], $sixLines['cart_address'], 'Reads of cart_address grow with the lines.');
        self::assertSame(1, $sixLines['decisions']);
    }

    /**
     * @return array{cart: int, cart_address: int, decisions: int}
     */
    private function readsWhilePricing(int $lines): array
    {
        $factory = $this->createFixtureFactory();
        $belgium = $factory->country(['isocode' => 'BE', 'isoalpha2' => 'BE', 'isoalpha3' => 'BEX']);
        $france = $factory->country(['isocode' => 'FR', 'isoalpha2' => 'FR', 'isoalpha3' => 'FRX']);
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $france->getId());

        $cart = $factory->cart();
        $address = (new CartAddress())
            ->setCustomerTitleId($factory->customerTitle()->getId())
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setAddress1('1 Main Street')
            ->setAddress2('')
            ->setAddress3('')
            ->setZipcode('1000')
            ->setCity('Brussels')
            ->setCompany('Acme')
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-1 day'))
            ->setCountryId($belgium->getId());
        $address->save($this->getPropelConnection());
        $cart->setAddressInvoiceId($address->getId())->save($this->getPropelConnection());

        $currency = $factory->currency();
        $category = $factory->category();
        $taxRule = TaxRuleQuery::create()->findOne();
        self::assertNotNull($taxRule);

        for ($line = 0; $line < $lines; ++$line) {
            $factory->cartItem($cart, $factory->product($category, $taxRule, $currency, ['baseQuantity' => 100]));
        }

        CartTableMap::clearInstancePool();
        CartItemTableMap::clearInstancePool();
        CartAddressTableMap::clearInstancePool();

        $pricedCart = CartQuery::create()->findPk($cart->getId());
        self::assertNotNull($pricedCart);
        self::assertCount($lines, $pricedCart->getCartItems());

        $decisions = 0;
        $countDecision = static function () use (&$decisions): void {
            ++$decisions;
        };
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(TheliaEvents::TAX_GET_CART_CALCULATOR, $countDecision, 10000);

        try {
            $statements = $this->recordSqlQueries(static function () use ($pricedCart, $belgium): void {
                $pricedCart->getTaxedAmount($belgium);
            });
        } finally {
            $dispatcher->removeListener(TheliaEvents::TAX_GET_CART_CALCULATOR, $countDecision);
        }

        return [
            'cart' => self::countSqlQueriesSelectingFrom($statements, 'cart'),
            'cart_address' => self::countSqlQueriesSelectingFrom($statements, 'cart_address'),
            'decisions' => $decisions,
        ];
    }
}

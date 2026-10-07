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

use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Enum\VatExemptionState;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Reverse charge crosses a border between two VAT-liable parties of the Union,
 * on a number somebody actually checked and recently enough to still mean
 * something. Every one of those conditions has to hold; each test below removes
 * exactly one of them and expects the order to be taxed again.
 */
final class VatExemptionResolverTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->configure(VatExemptionMode::VERIFIED_VAT_NUMBER);
    }

    protected function tearDown(): void
    {
        // ConfigQuery and Country memoize in static caches that outlive the
        // transaction rollback: left alone, what is written here would leak into
        // every later test of the suite.
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testABuyerVerifiedInAnotherMemberStateIsExempted(): void
    {
        $cart = $this->cartBilledTo('BE', new \DateTime('-10 days'));

        self::assertTrue($this->resolver()->isExemptedForCart($cart));
    }

    public function testAVerificationOlderThanItsLifetimeNoLongerExempts(): void
    {
        $cart = $this->cartBilledTo('BE', new \DateTime('-91 days'));

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testAnAddressNobodyVerifiedIsTaxed(): void
    {
        $cart = $this->cartBilledTo('BE', null);

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testABuyerInTheShopsOwnCountryIsTaxedEvenWhenVerified(): void
    {
        $cart = $this->cartBilledTo('FR', new \DateTime('-10 days'));

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testABuyerOutsideTheUnionIsTaxedEvenWhenVerified(): void
    {
        $cart = $this->cartBilledTo('CH', new \DateTime('-10 days'));

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testTheSettingLeftDisabledTaxesAnOtherwiseQualifyingBuyer(): void
    {
        $this->configure(VatExemptionMode::DISABLED);
        $cart = $this->cartBilledTo('BE', new \DateTime('-10 days'));

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testAShopOutOfTheVatScopeExemptsNobody(): void
    {
        ConfigQuery::write('store_vat_exempt', '1');
        $cart = $this->cartBilledTo('BE', new \DateTime('-10 days'));

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testAShopCountryThatNoLongerExistsExemptsNothingInsteadOfFailing(): void
    {
        $cart = $this->cartBilledTo('BE', new \DateTime('-10 days'));
        self::assertTrue($this->resolver()->isExemptedForCart($cart), 'Control: the cart exempts while the shop country exists.');

        ConfigQuery::write('store_country', '2147483647');

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testAVerifiedNumberOfAnotherMemberStateExemptsNothing(): void
    {
        $cart = $this->cartBilledTo('BE', new \DateTime('-10 days'));
        self::assertTrue($this->resolver()->isExemptedForCart($cart), 'Control: a Belgian number on a Belgian address exempts.');

        $cart->getCartAddressRelatedByAddressInvoiceId()->setVatNumber('DE136695976')->save($this->getPropelConnection());

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testACartWithNoBillingAddressIsTaxed(): void
    {
        self::assertFalse($this->resolver()->isExemptedForCart($this->factory->cart()));
    }

    public function testAVerificationNextToNoNumberExemptsNothing(): void
    {
        $cart = $this->cartBilledTo('BE', new \DateTime('-10 days'));
        $cart->getCartAddressRelatedByAddressInvoiceId()->setVatNumber(null)->save($this->getPropelConnection());

        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testTheStateTellsANumberNobodyVerified(): void
    {
        self::assertSame(VatExemptionState::NOT_VERIFIED, $this->resolver()->stateForCart($this->cartBilledTo('BE', null)));
    }

    public function testTheStateTellsAVerificationThatExpired(): void
    {
        ConfigQuery::write('vat_verification_lifetime_days', '90');

        self::assertSame(VatExemptionState::VERIFICATION_EXPIRED, $this->resolver()->stateForCart($this->cartBilledTo('BE', new \DateTime('-100 days'))));
    }

    public function testTheStateOfABuyerAtHomeSaysNothingOfItsNumber(): void
    {
        self::assertSame(VatExemptionState::NOT_APPLICABLE, $this->resolver()->stateForCart($this->cartBilledTo('FR', null)));
    }

    public function testTheStateOfAnExemptedBuyer(): void
    {
        self::assertSame(VatExemptionState::EXEMPTED, $this->resolver()->stateForCart($this->cartBilledTo('BE', new \DateTime('-10 days'))));
    }

    private function resolver(): VatExemptionResolver
    {
        return $this->getService(VatExemptionResolver::class);
    }

    private function configure(VatExemptionMode $mode): void
    {
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, $mode->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $this->countryOf('FR')->getId());
    }

    private function cartBilledTo(string $isoAlpha2, ?\DateTime $verifiedAt): Cart
    {
        $cart = $this->factory->cart();

        $billingAddress = new CartAddress();
        $billingAddress
            ->setCustomerTitleId($this->factory->customerTitle()->getId())
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setAddress1('1 Main Street')
            ->setAddress2('')
            ->setAddress3('')
            ->setZipcode('1000')
            ->setCity('Brussels')
            ->setCompany('Acme')
            ->setVatNumber($isoAlpha2.'0123456789')
            ->setVatVerifiedAt($verifiedAt)
            ->setCountryId($this->countryOf($isoAlpha2)->getId())
            ->save($this->getPropelConnection());

        $cart->setAddressInvoiceId($billingAddress->getId())->save($this->getPropelConnection());

        return $cart;
    }

    private function countryOf(string $isoAlpha2): Country
    {
        return $this->factory->country([
            'isocode' => $isoAlpha2,
            'isoalpha2' => $isoAlpha2,
            'isoalpha3' => $isoAlpha2.'X',
        ]);
    }
}

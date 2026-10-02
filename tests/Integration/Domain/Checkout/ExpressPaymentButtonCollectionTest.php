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

namespace Thelia\Tests\Integration\Domain\Checkout;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Checkout\DTO\ExpressPaymentButton;
use Thelia\Domain\Checkout\Enum\ExpressPaymentZone;
use Thelia\Domain\Checkout\Service\ExpressCheckoutConfirmationToken;
use Thelia\Domain\Checkout\Service\ExpressPaymentButtonCollector;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\ExpressPaymentModuleInterface;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Payment\ExpressPaymentTestModule;

/**
 * Which express payment buttons a shop shows, and where.
 *
 * Two decisions meet here and neither may override the other: the merchant says in which
 * places of the shop a wallet button is allowed at all, and the module says whether it
 * has one to show for this cart. A shop that turns nothing on shows nothing, whatever
 * modules are installed — which is every shop that upgrades.
 */
final class ExpressPaymentButtonCollectionTest extends IntegrationTestCase
{
    private ?string $previousZones = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousZones = ConfigQuery::read(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME);
        $this->deactivateTheShopsOwnExpressModules();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, (string) $this->previousZones);

        parent::tearDown();
    }

    public function testAShopThatEnabledNoZoneShowsNothing(): void
    {
        $cart = $this->aFilledCart();
        $this->registerTheExpressModule();
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, '');

        self::assertSame([], $this->collector()->collect($cart, ExpressPaymentZone::Checkout));
        self::assertFalse($this->collector()->isZoneEnabled(ExpressPaymentZone::Checkout));
    }

    public function testAModuleFillsAnEnabledZone(): void
    {
        $cart = $this->aFilledCart();
        $this->registerTheExpressModule();
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, ExpressPaymentZone::Checkout->value);

        $buttons = $this->collector()->collect($cart, ExpressPaymentZone::Checkout);

        self::assertCount(1, $buttons);
        self::assertInstanceOf(ExpressPaymentButton::class, $buttons[0]);
        self::assertSame(ExpressPaymentTestModule::MODULE_CODE, $buttons[0]->paymentModuleCode);
        self::assertSame('wallet', $buttons[0]->code);
        self::assertSame(['data-zone' => 'checkout'], $buttons[0]->attributes);
    }

    /**
     * The module draws the button, the shop says where it is confirmed and with what
     * proof: a module that had to name its own confirmation route would also have to
     * identify the buyer, and getting that wrong costs the cart.
     */
    public function testEveryButtonCarriesTheShopsRoutesAndATokenForThisCart(): void
    {
        $cart = $this->aFilledCart();
        $this->registerTheExpressModule();
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, ExpressPaymentZone::Checkout->value);

        $button = $this->collector()->collect($cart, ExpressPaymentZone::Checkout)[0];
        $moduleId = (int) ModuleQuery::create()->findOneByCode(ExpressPaymentTestModule::MODULE_CODE)?->getId();

        self::assertSame('/checkout/express/'.ExpressPaymentTestModule::MODULE_CODE.'/confirm', $button->confirmationUrl);
        self::assertSame('/checkout/express/'.ExpressPaymentTestModule::MODULE_CODE.'/amount', $button->amountUrl);
        self::assertNotNull($button->confirmationToken);
        self::assertTrue(
            $this->getService(ExpressCheckoutConfirmationToken::class)->isIssuedFor($button->confirmationToken, $cart, $moduleId),
            'The token must be the one the confirmation route accepts for this cart.',
        );
    }

    /**
     * The merchant may enable the zone while the module never claimed it. The module wins:
     * it knows whether its wallet can be asked to pay there.
     */
    public function testAZoneTheModuleDoesNotClaimStaysEmpty(): void
    {
        $cart = $this->aFilledCart();
        $this->registerTheExpressModule();
        ExpressPaymentTestModule::$zones = [];
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, ExpressPaymentZone::Checkout->value);

        self::assertSame([], $this->collector()->collect($cart, ExpressPaymentZone::Checkout));
    }

    public function testAModuleThatRefusesThisCartShowsNoButton(): void
    {
        $cart = $this->aFilledCart();
        $this->registerTheExpressModule();
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, ExpressPaymentZone::Checkout->value);

        ExpressPaymentTestModule::$accepts = false;
        self::assertSame([], $this->collector()->collect($cart, ExpressPaymentZone::Checkout));

        ExpressPaymentTestModule::$accepts = true;
        ExpressPaymentTestModule::$offersButton = false;
        self::assertSame([], $this->collector()->collect($cart, ExpressPaymentZone::Checkout));
    }

    /**
     * The front posts its confirmation to the module the button names. A button naming
     * another module would send a wallet's money to the wrong gateway, so it is dropped
     * rather than shown.
     */
    public function testAButtonClaimingAnotherModuleIsDropped(): void
    {
        $cart = $this->aFilledCart();
        $this->registerTheExpressModule();
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, ExpressPaymentZone::Checkout->value);

        ExpressPaymentTestModule::$claimedModuleId = 999999;

        self::assertSame([], $this->collector()->collect($cart, ExpressPaymentZone::Checkout));
    }

    /**
     * Cheque and FreeOrder know nothing of this contract and must stay untouched: the
     * whole point of a separate interface is that no existing module changes.
     */
    public function testPaymentModulesWithoutTheContractAreIgnored(): void
    {
        $cart = $this->aFilledCart();
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, ExpressPaymentZone::Checkout->value);

        self::assertSame([], $this->collector()->collect($cart, ExpressPaymentZone::Checkout));
    }

    /**
     * Seen on the dev shop right after an express order: the cart had been handed back
     * empty, and the wallet button was still sitting under it. A wallet opened over
     * nothing can only end in a refusal nobody asked for.
     */
    public function testAnEmptyCartOffersNoButton(): void
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart();

        $this->registerTheExpressModule();
        ConfigQuery::write(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, ExpressPaymentZone::Checkout->value);

        self::assertSame(0, $cart->countCartItems(), 'This test is only meaningful on an empty cart.');
        self::assertSame([], $this->collector()->collect($cart, ExpressPaymentZone::Checkout));
    }

    private function registerTheExpressModule(): void
    {
        $module = ModuleQuery::create()->findOneByCode(ExpressPaymentTestModule::MODULE_CODE);

        if (null === $module) {
            $module = (new Module())
                ->setCode(ExpressPaymentTestModule::MODULE_CODE)
                ->setFullNamespace(ExpressPaymentTestModule::class)
                ->setVersion('1.0.0')
                ->setType(BaseModule::PAYMENT_MODULE_TYPE)
                ->setCategory('payment')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $module->save($this->getPropelConnection());
        }

        ExpressPaymentTestModule::reset((int) $module->getId());

        static::getContainer()->set('module.'.ExpressPaymentTestModule::MODULE_CODE, new ExpressPaymentTestModule());
    }

    /**
     * The express modules a shop installed would add their buttons to every answer; the
     * ordinary payment modules stay active, they are what the contract has to ignore.
     */
    private function deactivateTheShopsOwnExpressModules(): void
    {
        $paymentModules = ModuleQuery::create()
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE)
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->filterByCode(ExpressPaymentTestModule::MODULE_CODE, Criteria::NOT_EQUAL)
            ->find($this->getPropelConnection());

        foreach ($paymentModules as $module) {
            if (is_a((string) $module->getFullNamespace(), ExpressPaymentModuleInterface::class, true)) {
                $module->setActivate(BaseModule::IS_NOT_ACTIVATED)->save($this->getPropelConnection());
            }
        }
    }

    /**
     * A cart with something in it, read back from the database: the instance the fixture
     * returns carries an item collection loaded before the item existed, and counting on
     * it answers zero.
     */
    private function aFilledCart(): \Thelia\Model\Cart
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart();
        $factory->cartItem($cart, $factory->product($factory->category(), $factory->taxRule(), $factory->currency()));

        return CartQuery::create()->findPk($cart->getId()) ?? $cart;
    }

    private function collector(): ExpressPaymentButtonCollector
    {
        return $this->getService(ExpressPaymentButtonCollector::class);
    }
}

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

use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\Enum\CheckoutDisplayMode;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Service\ExpressPaymentCheckoutGuard;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Payment\ExpressPaymentTestModule;

/**
 * An express payment module is one of the payment methods of the checkout, in both of its
 * layouts, and the checkout never places an order with it itself.
 *
 * Placed by the checkout, a wallet that takes the money in its own sheet would give an
 * order it never takes the money for. Only the wallet's confirmation places it, unless the
 * module states that it is also an ordinary checkout payment method.
 */
final class ExpressPaymentAtCheckoutTest extends IntegrationTestCase
{
    private ?string $previousDisplayMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDisplayMode = ConfigQuery::read('checkout_display_mode');
        ConfigQuery::write('checkout_display_mode', CheckoutDisplayMode::Steps->value);
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('checkout_display_mode', (string) $this->previousDisplayMode);

        parent::tearDown();
    }

    /**
     * In both layouts of the checkout the wallet is one of the payment methods: choosing it
     * turns the order button into the wallet's button.
     */
    public function testTheCheckoutInStepsLetsTheBuyerChooseAModuleThatOnlyTakesExpressPayment(): void
    {
        $cart = $this->aFilledCart();
        $moduleId = $this->registerTheExpressModule();

        $this->selection()->setPaymentModule(new CheckoutDTO(cart: $cart, paymentModuleId: $moduleId));

        self::assertSame($moduleId, (int) $cart->getPaymentModuleId());
    }

    public function testAModuleThatIsAlsoACheckoutPaymentMethodCanBeChosen(): void
    {
        $cart = $this->aFilledCart();
        $moduleId = $this->registerTheExpressModule();
        ExpressPaymentTestModule::$alsoOfferedAtCheckout = true;

        $this->selection()->setPaymentModule(new CheckoutDTO(cart: $cart, paymentModuleId: $moduleId));

        self::assertSame($moduleId, (int) $cart->getPaymentModuleId());
    }

    public function testTheOnePageCheckoutLetsTheBuyerChooseAModuleThatOnlyTakesExpressPayment(): void
    {
        ConfigQuery::write('checkout_display_mode', CheckoutDisplayMode::OnePage->value);
        $cart = $this->aFilledCart();
        $moduleId = $this->registerTheExpressModule();

        $this->selection()->setPaymentModule(new CheckoutDTO(cart: $cart, paymentModuleId: $moduleId));

        self::assertSame($moduleId, (int) $cart->getPaymentModuleId());
    }

    /**
     * Chosen or not, the checkout never places the order itself with such a module: its
     * pay() would mark the order paid with no sheet opened and no money taken.
     */
    public function testTheOrdinaryPlacementIsRefusedForAModuleThatOnlyTakesExpressPayment(): void
    {
        ConfigQuery::write('checkout_display_mode', CheckoutDisplayMode::OnePage->value);
        $cart = $this->aFilledCart();
        $cart->setPaymentModuleId($this->registerTheExpressModule())->save($this->getPropelConnection());

        $this->expectException(InvalidPaymentException::class);

        $this->getService(ExpressPaymentCheckoutGuard::class)->refuseAnOrdinaryPlacementOf($cart);
    }

    public function testTheOrdinaryPlacementGoesThroughForAModuleThatAlsoTakesCards(): void
    {
        $cart = $this->aFilledCart();
        $cart->setPaymentModuleId($this->registerTheExpressModule())->save($this->getPropelConnection());
        ExpressPaymentTestModule::$alsoOfferedAtCheckout = true;

        $this->getService(ExpressPaymentCheckoutGuard::class)->refuseAnOrdinaryPlacementOf($cart);

        $this->addToAssertionCount(1);
    }

    private function registerTheExpressModule(): int
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

        return (int) $module->getId();
    }

    private function aFilledCart(): Cart
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart();
        $factory->cartItem($cart, $factory->product($factory->category(), $factory->taxRule(), $factory->currency()));

        return CartQuery::create()->findPk($cart->getId()) ?? $cart;
    }

    private function selection(): CartSelectionService
    {
        return $this->getService(CartSelectionService::class);
    }
}

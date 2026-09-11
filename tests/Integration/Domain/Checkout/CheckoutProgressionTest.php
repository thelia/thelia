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
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\CheckoutStepView;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Checkout\Service\CheckoutStepTitleResolver;
use Thelia\Domain\Checkout\Service\CheckoutTunnelShape;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Checkout\Service\Step\CartStepProvider;
use Thelia\Domain\Checkout\Service\Step\ConfirmationStepProvider;
use Thelia\Domain\Checkout\Service\Step\DeliveryStepProvider;
use Thelia\Domain\Checkout\Service\Step\PaymentStepProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CheckoutStep;
use Thelia\Model\CheckoutStepI18nQuery;
use Thelia\Model\CheckoutStepQuery;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The steps of the checkout and where a given cart stands in them.
 *
 * Two things are being pinned here. The list of steps is what the shop configured —
 * a step turned off no longer has a screen — and the progression names the first step
 * a cart still has something to do at, in the order the merchant put them in.
 *
 * And the one thing turning a step off may never do: let an order through. Removing a
 * step removes its screen, not the check made when the order is placed.
 */
final class CheckoutProgressionTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->progression()->forget();
    }

    protected function tearDown(): void
    {
        // The kernel dispatcher outlives the test, so listeners left behind would
        // answer the next test of the process.
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->progression()->forget();
        // The consent list is memoized on a service the kernel shares between tests, so
        // a test that turned a consent off would otherwise keep it off for the next one,
        // whatever the transaction rolled back.
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    public function testAFreshShopWalksTheBuyerThroughTheFourStepsInOrder(): void
    {
        $cart = $this->cartWithAnItem();

        self::assertSame(
            ['cart', 'delivery', 'payment', 'confirmation'],
            $this->codesOf($this->progression()->activeSteps($cart)),
        );
    }

    /**
     * The wording and the place in the trail come from the table; the rendering hint is
     * null for every core step, because naming a component is the theme's business and
     * the core has no name to give. A module's step is where a hint means something.
     */
    public function testTheStepsCarryTheWordingAndTheRankTheThemeNeeds(): void
    {
        $cart = $this->cartWithAnItem();

        $steps = $this->progression()->activeSteps($cart, 'en_US');
        $delivery = $this->stepNamed($steps, 'delivery');

        self::assertSame('Delivery', $delivery->title);
        self::assertNull($delivery->componentName);
        self::assertSame(2, $delivery->position);
        self::assertFalse($delivery->mandatory);
        self::assertTrue($this->stepNamed($steps, 'payment')->mandatory);
    }

    public function testACartWithNothingToShipSkipsTheDeliveryStep(): void
    {
        $cart = $this->virtualCart();

        self::assertSame(
            ['cart', 'payment', 'confirmation'],
            $this->codesOf($this->progression()->activeSteps($cart)),
        );
    }

    public function testAnEmptyCartIsStuckOnTheCartStep(): void
    {
        $cart = $this->factory->cart();

        self::assertSame('cart', $this->progression()->firstIncompleteStep($cart)?->code);
    }

    public function testACartWithNoDeliveryChoiceIsStuckOnTheDeliveryStep(): void
    {
        $cart = $this->cartWithAnItem();

        self::assertSame('delivery', $this->progression()->firstIncompleteStep($cart)?->code);
    }

    public function testACartWithNoPaymentChoiceIsStuckOnThePaymentStep(): void
    {
        $cart = $this->cartReadyToPay();
        $cart->setPaymentModuleId(null)->save($this->getPropelConnection());
        $this->progression()->forget();

        self::assertSame('payment', $this->progression()->firstIncompleteStep($cart)?->code);
    }

    /**
     * The memo is kept per cart and per state of that cart, so a caller that changes the
     * cart and asks again is answered about the cart it now holds. Freshness used to be
     * a convention between callers — every one of them had to remember forget() — and a
     * caller that forgot sent a buyer back to a screen they had just finished with.
     */
    public function testACartChangedBetweenTwoQuestionsIsAnsweredAgainstItsNewState(): void
    {
        $this->turnMandatoryConsentsOff();
        $cart = $this->cartReadyToPay();
        $cart->setPaymentModuleId(null)->save($this->getPropelConnection());
        $this->progression()->forget();

        self::assertSame('payment', $this->progression()->firstIncompleteStep($cart)?->code);

        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');
        $cart->setPaymentModuleId($paymentModule->getId())->save($this->getPropelConnection());

        // Deliberately no forget() here: that is the whole point.
        self::assertNull($this->progression()->firstIncompleteStep($cart));
    }

    public function testAStepIsOutOfReachWhileAnEarlierOneIsUnfinished(): void
    {
        $cart = $this->cartWithAnItem();

        self::assertTrue($this->progression()->isReachable($cart, 'cart'));
        self::assertTrue($this->progression()->isReachable($cart, 'delivery'));
        self::assertFalse($this->progression()->isReachable($cart, 'payment'));
        self::assertFalse($this->progression()->isReachable($cart, 'confirmation'));
    }

    /**
     * A shop that hands the delivery over to something else — a marketplace, a
     * pickup-only catalogue — turns the step off, and the buyer no longer sees that
     * screen. What it does not turn off is the check: an order still has to name a
     * delivery the shop would have offered, or the checkout would let through orders
     * nobody can ship.
     */
    public function testTurningTheDeliveryStepOffRemovesItsScreenAndNotItsCheck(): void
    {
        $this->deactivateStep('delivery');
        $cart = $this->cartWithAnItem();

        self::assertSame(
            ['cart', 'payment', 'confirmation'],
            $this->codesOf($this->progression()->activeSteps($cart)),
        );
        self::assertSame(
            'payment',
            $this->progression()->firstIncompleteStep($cart)?->code,
            'With no delivery step left, the first thing the buyer still has to do is the payment step.',
        );

        $this->expectException(CheckoutException::class);
        $this->getService(CheckoutValidationService::class)->validateForOrder($cart);
    }

    /**
     * A row written straight into the table — by a migration, by a module, by hand —
     * can leave the checkout without a step a shop cannot sell without. The answer is
     * the list the code ships with, never an empty tunnel.
     */
    public function testAConfigurationMissingThePaymentStepFallsBackToTheDefaults(): void
    {
        $this->deactivateStep('payment');
        $cart = $this->cartWithAnItem();

        self::assertSame(
            ['cart', 'delivery', 'payment', 'confirmation'],
            $this->codesOf($this->progression()->activeSteps($cart)),
        );
    }

    public function testATableWithNoActiveStepAtAllFallsBackToTheDefaults(): void
    {
        CheckoutStepQuery::create()->update(['Active' => 0], $this->getPropelConnection());
        $this->progression()->forget();
        $cart = $this->cartWithAnItem();

        self::assertSame(
            ['cart', 'delivery', 'payment', 'confirmation'],
            $this->codesOf($this->progression()->activeSteps($cart)),
        );
    }

    /**
     * A row naming a step no installed code declares has no screen to show and no
     * check to run. It is left out rather than rendered as a dead end.
     */
    public function testARowNoProviderAnswersForIsLeftOut(): void
    {
        $step = (new CheckoutStep())
            ->setCode('gift-wrapping')
            ->setPosition(10)
            ->setActive(1)
            ->setMandatory(0);
        $step->save($this->getPropelConnection());
        $this->progression()->forget();

        $cart = $this->cartWithAnItem();

        self::assertSame(
            ['cart', 'delivery', 'payment', 'confirmation'],
            $this->codesOf($this->progression()->activeSteps($cart)),
        );
    }

    /**
     * The end of the tunnel: an address, a carrier, a payment and nothing mandatory left
     * to tick. Nothing is left to do, and the confirmation is the screen to serve.
     */
    public function testACartWithEverythingSettledHasNoStepLeftAndReachesTheConfirmation(): void
    {
        $this->turnMandatoryConsentsOff();
        $cart = $this->cartReadyToPay();

        self::assertNull($this->progression()->firstIncompleteStep($cart));
        self::assertTrue($this->progression()->isReachable($cart, 'confirmation'));
    }

    /**
     * A table whose positions were permuted by hand holds all four steps, so nothing is
     * missing — and yet the confirmation comes first and the cart last, which is a
     * tunnel nobody can buy through. It is the shape that is checked, not the roll call.
     */
    public function testPositionsPermutedInTheTableFallBackToTheStepsTheCodeDeclares(): void
    {
        $this->positionStep('confirmation', 1);
        $this->positionStep('cart', 4);

        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $warnings = [];

            public function log($level, $message, array $context = []): void
            {
                if (LogLevel::WARNING === $level) {
                    $this->warnings[] = (string) $message;
                }
            }
        };

        $progression = new CheckoutProgressionService(
            $this->stepProviders(),
            new CheckoutTunnelShape(),
            $this->getService(CheckoutStepTitleResolver::class),
            $logger,
        );
        $cart = $this->cartWithAnItem();

        self::assertSame(
            ['cart', 'delivery', 'payment', 'confirmation'],
            $this->codesOf($progression->activeSteps($cart)),
        );
        self::assertCount(1, $logger->warnings);
        self::assertStringContainsString('"cart"', $logger->warnings[0]);
    }

    public function testAWordingMissingInTheAskedLanguageFallsBackToTheShopLanguage(): void
    {
        $shopLocale = (string) Lang::getDefaultLanguage()->getLocale();
        $expected = $this->wordingOf('delivery', $shopLocale);
        self::assertNotSame('', $expected, 'The fixture has to hold a delivery wording in the shop language.');

        $this->keepOnlyWordings('delivery', [$shopLocale]);

        $steps = $this->progression()->activeSteps($this->cartWithAnItem(), 'de_DE');

        self::assertSame($expected, $this->stepNamed($steps, 'delivery')->title);
    }

    public function testAWordingWrittenOnlyInAnotherLanguageIsShownRatherThanNone(): void
    {
        $shopLocale = (string) Lang::getDefaultLanguage()->getLocale();
        $written = 'it_IT' === $shopLocale ? 'fr_FR' : 'it_IT';
        $expected = $this->wordingOf('delivery', $written);
        self::assertNotSame('', $expected, 'The fixture has to hold a delivery wording in that language.');

        $this->keepOnlyWordings('delivery', [$written]);

        $steps = $this->progression()->activeSteps($this->cartWithAnItem(), 'de_DE');

        self::assertSame($expected, $this->stepNamed($steps, 'delivery')->title);
    }

    /**
     * A step nobody ever worded is named by its code. What the breadcrumb never shows is
     * an empty link, nor the "DEFAULT TITLE" placeholder I18n forges.
     */
    public function testAStepWordedInNoLanguageAtAllIsNamedByItsCode(): void
    {
        $this->keepOnlyWordings('delivery', []);

        $steps = $this->progression()->activeSteps($this->cartWithAnItem(), 'de_DE');

        self::assertSame('delivery', $this->stepNamed($steps, 'delivery')->title);
    }

    /**
     * @param list<CheckoutStepView> $steps
     *
     * @return list<string>
     */
    private function codesOf(array $steps): array
    {
        return array_map(static fn (CheckoutStepView $step): string => $step->code, $steps);
    }

    /**
     * @param list<CheckoutStepView> $steps
     */
    private function stepNamed(array $steps, string $code): CheckoutStepView
    {
        foreach ($steps as $step) {
            if ($step->code === $code) {
                return $step;
            }
        }

        throw new \RuntimeException(\sprintf('No step "%s" in the list.', $code));
    }

    /**
     * @return list<\Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface>
     */
    private function stepProviders(): array
    {
        return [
            $this->getService(CartStepProvider::class),
            $this->getService(DeliveryStepProvider::class),
            $this->getService(PaymentStepProvider::class),
            $this->getService(ConfirmationStepProvider::class),
        ];
    }

    private function positionStep(string $code, int $position): void
    {
        $this->stepNamedInTable($code)->setPosition($position)->save($this->getPropelConnection());
        $this->progression()->forget();
    }

    private function wordingOf(string $code, string $locale): string
    {
        $wording = CheckoutStepI18nQuery::create()
            ->filterById($this->stepNamedInTable($code)->getId())
            ->filterByLocale($locale)
            ->findOne($this->getPropelConnection());

        return (string) ($wording?->getTitle() ?? '');
    }

    /**
     * @param list<string> $locales
     */
    private function keepOnlyWordings(string $code, array $locales): void
    {
        $query = CheckoutStepI18nQuery::create()->filterById($this->stepNamedInTable($code)->getId());

        if ([] !== $locales) {
            $query->filterByLocale($locales, Criteria::NOT_IN);
        }

        $query->delete($this->getPropelConnection());
        $this->progression()->forget();
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            /* @var Consent $consent */
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
        $this->progression()->forget();
    }

    private function stepNamedInTable(string $code): CheckoutStep
    {
        return CheckoutStepQuery::create()->findOneByCode($code, $this->getPropelConnection())
            ?? throw new \RuntimeException(\sprintf('No step "%s" seeded — run bin/test-prepare.', $code));
    }

    private function deactivateStep(string $code): void
    {
        $this->stepNamedInTable($code)->setActive(0)->save($this->getPropelConnection());
        $this->progression()->forget();
    }

    private function cartWithAnItem(): Cart
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        ));

        return $cart;
    }

    private function virtualCart(): Cart
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $product->setVirtual(1)->save($this->getPropelConnection());

        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $product);

        return $cart;
    }

    /**
     * A cart every step but the payment one is done with: the delivery module serves
     * the country and quotes the cart, so what is left to settle is the payment.
     */
    private function cartReadyToPay(): Cart
    {
        $country = $this->factory->country();

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');

        $this->serveCountryWith($deliveryModule, $country);
        $this->answerDeliveryQuoteWith(valid: true);
        $this->answerPaymentValidityWith(valid: true);

        $cart = $this->cartWithAnItem();
        $cart
            ->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Progression test area');
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save($this->getPropelConnection());
    }

    private function answerDeliveryQuoteWith(bool $valid): void
    {
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event) use ($valid): void {
            $event->setValidModule($valid);
            if ($valid) {
                $event->setPostage(new OrderPostage(5.0, 1.0, 'VAT'));
            }
            $event->stopPropagation();
        });
    }

    private function answerPaymentValidityWith(bool $valid): void
    {
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event) use ($valid): void {
            $event->setValidModule($valid);
            $event->stopPropagation();
        });
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher()->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }

    private function progression(): CheckoutProgressionService
    {
        return $this->getService(CheckoutProgressionService::class);
    }
}

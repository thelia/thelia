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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\CheckoutViolation;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Checkout\Service\Step\CartStepProvider;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Domain\Checkout\Service\Step\ConfirmationStepProvider;
use Thelia\Domain\Checkout\Service\Step\DeliveryStepProvider;
use Thelia\Domain\Checkout\Service\Step\PaymentStepProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CheckoutStep;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Everything a cart still has to settle, told in one go.
 *
 * The tunnel of a theme asks one question per screen and stops at the first refusal,
 * which is what validateForOrder() answers. A caller with no screens — the front API —
 * has a form to fill in in one shot and needs the whole list, so the same steps are
 * asked the same questions and their refusals are gathered instead of raised.
 *
 * Each violation carries a machine code that never changes wording, next to the sentence
 * the buyer reads: the code is what a client branches on, the message is what it shows.
 */
final class CheckoutViolationCollectionTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    /**
     * The point of the whole method: a cart with nothing settled comes back with every
     * refusal at once, not with the first one.
     */
    public function testACartWithNothingSettledReportsEveryRefusalAtOnce(): void
    {
        $this->turnMandatoryConsentsOff();

        $violations = $this->validationWith($this->coreProviders())->collectViolations($this->factory->cart());

        $codes = $this->codesOf($violations);

        self::assertContains(CheckoutViolationCode::CartEmpty->value, $codes);
        self::assertContains(CheckoutViolationCode::AddressMissing->value, $codes);
        self::assertContains(CheckoutViolationCode::PaymentInvalid->value, $codes);
        self::assertGreaterThanOrEqual(
            2,
            \count($violations),
            'A cart missing several things must be told about all of them, not about the first.',
        );
    }

    /**
     * The payment screen asks three separate questions, and a buyer who got two of them
     * wrong is told about both rather than sent back twice.
     */
    public function testThePaymentStepReportsItsOwnGuardsSeparately(): void
    {
        $this->turnMandatoryConsentsOff();

        $cart = $this->cartReadyToPay();
        $this->makeTheInvoiceAddressIncomplete($cart);
        $cart->setPaymentModuleId(null)->save($this->getPropelConnection());

        $codes = $this->codesOf($this->validationWith($this->coreProviders())->collectViolations($cart));

        self::assertContains(CheckoutViolationCode::InvoiceAddressIncomplete->value, $codes);
        self::assertContains(CheckoutViolationCode::PaymentInvalid->value, $codes);
    }

    /**
     * The delivery family has to be reachable too: an address is there, a carrier is not.
     */
    public function testACartWithAnAddressAndNoCarrierReportsTheDeliveryRefusal(): void
    {
        $this->turnMandatoryConsentsOff();

        $cart = $this->cartReadyToPay();
        $cart->setDeliveryModuleId(null)->save($this->getPropelConnection());

        $codes = $this->codesOf($this->validationWith($this->coreProviders())->collectViolations($cart));

        self::assertContains(CheckoutViolationCode::DeliveryInvalid->value, $codes);
    }

    /**
     * A consent the shop requires is a refusal like any other, and it names the very box
     * the buyer has to tick.
     */
    public function testAMandatoryConsentLeftUntickedIsReportedWithItsCode(): void
    {
        // The shop is seeded with mandatory consents of its own, and the guard names the
        // first one it meets: leave this test with exactly one box to tick.
        $this->turnMandatoryConsentsOff();

        $consent = (new Consent())
            ->setCode('collection-test-consent')
            ->setActive(1)
            ->setMandatory(1)
            ->setPosition(1);
        $consent->setLocale('en_US')->setTitle('The terms of sale');
        $consent->save($this->getPropelConnection());
        $this->getService(ConsentProvider::class)->forgetCache();

        $violations = $this->validationWith($this->coreProviders())->collectViolations($this->cartReadyToPay());

        $consentViolations = array_values(array_filter(
            $violations,
            static fn (CheckoutViolation $violation): bool => CheckoutViolationCode::ConsentMissing->value === $violation->code,
        ));

        self::assertNotSame([], $consentViolations);
        self::assertSame('collection-test-consent', $consentViolations[0]->details['consentCode'] ?? null);
        self::assertSame(CheckoutStep::CODE_PAYMENT, $consentViolations[0]->stepCode);
    }

    /**
     * And a cart with everything settled reports nothing, which is what lets a caller
     * treat an empty list as "this may be ordered".
     */
    public function testACartWithEverythingSettledReportsNothing(): void
    {
        $this->turnMandatoryConsentsOff();

        self::assertSame([], $this->validationWith($this->coreProviders())->collectViolations($this->cartReadyToPay()));
    }

    /**
     * @param list<CheckoutViolation> $violations
     *
     * @return list<string>
     */
    private function codesOf(array $violations): array
    {
        return array_map(static fn (CheckoutViolation $violation): string => $violation->code, $violations);
    }

    /**
     * @param list<CheckoutStepProviderInterface> $providers
     */
    private function validationWith(array $providers): CheckoutValidationService
    {
        return new CheckoutValidationService($providers);
    }

    /**
     * @return list<CheckoutStepProviderInterface>
     */
    private function coreProviders(): array
    {
        return [
            $this->getService(ConfirmationStepProvider::class),
            $this->getService(PaymentStepProvider::class),
            $this->getService(CartStepProvider::class),
            $this->getService(DeliveryStepProvider::class),
        ];
    }

    private function makeTheInvoiceAddressIncomplete(Cart $cart): void
    {
        CartAddressQuery::create()
            ->findPk($cart->getAddressInvoiceId(), $this->getPropelConnection())
            ->setCompany('Collection Test Company')
            // A SIRET is fourteen digits, and the address is French: eleven is refused.
            ->setSiret('12345678901')
            ->save($this->getPropelConnection());
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            /* @var Consent $consent */
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function cartReadyToPay(): Cart
    {
        // A French address on purpose: the legal identifiers of a company are checked
        // against the country of the address, and France is where the rules bite.
        $country = $this->factory->country(['isocode' => '250', 'isoalpha2' => 'FR', 'isoalpha3' => 'FRA', 'shopCountry' => false]);

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');

        $this->serveCountryWith($deliveryModule, $country);
        $this->answerDeliveryQuoteWith(valid: true);
        $this->answerPaymentValidityWith(valid: true);

        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        ));

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
        $area = (new Area())->setName('Violation collection test area');
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
}

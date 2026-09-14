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

use Thelia\Domain\Checkout\Exception\CheckoutStepConfigurationException;
use Thelia\Domain\Checkout\Service\CheckoutStepConfigurationService;
use Thelia\Domain\Checkout\Service\CheckoutTunnelShape;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Model\Cart;
use Thelia\Model\CheckoutStep;
use Thelia\Model\CheckoutStepQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * What the merchant is allowed to change about the steps of the checkout.
 *
 * A tunnel has a shape a shop cannot sell without: it opens on the cart, it takes the
 * money at the next-to-last step and it ends on the confirmation. Everything in
 * between is the merchant's to reorder and to turn off; those three are not.
 */
final class CheckoutStepConfigurationTest extends IntegrationTestCase
{
    public function testAMandatoryStepCannotBeTurnedOff(): void
    {
        $this->expectException(CheckoutStepConfigurationException::class);

        $this->configuration()->setActive('payment', false);
    }

    public function testAnOptionalStepCanBeTurnedOffAndBackOn(): void
    {
        $this->configuration()->setActive('delivery', false);
        self::assertFalse($this->stepNamed('delivery')->isActive());

        $this->configuration()->setActive('delivery', true);
        self::assertTrue($this->stepNamed('delivery')->isActive());
    }

    public function testTheCartStepCannotBeMovedOffTheFirstPlace(): void
    {
        $this->expectException(CheckoutStepConfigurationException::class);

        $this->configuration()->updatePosition('cart', 2);
    }

    public function testAStepCannotBeMovedAheadOfTheCartStep(): void
    {
        $this->expectException(CheckoutStepConfigurationException::class);

        $this->configuration()->updatePosition('delivery', 1);
    }

    public function testAStepCannotBeMovedPastThePaymentStep(): void
    {
        $this->expectException(CheckoutStepConfigurationException::class);

        $this->configuration()->updatePosition('delivery', 3);
    }

    public function testAStepBetweenTheCartAndThePaymentIsMovedAndTheListRenumbered(): void
    {
        $this->createStep('gift-message', 5);

        $this->configuration()->updatePosition('gift-message', 2);

        self::assertSame(
            ['cart' => 1, 'gift-message' => 2, 'delivery' => 3, 'payment' => 4, 'confirmation' => 5],
            $this->orderedPositions(),
        );
    }

    public function testAStepAProviderDeclaresButTheTableIsMissingIsRecreated(): void
    {
        $this->stepNamed('delivery')->delete($this->getPropelConnection());
        self::assertNull(CheckoutStepQuery::create()->findOneByCode('delivery', $this->getPropelConnection()));

        $created = $this->configuration()->synchronize();

        self::assertContains('delivery', $created);

        $delivery = $this->stepNamed('delivery');
        self::assertTrue($delivery->isActive());
        self::assertSame(2, $delivery->getPosition());
    }

    public function testSynchronisationCreatesNothingWhenEveryStepIsAlreadyThere(): void
    {
        self::assertSame([], $this->configuration()->synchronize());
    }

    /**
     * A module is free to say where its step belongs and just as free to get it wrong:
     * `defaultPosition(): 99` would land its screen behind the confirmation, where the
     * buyer has already paid and nothing will ever show it. Synchronisation brings it
     * back into the room the tunnel leaves, between the cart and the payment.
     */
    public function testAProviderAskingForAPlacePastThePaymentIsBroughtBackBeforeIt(): void
    {
        $service = new CheckoutStepConfigurationService(
            [$this->providerFor('gift-wrapping', 99)],
            new CheckoutTunnelShape(),
        );

        self::assertSame(['gift-wrapping'], $service->synchronize());

        $created = CheckoutStepQuery::create()->findOneByCode('gift-wrapping', $this->getPropelConnection());

        self::assertNotNull($created);
        self::assertSame(2, $created->getPosition(), 'The only free place between the cart and the payment is 2.');
        self::assertSame(
            ['cart', 'delivery', 'gift-wrapping', 'payment', 'confirmation'],
            array_keys($this->orderedPositions()),
        );
    }

    private function providerFor(string $code, int $defaultPosition): CheckoutStepProviderInterface
    {
        return new class($code, $defaultPosition) implements CheckoutStepProviderInterface {
            public function __construct(
                private readonly string $code,
                private readonly int $defaultPosition,
            ) {
            }

            public function code(): string
            {
                return $this->code;
            }

            public function defaultPosition(): int
            {
                return $this->defaultPosition;
            }

            public function isMandatory(): bool
            {
                return false;
            }

            public function isSkippedFor(Cart $cart): bool
            {
                return false;
            }

            public function check(Cart $cart): void
            {
            }

            public function componentName(): ?string
            {
                return null;
            }
        };
    }

    /**
     * @return array<string, int>
     */
    private function orderedPositions(): array
    {
        $positions = [];

        // Two steps may share a position, and the front office settles that tie on the
        // code: the listing here has to read the same way round.
        foreach (CheckoutStepQuery::create()->orderByPosition()->orderByCode()->find($this->getPropelConnection()) as $step) {
            $positions[(string) $step->getCode()] = $step->getPosition();
        }

        return $positions;
    }

    private function createStep(string $code, int $position): CheckoutStep
    {
        $step = (new CheckoutStep())
            ->setCode($code)
            ->setActive(1)
            ->setMandatory(0);
        $step->save($this->getPropelConnection());
        $step->setPosition($position)->save($this->getPropelConnection());

        return $step;
    }

    private function stepNamed(string $code): CheckoutStep
    {
        return CheckoutStepQuery::create()->findOneByCode($code, $this->getPropelConnection())
            ?? throw new \RuntimeException(\sprintf('No step "%s" seeded — run bin/test-prepare.', $code));
    }

    private function configuration(): CheckoutStepConfigurationService
    {
        return $this->getService(CheckoutStepConfigurationService::class);
    }
}

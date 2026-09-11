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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Thelia\Domain\Checkout\DTO\CheckoutStepView;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Checkout\Service\CheckoutStepTitleResolver;
use Thelia\Domain\Checkout\Service\CheckoutTunnelShape;
use Thelia\Domain\Checkout\Service\Step\CartStepProvider;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Domain\Checkout\Service\Step\ConfirmationStepProvider;
use Thelia\Domain\Checkout\Service\Step\DeliveryStepProvider;
use Thelia\Domain\Checkout\Service\Step\PaymentStepProvider;
use Thelia\Model\Cart;
use Thelia\Test\IntegrationTestCase;

/**
 * The shop between the two halves of a deployment.
 *
 * The code lands before the SQL runs often enough to plan for: a release pushed in the
 * wrong order, a migration that failed, a database restored from before the feature.
 * The table is read on every page of the tunnel, so the shop would be selling nothing at
 * all until someone noticed. It runs on the steps the code declares instead, and says so
 * in the log — the same answer already given to a table holding an order nobody can buy
 * through.
 *
 * The table is renamed rather than dropped, and put back whatever happens.
 */
final class CheckoutStepTableMissingTest extends IntegrationTestCase
{
    // Renaming a table is DDL: MariaDB commits it, so there is no transaction to roll
    // back and the rename is undone by hand.
    protected bool $useTransaction = false;

    private const TABLE = 'checkout_step';
    private const PARKED = 'checkout_step_parked_by_test';

    private bool $renamed = false;

    protected function tearDown(): void
    {
        $this->putTheTableBack();

        parent::tearDown();
    }

    public function testATunnelWhoseTableIsNotThereYetRunsOnTheStepsTheCodeDeclares(): void
    {
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

        $this->takeTheTableAway();

        $steps = $progression->activeSteps(new Cart());

        self::assertSame(
            ['cart', 'delivery', 'payment', 'confirmation'],
            array_map(static fn (CheckoutStepView $step): string => $step->code, $steps),
        );
        self::assertSame(
            'cart',
            $steps[0]->title,
            'With no row to read a wording from, a step is named by its code.',
        );
        self::assertCount(1, $logger->warnings);
        self::assertStringContainsString('checkout_step', $logger->warnings[0]);
    }

    /**
     * @return list<CheckoutStepProviderInterface>
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

    private function takeTheTableAway(): void
    {
        $this->connection()->exec(\sprintf('RENAME TABLE `%s` TO `%s`', self::TABLE, self::PARKED));
        $this->renamed = true;
    }

    private function putTheTableBack(): void
    {
        if (!$this->renamed) {
            return;
        }

        $this->connection()->exec(\sprintf('RENAME TABLE `%s` TO `%s`', self::PARKED, self::TABLE));
        $this->renamed = false;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getConnection('TheliaMain');
    }
}

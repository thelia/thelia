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

namespace Thelia\Tests\Integration\Domain\Checkout\Fixture;

use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Model\Cart;

/**
 * The step of a module, as the core sees one: it refuses every cart, and it is not
 * registered in the container, so nothing but the list it is handed to can reach it.
 */
final readonly class RefusingStepProvider implements CheckoutStepProviderInterface
{
    public const CODE = 'fixture_module_step';

    public function __construct(private bool $skipped = false)
    {
    }

    public function code(): string
    {
        return self::CODE;
    }

    public function defaultPosition(): int
    {
        return 2;
    }

    public function isMandatory(): bool
    {
        return false;
    }

    public function isSkippedFor(Cart $cart): bool
    {
        return $this->skipped;
    }

    public function check(Cart $cart): void
    {
        throw new RefusedByFixtureException('The fixture step refuses this cart.');
    }

    public function componentName(): ?string
    {
        return null;
    }
}

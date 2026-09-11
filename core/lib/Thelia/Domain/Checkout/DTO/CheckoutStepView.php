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

namespace Thelia\Domain\Checkout\DTO;

/**
 * One step of the checkout as a theme reads it: what it is called, what to write in the
 * breadcrumb and what to render it with.
 *
 * A value object rather than the `checkout_step` row, because what the theme is handed
 * has to be the same whether the row exists or the step only comes from the code that
 * declares it — and because a template must not be able to save a step.
 */
final readonly class CheckoutStepView
{
    public function __construct(
        public string $code,
        public string $title,
        public int $position,
        public bool $mandatory,
        public ?string $componentName,
    ) {
    }
}

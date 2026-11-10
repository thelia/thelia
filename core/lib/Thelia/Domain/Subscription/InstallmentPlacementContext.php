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

namespace Thelia\Domain\Subscription;

/**
 * Says, while an order is being placed, that it is the order of an installment and not
 * one a customer checks out: the listeners of the placement have no other way to tell,
 * since the installment row only names the order once it is placed.
 */
final class InstallmentPlacementContext
{
    private int $depth = 0;

    /**
     * @template T
     *
     * @param \Closure(): T $placement
     *
     * @return T
     */
    public function during(\Closure $placement): mixed
    {
        ++$this->depth;

        try {
            return $placement();
        } finally {
            --$this->depth;
        }
    }

    public function isPlacingAnInstallment(): bool
    {
        return $this->depth > 0;
    }
}

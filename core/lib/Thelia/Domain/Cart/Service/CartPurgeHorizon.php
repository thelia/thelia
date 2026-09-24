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

namespace Thelia\Domain\Cart\Service;

use Thelia\Model\ConfigQuery;

final readonly class CartPurgeHorizon
{
    public const string CONFIG_KEY_CART_NO_ORDER_DAYS = 'purification_cart_no_order_days';
    public const string CONFIG_KEY_CART_ANONYMOUS_DAYS = 'purification_cart_anonymous_days';
    public const int DEFAULT_CART_NO_ORDER_DAYS = 60;
    public const int DEFAULT_CART_ANONYMOUS_DAYS = 30;

    public function __construct(
        private int $cartNoOrderDays,
        private int $cartAnonymousDays,
    ) {
        if ($this->cartNoOrderDays < 0) {
            throw new \InvalidArgumentException('cartNoOrderDays must be greater than or equal to 0.');
        }

        if ($this->cartAnonymousDays < 0) {
            throw new \InvalidArgumentException('cartAnonymousDays must be greater than or equal to 0.');
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            (int) ConfigQuery::read(self::CONFIG_KEY_CART_NO_ORDER_DAYS, self::DEFAULT_CART_NO_ORDER_DAYS),
            (int) ConfigQuery::read(self::CONFIG_KEY_CART_ANONYMOUS_DAYS, self::DEFAULT_CART_ANONYMOUS_DAYS),
        );
    }

    public function retentionDays(): int
    {
        return min($this->cartNoOrderDays, $this->cartAnonymousDays);
    }

    public function earliestSurvivingCartDate(?\DateTimeImmutable $now = null): \DateTimeImmutable
    {
        $now ??= new \DateTimeImmutable();

        return $now
            ->modify(\sprintf('-%d days', $this->retentionDays()))
            ->setTime(0, 0, 0);
    }

    public function mayHavePurged(\DateTimeInterface $from, ?\DateTimeImmutable $now = null): bool
    {
        return $from < $this->earliestSurvivingCartDate($now);
    }
}

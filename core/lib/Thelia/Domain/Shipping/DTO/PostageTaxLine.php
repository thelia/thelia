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

namespace Thelia\Domain\Shipping\DTO;

/**
 * One line of a postage tax breakdown: the share of the untaxed postage that
 * follows a given tax rule, and the tax due on that share.
 *
 * It is the unsaved form of an `order_postage_tax` row.
 */
final readonly class PostageTaxLine
{
    public function __construct(
        public string $title,
        public ?string $description,
        public float $untaxedAmount,
        public float $amount,
    ) {
    }

    public function withAmounts(float $untaxedAmount, float $amount): self
    {
        return new self($this->title, $this->description, $untaxedAmount, $amount);
    }
}

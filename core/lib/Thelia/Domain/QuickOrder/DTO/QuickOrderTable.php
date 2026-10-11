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

namespace Thelia\Domain\QuickOrder\DTO;

use Thelia\Domain\QuickOrder\Enum\LineStatus;

/**
 * The control table the buyer reviews before anything reaches the cart, one
 * line per reference, in the order they were given.
 */
final readonly class QuickOrderTable
{
    /**
     * @param list<QuickOrderLine> $lines
     */
    public function __construct(
        public array $lines,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $summary = array_fill_keys(array_map(static fn (LineStatus $status): string => $status->value, LineStatus::cases()), 0);

        foreach ($this->lines as $line) {
            ++$summary[$line->status->value];
        }

        return $summary;
    }

    /**
     * @return array{lines: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'lines' => array_map(static fn (QuickOrderLine $line): array => $line->toArray(), $this->lines),
            'summary' => $this->summary(),
        ];
    }
}

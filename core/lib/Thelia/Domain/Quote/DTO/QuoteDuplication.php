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

namespace Thelia\Domain\Quote\DTO;

use Thelia\Model\Quote;

/**
 * The quote a duplication started, and the lines of the original it could not take along: their
 * product can no longer be ordered. The shop is told which, so that it asks for them again.
 */
final readonly class QuoteDuplication
{
    /**
     * @param list<array{reference: string, title: string}> $omittedLines the product reference and the title the line was written with
     */
    public function __construct(
        public Quote $quote,
        public array $omittedLines = [],
    ) {
    }

    public function hasOmittedLines(): bool
    {
        return [] !== $this->omittedLines;
    }
}

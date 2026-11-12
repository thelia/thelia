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

namespace Thelia\Domain\Pricing\PriceList\Import;

/**
 * What an import did, or, for a preview, would do. The count of errors is exact; the detail of
 * the lines is kept for the first MAX_REPORTED_ERRORS, so a file of nothing but errors does not
 * build a report as large as itself.
 */
final readonly class PriceListImportReport
{
    public const int MAX_REPORTED_ERRORS = 1000;

    /**
     * @param list<PriceListImportLineError> $errors
     */
    public function __construct(
        public bool $applied,
        public int $created,
        public int $updated,
        public int $errorCount,
        public array $errors,
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    public function omittedErrorCount(): int
    {
        return $this->errorCount - \count($this->errors);
    }
}

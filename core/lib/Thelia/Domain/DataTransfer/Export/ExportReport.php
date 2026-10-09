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

namespace Thelia\Domain\DataTransfer\Export;

/**
 * What an export has to say beside its file: what it produced, and what it left out and
 * why. The command line prints it, the back office shows it.
 */
final class ExportReport
{
    /** @var list<string> */
    private array $lines = [];

    /** @var list<string> */
    private array $warnings = [];

    public function addLine(string $line): void
    {
        $this->lines[] = $line;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}

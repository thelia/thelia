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

use Thelia\Core\Translation\Translator;

/**
 * What an export has to say beside its file: what it produced, and what it left out and
 * why. The command line prints it, the back office shows it.
 *
 * It keeps a bounded number of warnings: the rest is counted, and
 * {@see self::closeWarnings()} ends the list with that count, so that the report of a
 * period with thousands of faulty orders still fits the row of its job.
 */
final class ExportReport
{
    public const MAX_WARNINGS = 100;

    private int $omittedWarnings = 0;

    /** @var list<string> */
    private array $lines = [];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * A report as it was recorded: its closing warning is already in its list.
     *
     * @param list<string> $lines
     * @param list<string> $warnings
     */
    public static function recorded(array $lines, array $warnings): self
    {
        $report = new self();
        $report->lines = $lines;
        $report->warnings = $warnings;

        return $report;
    }

    public function addLine(string $line): void
    {
        $this->lines[] = $line;
    }

    public function addWarning(string $warning): void
    {
        if (\count($this->warnings) < self::MAX_WARNINGS) {
            $this->warnings[] = $warning;

            return;
        }

        ++$this->omittedWarnings;
    }

    /**
     * Ends the list of warnings with how many were not kept; to be called once the last one was added.
     */
    public function closeWarnings(string $locale): void
    {
        if (0 === $this->omittedWarnings) {
            return;
        }

        $this->warnings[] = Translator::getInstance()->trans('%count more warnings are not listed.', ['%count' => $this->omittedWarnings], null, $locale);
        $this->omittedWarnings = 0;
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

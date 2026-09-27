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

namespace Thelia\Install\Charset;

/**
 * A table that is not entirely in utf8mb4, and what converting it involves.
 */
final readonly class Utf8mb4TableConversion
{
    /**
     * @param list<CharacterColumn> $columns  every text column of the table, converted or not
     * @param list<string>          $blockers what has to be settled by hand before the table can be converted
     */
    public function __construct(
        public string $table,
        public string $engine,
        public string $rowFormat,
        public string $collation,
        public array $columns,
        public int $approximateRows,
        public int $sizeInBytes,
        public array $blockers,
        public ?string $unsupportedCharset,
    ) {
    }

    /**
     * @return list<CharacterColumn> the columns the conversion changes: their set, their collation or both
     */
    public function changedColumns(): array
    {
        return array_values(array_filter($this->columns, static fn (CharacterColumn $column): bool => !$column->isUtf8mb4() || $column->changesCollation()));
    }

    public function isConvertible(): bool
    {
        return null === $this->unsupportedCharset && [] === $this->blockers;
    }

    /**
     * InnoDB caps an index key at 767 bytes in the COMPACT and REDUNDANT row formats, which a
     * VARCHAR(255) in utf8mb4 (1020 bytes) exceeds. The fresh install creates its tables in
     * DYNAMIC, where the cap is 3072 bytes; a table created by an older MySQL may still be COMPACT.
     */
    public function needsDynamicRowFormat(): bool
    {
        return 'innodb' === strtolower($this->engine)
            && \in_array(strtolower($this->rowFormat), ['compact', 'redundant'], true);
    }
}

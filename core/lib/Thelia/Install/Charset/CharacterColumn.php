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
 * A column holding text, as information_schema.COLUMNS describes it.
 */
final readonly class CharacterColumn
{
    public function __construct(
        public string $name,
        public string $dataType,
        public string $columnType,
        public string $charset,
        public string $collation,
        public bool $nullable,
        public ?string $default,
        public string $extra,
        public string $comment,
        public int $maximumLength,
    ) {
    }

    public function isUtf8mb4(): bool
    {
        return Utf8mb4Converter::CHARSET === $this->charset;
    }

    public function isGenerated(): bool
    {
        return 1 === preg_match('/\b(VIRTUAL|STORED|PERSISTENT)\b/i', $this->extra);
    }

    public function isInvisible(): bool
    {
        return 1 === preg_match('/\bINVISIBLE\b/i', $this->extra);
    }

    /**
     * The conversion gives every text column of the table utf8mb4_general_ci, the collation
     * of the fresh install; utf8mb3_general_ci is the same collation in the smaller set. Any
     * other one (a binary collation, a unicode one) changes how the column compares.
     */
    public function changesCollation(): bool
    {
        return 'general_ci' !== (explode('_', $this->collation, 2)[1] ?? '');
    }
}

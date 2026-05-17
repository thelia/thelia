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

namespace BackOfficeDefaultTwigBundle\UiComponents\DataTable;

final readonly class Column
{
    /**
     * @param string $cellAlign Bootstrap text-alignment utility, one of: 'start', 'center', 'end'.
     * @param array<string, mixed> $options Kind-specific options. Schemas per ColumnKind:
     *   - TOGGLE: { url_key: string, icon_on: string, icon_off: string }
     *   - BADGE:  { variants: array<scalar, string> mapping row value → Bootstrap variant }
     *   - TEXT / HTML / ACTIONS: empty
     */
    public function __construct(
        public string $key,
        public string $label,
        public ColumnKind $kind = ColumnKind::TEXT,
        public string $cellAlign = 'start',
        public array $options = [],
    ) {
    }
}

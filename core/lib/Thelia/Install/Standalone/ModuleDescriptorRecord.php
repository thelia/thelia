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

namespace Thelia\Install\Standalone;

/**
 * One module.xml found on disk, as the install reads it: the row it would write for the
 * module, and the descriptor the warnings read.
 *
 * @internal
 */
final readonly class ModuleDescriptorRecord
{
    /**
     * @param array{code: string, version: string, type: int, category: string, activate: int, namespace: string, mandatory: int, hidden: int} $row
     */
    public function __construct(
        public string $code,
        public string $path,
        public \SimpleXMLElement $descriptor,
        public array $row,
    ) {
    }
}

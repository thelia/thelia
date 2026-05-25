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

namespace Thelia\Command\Import;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('thelia.demo_importer')]
interface DemoImporterInterface
{
    /**
     * Lower runs first. Importers sharing data must order their priorities so
     * producers run before consumers (e.g. categories before products).
     */
    public function priority(): int;

    public function description(): string;

    public function import(DemoImportContext $context): void;
}

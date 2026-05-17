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

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'BoDataTable', template: '@BackOfficeDefaultTwig/components/DataTable/DataTable.html.twig')]
final class DataTable
{
    public string $id = '';

    public string $caption = '';

    /** @var list<Column> */
    public array $columns = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public string $emptyMessage = '';

    /** @var array<string, string> Extra HTML attributes appended to the table body (e.g. Stimulus wiring). */
    public array $tbodyAttributes = [];
}

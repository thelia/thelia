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

namespace BackOfficeDefaultTwigBundle\Twig;

use BackOfficeDefaultTwigBundle\UiComponents\DataTable\Column;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\ColumnKind;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class DataTableExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('column', $this->textColumn(...)),
            new TwigFunction('column_text', $this->textColumn(...)),
            new TwigFunction('column_html', $this->htmlColumn(...)),
            new TwigFunction('column_toggle', $this->toggleColumn(...)),
            new TwigFunction('column_badge', $this->badgeColumn(...)),
            new TwigFunction('column_actions', $this->actionsColumn(...)),
            new TwigFunction('row_action', $this->rowAction(...)),
        ];
    }

    public function textColumn(string $key, string $label, string $cellAlign = 'start'): Column
    {
        return new Column($key, $label, ColumnKind::TEXT, $cellAlign);
    }

    public function htmlColumn(string $key, string $label, string $cellAlign = 'start'): Column
    {
        return new Column($key, $label, ColumnKind::HTML, $cellAlign);
    }

    public function toggleColumn(
        string $key,
        string $label,
        string $urlKey,
        string $cellAlign = 'center',
        string $iconOn = 'bi-check-circle-fill text-success',
        string $iconOff = 'bi-circle text-secondary',
    ): Column {
        return new Column(
            $key,
            $label,
            ColumnKind::TOGGLE,
            $cellAlign,
            ['url_key' => $urlKey, 'icon_on' => $iconOn, 'icon_off' => $iconOff],
        );
    }

    /**
     * @param array<scalar, string> $variants
     */
    public function badgeColumn(string $key, string $label, array $variants = [], string $cellAlign = 'start'): Column
    {
        return new Column($key, $label, ColumnKind::BADGE, $cellAlign, ['variants' => $variants]);
    }

    public function actionsColumn(string $key = '_actions', string $label = 'Actions', string $cellAlign = 'end'): Column
    {
        return new Column($key, $label, ColumnKind::ACTIONS, $cellAlign);
    }

    /**
     * @param string|array<string, mixed>|null $grantedSubject
     * @param array<string, scalar>            $dataAttributes
     */
    public function rowAction(
        string $kind,
        string $label,
        ?string $href = null,
        ?string $modalTarget = null,
        ?string $grantedAttribute = null,
        string|array|null $grantedSubject = null,
        array $dataAttributes = [],
    ): RowAction {
        return new RowAction(
            kind: $kind,
            label: $label,
            href: $href,
            modalTarget: $modalTarget,
            grantedAttribute: $grantedAttribute,
            grantedSubject: $grantedSubject,
            dataAttributes: $dataAttributes,
        );
    }
}

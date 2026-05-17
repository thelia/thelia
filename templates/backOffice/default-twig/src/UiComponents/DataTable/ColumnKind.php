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

enum ColumnKind: string
{
    case TEXT = 'text';
    case HTML = 'html';
    case TOGGLE = 'toggle';
    case BADGE = 'badge';
    case ACTIONS = 'actions';
}

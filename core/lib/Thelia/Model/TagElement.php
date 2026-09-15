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

namespace Thelia\Model;

use Thelia\Model\Base\TagElement as BaseTagElement;

/**
 * Attaches a tag to a shop object by an element key and an identifier.
 *
 * The element key is a plain string, not an enum: a module tagging its own
 * objects declares its own key, and an enum in core would shut it out. Core
 * declares the keys of the objects it tags itself, below.
 *
 * Because the key is a string, no foreign key guards `element_id`. Every model
 * that becomes taggable therefore owes a `postDelete()` clearing its rows, the
 * way Customer does — nothing in the schema will remind its author. Bulk
 * deletions bypass `postDelete()` altogether, which is what the
 * tag:prune-orphans command is for.
 */
class TagElement extends BaseTagElement
{
    public const ELEMENT_KEY_CUSTOMER = 'customer';
}

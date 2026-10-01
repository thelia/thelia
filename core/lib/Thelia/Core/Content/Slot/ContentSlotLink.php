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

namespace Thelia\Core\Content\Slot;

/**
 * One entry of a content slot, as a theme renders it.
 *
 * A null url is a label without a link, such as the heading of a menu column. The source
 * names where the entry comes from ('content', 'folder', 'cms_page', 'url' or the code a
 * module picks) and sourceId the object it points at, so that a theme can build more than
 * a link from it, a folder mega-menu for one.
 */
final readonly class ContentSlotLink
{
    /**
     * @param list<ContentSlotLink> $children
     */
    public function __construct(
        public string $label,
        public ?string $url,
        public string $source,
        public int|string|null $sourceId = null,
        public array $children = [],
        public bool $opensInNewWindow = false,
    ) {
    }
}

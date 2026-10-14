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

namespace Thelia\Core\SharedCache;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What a module adds to the pages a proxy may share: a resource of its own that every
 * visitor reads the same, or a view it renders. A module implements it on a service the
 * container autoconfigures; the core reads every implementation.
 *
 * The module purges what it declares: it tags the pages with the keys it names here, and
 * hands the same keys to {@see SharedCacheInvalidator::purge()} when the merchant changes
 * what they show.
 */
#[AutoconfigureTag(self::TAG)]
interface SharedCacheContributorInterface
{
    public const TAG = 'thelia.shared_cache.contributor';

    /**
     * The resources read through `resources()` whose answer is the same for every visitor,
     * by the start of their path, each with the keys a page that reads it is tagged with.
     *
     * @return array<string, list<string>> e.g. `['/api/header-highlights' => ['header_highlights']]`
     */
    public function readableResources(): array;

    /**
     * The views rendered by the module whose page may be shared, on the same terms as the
     * views of the core.
     *
     * @return list<string>
     */
    public function shareableViews(): array;
}

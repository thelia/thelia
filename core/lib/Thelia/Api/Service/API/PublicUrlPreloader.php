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

namespace Thelia\Api\Service\API;

use Thelia\Tools\URL;

/**
 * Fills the rewritten url memo for a whole page of resources.
 *
 * `publicUrl` belongs to the read groups of every resource that has one, so
 * something calls the getter once per item, and the getter resolves a rewritten
 * url of its own: one rewriting_url read per item of every page. The lookup is
 * memoized per view, locale and id, so filling the memo for the page first
 * turns that into one read per view.
 */
final readonly class PublicUrlPreloader
{
    /**
     * @param iterable<mixed> $resources resources holding the propel model their
     *                                   url is resolved from
     */
    public function preload(iterable $resources, string $locale): void
    {
        $viewIdsByView = [];

        foreach ($resources as $resource) {
            if (!method_exists($resource, 'getUrl') || !method_exists($resource, 'getRewrittenUrlViewName')) {
                continue;
            }

            $view = $resource->getRewrittenUrlViewName();

            if ('' === $view) {
                continue;
            }

            $viewIdsByView[$view][] = $resource->getId();
        }

        foreach ($viewIdsByView as $view => $viewIds) {
            URL::getInstance()->preloadRewrittenUrls($view, $locale, $viewIds);
        }
    }
}

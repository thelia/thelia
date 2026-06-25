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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\TheliaEvents;

/**
 * Flushes the data access cache whenever catalog or content data changes.
 *
 * A full pool flush on a (rare) admin catalog change is deliberate: it has no
 * staleness blind spot, unlike a per-resource tag mapping that has to be kept
 * in sync with every resource. The TTL is the backstop for anything not
 * covered by an explicit event.
 */
class ResourceCacheInvalidationListener implements EventSubscriberInterface
{
    public function __construct(private readonly ResourceCache $cache)
    {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            TheliaEvents::CACHE_CLEAR,

            TheliaEvents::PRODUCT_CREATE,
            TheliaEvents::PRODUCT_UPDATE,
            TheliaEvents::PRODUCT_DELETE,
            TheliaEvents::PRODUCT_UPDATE_SEO,
            TheliaEvents::PRODUCT_UPDATE_POSITION,
            TheliaEvents::PRODUCT_TOGGLE_VISIBILITY,
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT,
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,

            TheliaEvents::CATEGORY_CREATE,
            TheliaEvents::CATEGORY_UPDATE,
            TheliaEvents::CATEGORY_DELETE,
            TheliaEvents::CATEGORY_UPDATE_SEO,
            TheliaEvents::CATEGORY_UPDATE_POSITION,
            TheliaEvents::CATEGORY_TOGGLE_VISIBILITY,

            TheliaEvents::CONTENT_CREATE,
            TheliaEvents::CONTENT_UPDATE,
            TheliaEvents::CONTENT_DELETE,
            TheliaEvents::CONTENT_UPDATE_SEO,
            TheliaEvents::CONTENT_UPDATE_POSITION,
            TheliaEvents::CONTENT_TOGGLE_VISIBILITY,

            TheliaEvents::FOLDER_CREATE,
            TheliaEvents::FOLDER_UPDATE,
            TheliaEvents::FOLDER_DELETE,
            TheliaEvents::FOLDER_UPDATE_SEO,
            TheliaEvents::FOLDER_UPDATE_POSITION,
            TheliaEvents::FOLDER_TOGGLE_VISIBILITY,

            TheliaEvents::BRAND_CREATE,
            TheliaEvents::BRAND_UPDATE,
            TheliaEvents::BRAND_DELETE,
            TheliaEvents::BRAND_UPDATE_SEO,

            TheliaEvents::FEATURE_CREATE,
            TheliaEvents::FEATURE_UPDATE,
            TheliaEvents::FEATURE_DELETE,

            TheliaEvents::ATTRIBUTE_CREATE,
            TheliaEvents::ATTRIBUTE_UPDATE,
            TheliaEvents::ATTRIBUTE_DELETE,
        ];

        return array_fill_keys($events, 'clear');
    }

    public function clear(): void
    {
        $this->cache->clear();
    }
}

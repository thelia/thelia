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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Category;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupCategory;
use Thelia\Model\CustomerGroupPaymentModule;
use Thelia\Model\CustomerGroupProductPrice;
use Thelia\Model\Event\CustomerGroupCategoryEvent;
use Thelia\Model\Event\CustomerGroupEvent;
use Thelia\Model\Event\CustomerGroupPaymentModuleEvent;
use Thelia\Model\Event\CustomerGroupProductPriceEvent;

/**
 * Flushes the data access cache whenever catalog or content data changes.
 *
 * A full pool flush on a (rare) admin catalog change is deliberate: it has no
 * staleness blind spot, unlike a per-resource tag mapping that has to be kept
 * in sync with every resource. The TTL is the backstop for anything not
 * covered by an explicit event.
 *
 * What a customer group sees and pays is written straight to its tables, with no
 * catalog event: its discount and its display, the categories it is shut out of, the
 * prices it owns and the payment modules it may use. The answers cached for its
 * customers, and for the visitors of the default group, follow those rows.
 *
 * A write only marks the pool: it is flushed once, when the request or the command
 * ends. The model events fire while the transaction of the write still runs, and a
 * pool flushed then is refilled by a concurrent reader from the rows as they were,
 * until the TTL; at the end of the request the write is committed.
 */
class ResourceCacheInvalidationListener implements EventSubscriberInterface
{
    /**
     * The writes to the tables of the customer groups that change what is cached.
     */
    public const CUSTOMER_GROUP_WRITES = [
        CustomerGroupEvent::POST_SAVE,
        CustomerGroupEvent::POST_DELETE,
        CustomerGroupCategoryEvent::POST_SAVE,
        CustomerGroupCategoryEvent::POST_DELETE,
        CustomerGroupProductPriceEvent::POST_SAVE,
        CustomerGroupProductPriceEvent::POST_DELETE,
        CustomerGroupPaymentModuleEvent::POST_SAVE,
        CustomerGroupPaymentModuleEvent::POST_DELETE,
    ];

    private const CUSTOMER_GROUP_MODELS = [
        CustomerGroup::class,
        CustomerGroupCategory::class,
        CustomerGroupProductPrice::class,
        CustomerGroupPaymentModule::class,
    ];

    private bool $flushPending = false;

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

            TheliaEvents::CATALOG_PRICE_RULE_CREATE,
            TheliaEvents::CATALOG_PRICE_RULE_UPDATE,
            TheliaEvents::CATALOG_PRICE_RULE_DELETE,
            TheliaEvents::CATALOG_PRICE_RULE_TOGGLE_ACTIVITY,
            TheliaEvents::CATALOG_PRICE_RULE_RECOMPUTE,

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
            TheliaEvents::CONTENT_UPDATE_PUBLICATION,

            TheliaEvents::FOLDER_CREATE,
            TheliaEvents::FOLDER_UPDATE,
            TheliaEvents::FOLDER_DELETE,
            TheliaEvents::FOLDER_UPDATE_SEO,
            TheliaEvents::FOLDER_UPDATE_POSITION,
            TheliaEvents::FOLDER_TOGGLE_VISIBILITY,
            TheliaEvents::FOLDER_UPDATE_PUBLICATION,

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

        return array_fill_keys([...$events, ...self::CUSTOMER_GROUP_WRITES], 'clear') + [
            ResourcePersistedEvent::class => ['onResourceChange', -255],
            KernelEvents::TERMINATE => ['flush', 0],
            ConsoleEvents::TERMINATE => ['flush', 0],
            // An import run by `messenger:consume` ends no request nor command: the end of the message does.
            WorkerMessageHandledEvent::class => ['flush', 0],
            WorkerMessageFailedEvent::class => ['flush', 0],
        ];
    }

    /**
     * Heard once the write is committed: the model events above fire while it still runs.
     */
    public function onResourceChange(ResourcePersistedEvent $event): void
    {
        // A category written or deleted through the API (the back office goes through the
        // CATEGORY_* events above): the products a group sees follow its categories, and the
        // deletion of one cascades on the lists of the groups without any event of their own.
        if (self::isCustomerGroupModel($event->getModel()) || $event->getModel() instanceof Category) {
            $this->clear();
        }
    }

    public static function isCustomerGroupModel(object $model): bool
    {
        foreach (self::CUSTOMER_GROUP_MODELS as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Marks the pool to be flushed at the end of the request or of the command.
     */
    public function clear(): void
    {
        $this->flushPending = true;
    }

    public function flush(): void
    {
        if (!$this->flushPending) {
            return;
        }

        $this->flushPending = false;
        $this->cache->clear();
    }
}

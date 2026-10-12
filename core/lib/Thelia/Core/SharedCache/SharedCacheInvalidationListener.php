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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Core\Event\TheliaEvents;

final class SharedCacheInvalidationListener implements EventSubscriberInterface
{
    private const PRODUCT = 'product';
    private const CATEGORY = 'category';
    private const CONTENT = 'content';
    private const FOLDER = 'folder';
    private const BRAND = 'brand';

    private const FAMILIES = [
        TheliaEvents::PRODUCT_CREATE => self::PRODUCT,
        TheliaEvents::PRODUCT_UPDATE => self::PRODUCT,
        TheliaEvents::PRODUCT_DELETE => self::PRODUCT,
        TheliaEvents::PRODUCT_TOGGLE_VISIBILITY => self::PRODUCT,
        TheliaEvents::PRODUCT_UPDATE_POSITION => self::PRODUCT,
        TheliaEvents::PRODUCT_UPDATE_SEO => self::PRODUCT,
        TheliaEvents::PRODUCT_ADD_CONTENT => self::PRODUCT,
        TheliaEvents::PRODUCT_REMOVE_CONTENT => self::PRODUCT,
        TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT => self::PRODUCT,
        TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT => self::PRODUCT,
        TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT => self::PRODUCT,
        TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_TOGGLE_VISIBILITY => self::PRODUCT,
        TheliaEvents::PRODUCT_COMBINATION_GENERATION => self::PRODUCT,
        TheliaEvents::PRODUCT_SET_TEMPLATE => self::PRODUCT,
        TheliaEvents::PRODUCT_ADD_ACCESSORY => self::PRODUCT,
        TheliaEvents::PRODUCT_REMOVE_ACCESSORY => self::PRODUCT,
        TheliaEvents::PRODUCT_ADD_ASSOCIATION => self::PRODUCT,
        TheliaEvents::PRODUCT_REMOVE_ASSOCIATION => self::PRODUCT,
        TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE => self::PRODUCT,
        TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE => self::PRODUCT,
        TheliaEvents::PRODUCT_ADD_CATEGORY => self::PRODUCT,
        TheliaEvents::PRODUCT_REMOVE_CATEGORY => self::PRODUCT,
        TheliaEvents::PRODUCT_CLONE => self::PRODUCT,
        TheliaEvents::PSE_CLONE => self::PRODUCT,

        TheliaEvents::CATEGORY_CREATE => self::CATEGORY,
        TheliaEvents::CATEGORY_UPDATE => self::CATEGORY,
        TheliaEvents::CATEGORY_DELETE => self::CATEGORY,
        TheliaEvents::CATEGORY_TOGGLE_VISIBILITY => self::CATEGORY,
        TheliaEvents::CATEGORY_UPDATE_POSITION => self::CATEGORY,
        TheliaEvents::CATEGORY_ADD_CONTENT => self::CATEGORY,
        TheliaEvents::CATEGORY_REMOVE_CONTENT => self::CATEGORY,
        TheliaEvents::CATEGORY_UPDATE_SEO => self::CATEGORY,

        TheliaEvents::CONTENT_CREATE => self::CONTENT,
        TheliaEvents::CONTENT_UPDATE => self::CONTENT,
        TheliaEvents::CONTENT_DELETE => self::CONTENT,
        TheliaEvents::CONTENT_TOGGLE_VISIBILITY => self::CONTENT,
        TheliaEvents::CONTENT_UPDATE_POSITION => self::CONTENT,
        TheliaEvents::CONTENT_UPDATE_SEO => self::CONTENT,
        TheliaEvents::CONTENT_ADD_FOLDER => self::CONTENT,
        TheliaEvents::CONTENT_REMOVE_FOLDER => self::CONTENT,

        TheliaEvents::FOLDER_CREATE => self::FOLDER,
        TheliaEvents::FOLDER_UPDATE => self::FOLDER,
        TheliaEvents::FOLDER_DELETE => self::FOLDER,
        TheliaEvents::FOLDER_TOGGLE_VISIBILITY => self::FOLDER,
        TheliaEvents::FOLDER_UPDATE_POSITION => self::FOLDER,
        TheliaEvents::FOLDER_UPDATE_SEO => self::FOLDER,

        TheliaEvents::BRAND_CREATE => self::BRAND,
        TheliaEvents::BRAND_UPDATE => self::BRAND,
        TheliaEvents::BRAND_DELETE => self::BRAND,
        TheliaEvents::BRAND_UPDATE_POSITION => self::BRAND,
        TheliaEvents::BRAND_TOGGLE_VISIBILITY => self::BRAND,
        TheliaEvents::BRAND_UPDATE_SEO => self::BRAND,
    ];

    private const WHOLE_SHOP = [
        TheliaEvents::CACHE_CLEAR,
        TheliaEvents::SALE_CREATE,
        TheliaEvents::SALE_UPDATE,
        TheliaEvents::SALE_DELETE,
        TheliaEvents::SALE_TOGGLE_ACTIVITY,
        TheliaEvents::SALE_CLEAR_SALE_STATUS,
        TheliaEvents::UPDATE_PRODUCT_SALE_STATUS,
        TheliaEvents::CATALOG_PRICE_RULE_CREATE,
        TheliaEvents::CATALOG_PRICE_RULE_UPDATE,
        TheliaEvents::CATALOG_PRICE_RULE_DELETE,
        TheliaEvents::CATALOG_PRICE_RULE_TOGGLE_ACTIVITY,
        TheliaEvents::CATALOG_PRICE_RULE_RECOMPUTE,
        TheliaEvents::FEATURE_CREATE,
        TheliaEvents::FEATURE_UPDATE,
        TheliaEvents::FEATURE_DELETE,
        TheliaEvents::FEATURE_UPDATE_POSITION,
        TheliaEvents::FEATURE_AV_CREATE,
        TheliaEvents::FEATURE_AV_UPDATE,
        TheliaEvents::FEATURE_AV_DELETE,
        TheliaEvents::FEATURE_AV_UPDATE_POSITION,
        TheliaEvents::ATTRIBUTE_CREATE,
        TheliaEvents::ATTRIBUTE_UPDATE,
        TheliaEvents::ATTRIBUTE_DELETE,
        TheliaEvents::ATTRIBUTE_UPDATE_POSITION,
        TheliaEvents::ATTRIBUTE_AV_CREATE,
        TheliaEvents::ATTRIBUTE_AV_UPDATE,
        TheliaEvents::ATTRIBUTE_AV_DELETE,
        TheliaEvents::ATTRIBUTE_AV_UPDATE_POSITION,
        TheliaEvents::PRODUCT_ASSOCIATION_TYPE_CREATE,
        TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE,
        TheliaEvents::PRODUCT_ASSOCIATION_TYPE_DELETE,
        TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE_POSITION,
        TheliaEvents::PRODUCT_ASSOCIATION_TYPE_TOGGLE_VISIBLE,
        TheliaEvents::PRODUCT_UPDATE_CONTENT_POSITION,
        TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_UPDATE_POSITION,
        TheliaEvents::PRODUCT_UPDATE_ACCESSORY_POSITION,
        TheliaEvents::PRODUCT_UPDATE_ASSOCIATION_POSITION,
        TheliaEvents::PRODUCT_VIDEO_CREATE,
        TheliaEvents::PRODUCT_VIDEO_UPDATE,
        TheliaEvents::PRODUCT_VIDEO_DELETE,
        TheliaEvents::PRODUCT_VIDEO_UPDATE_POSITION,
        TheliaEvents::PRODUCT_VIDEO_TOGGLE_VISIBILITY,
        TheliaEvents::TAX_CREATE,
        TheliaEvents::TAX_UPDATE,
        TheliaEvents::TAX_DELETE,
        TheliaEvents::TAX_RULE_CREATE,
        TheliaEvents::TAX_RULE_UPDATE,
        TheliaEvents::TAX_RULE_DELETE,
        TheliaEvents::TAX_RULE_SET_DEFAULT,
        TheliaEvents::TAX_RULE_TAXES_UPDATE,
        TheliaEvents::CURRENCY_CREATE,
        TheliaEvents::CURRENCY_UPDATE,
        TheliaEvents::CURRENCY_DELETE,
        TheliaEvents::CURRENCY_SET_DEFAULT,
        TheliaEvents::CURRENCY_SET_VISIBLE,
        TheliaEvents::CURRENCY_UPDATE_RATES,
        TheliaEvents::CURRENCY_UPDATE_POSITION,
        TheliaEvents::TEMPLATE_CREATE,
        TheliaEvents::TEMPLATE_UPDATE,
        TheliaEvents::TEMPLATE_DELETE,
        TheliaEvents::TEMPLATE_DUPLICATE,
        TheliaEvents::TEMPLATE_ADD_ATTRIBUTE,
        TheliaEvents::TEMPLATE_DELETE_ATTRIBUTE,
        TheliaEvents::TEMPLATE_ADD_FEATURE,
        TheliaEvents::TEMPLATE_DELETE_FEATURE,
        TheliaEvents::TEMPLATE_CHANGE_FEATURE_POSITION,
        TheliaEvents::TEMPLATE_CHANGE_ATTRIBUTE_POSITION,
        TheliaEvents::CONFIG_CREATE,
        TheliaEvents::CONFIG_SETVALUE,
        TheliaEvents::CONFIG_UPDATE,
        TheliaEvents::CONFIG_DELETE,
        TheliaEvents::LANG_UPDATE,
        TheliaEvents::LANG_CREATE,
        TheliaEvents::LANG_DELETE,
        TheliaEvents::LANG_DEFAULTBEHAVIOR,
        TheliaEvents::LANG_URL,
        TheliaEvents::LANG_TOGGLEDEFAULT,
        TheliaEvents::LANG_TOGGLEACTIVE,
        TheliaEvents::LANG_TOGGLEVISIBLE,
        TheliaEvents::HOOK_CREATE,
        TheliaEvents::HOOK_UPDATE,
        TheliaEvents::HOOK_DELETE,
        TheliaEvents::HOOK_TOGGLE_NATIVE,
        TheliaEvents::HOOK_TOGGLE_ACTIVATION,
        TheliaEvents::MODULE_HOOK_CREATE,
        TheliaEvents::MODULE_HOOK_UPDATE,
        TheliaEvents::MODULE_HOOK_DELETE,
        TheliaEvents::MODULE_HOOK_UPDATE_POSITION,
        TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION,
        TheliaEvents::MODULE_TOGGLE_ACTIVATION,
        TheliaEvents::META_DATA_CREATE,
        TheliaEvents::META_DATA_UPDATE,
        TheliaEvents::META_DATA_DELETE,
        TheliaEvents::IMPORT_SUCCESS,
    ];

    private const MODEL_FAMILIES = [
        'Product' => self::PRODUCT,
        'Category' => self::CATEGORY,
        'Content' => self::CONTENT,
        'Folder' => self::FOLDER,
        'Brand' => self::BRAND,
    ];

    private const CATALOGUE_MODEL = '#^Thelia\\\\Model\\\\(?:Product|Category|Content|Folder|Brand|Feature|Attribute|Tax|Currency|Sale|CatalogPriceRule|Template|Accessory|Country|State|Lang|Config|Hook|Module)#';

    public function __construct(
        private readonly SharedCachePurgeQueue $queue,
        private readonly ResourceCache $resourceCache,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $subscribed = [
            KernelEvents::TERMINATE => ['flush', 0],
            ConsoleEvents::TERMINATE => ['flush', 0],
        ];

        foreach (array_keys(self::FAMILIES) as $eventName) {
            $subscribed[$eventName] = ['onCatalogueChange', -255];
        }

        foreach (self::WHOLE_SHOP as $eventName) {
            $subscribed[$eventName] = ['onWholeShopChange', -255];
        }

        $subscribed[ResourcePersistedEvent::class] = ['onResourceChange', -255];

        return $subscribed;
    }

    public function onCatalogueChange(object $event, string $eventName): void
    {
        $family = self::FAMILIES[$eventName] ?? null;
        $id = null === $family ? null : $this->idOf($event, $family);

        $this->resourceCache->clear();
        $this->queue->add(null === $family || null === $id ? [SharedCacheKey::ALL] : SharedCacheKey::forChange($family, $id));
    }

    public function onWholeShopChange(): void
    {
        $this->resourceCache->clear();
        $this->queue->add([SharedCacheKey::ALL]);
    }

    public function onResourceChange(ResourcePersistedEvent $event): void
    {
        $model = $event->getModel();

        if (1 !== preg_match(self::CATALOGUE_MODEL, $model::class)) {
            return;
        }

        $this->resourceCache->clear();

        $family = self::MODEL_FAMILIES[substr(strrchr($model::class, '\\') ?: '', 1)] ?? null;
        $id = null !== $family && method_exists($model, 'getId') ? $model->getId() : null;

        $this->queue->add(null === $family || null === $id ? [SharedCacheKey::ALL] : SharedCacheKey::forChange($family, (int) $id));
    }

    public function flush(): void
    {
        $this->queue->flush();
    }

    private function idOf(object $event, string $family): ?int
    {
        $getter = 'get'.ucfirst($family);

        if (method_exists($event, $getter)) {
            $subject = $event->{$getter}();

            if (\is_object($subject) && method_exists($subject, 'getId') && null !== $subject->getId()) {
                return (int) $subject->getId();
            }
        }

        if (method_exists($event, 'getObjectId') && null !== $event->getObjectId()) {
            return (int) $event->getObjectId();
        }

        return null;
    }
}

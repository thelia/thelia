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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Event\ChoiceFilterEvent;
use Thelia\Model\Event\ConfigEvent;
use Thelia\Model\ProductSaleElementsQuery;

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
        TheliaEvents::TRANSLATION_WRITE_FILE,
        TheliaEvents::IMAGE_SAVE,
        TheliaEvents::IMAGE_UPDATE,
        TheliaEvents::IMAGE_UPDATE_POSITION,
        TheliaEvents::IMAGE_TOGGLE_VISIBILITY,
        TheliaEvents::IMAGE_DELETE,
        TheliaEvents::DOCUMENT_SAVE,
        TheliaEvents::DOCUMENT_UPDATE,
        TheliaEvents::DOCUMENT_UPDATE_POSITION,
        TheliaEvents::DOCUMENT_TOGGLE_VISIBILITY,
        TheliaEvents::DOCUMENT_DELETE,
        TheliaEvents::COUNTRY_CREATE,
        TheliaEvents::COUNTRY_UPDATE,
        TheliaEvents::COUNTRY_DELETE,
        TheliaEvents::COUNTRY_TOGGLE_DEFAULT,
        TheliaEvents::COUNTRY_TOGGLE_VISIBILITY,
        TheliaEvents::STATE_CREATE,
        TheliaEvents::STATE_UPDATE,
        TheliaEvents::STATE_DELETE,
        TheliaEvents::STATE_TOGGLE_VISIBILITY,
        TheliaEvents::AREA_CREATE,
        TheliaEvents::AREA_UPDATE,
        TheliaEvents::AREA_DELETE,
        TheliaEvents::AREA_ADD_COUNTRY,
        TheliaEvents::AREA_REMOVE_COUNTRY,
        TheliaEvents::AREA_POSTAGE_UPDATE,
        TheliaEvents::MODULE_UPDATE,
        TheliaEvents::MODULE_UPDATE_POSITION,
        TheliaEvents::MODULE_DELETE,
        TheliaEvents::HOOK_DEACTIVATION,
        TheliaEvents::HOOK_CREATE_ALL,
        TheliaEvents::FEATURE_ADD_TO_ALL_TEMPLATES,
        TheliaEvents::FEATURE_REMOVE_FROM_ALL_TEMPLATES,
        TheliaEvents::ATTRIBUTE_ADD_TO_ALL_TEMPLATES,
        TheliaEvents::ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES,
    ];

    private const MODEL_FAMILIES = [
        'Product' => self::PRODUCT,
        'Category' => self::CATEGORY,
        'Content' => self::CONTENT,
        'Folder' => self::FOLDER,
        'Brand' => self::BRAND,
    ];

    private const CATALOGUE_MODEL = '#^Thelia\\\\Model\\\\(?:Product|Category|Content|Folder|Brand|Feature|Attribute|Tax|Currency|Sale|CatalogPriceRule|Template|Accessory|Country|State|Area|Lang|Config|Hook|Module|MetaData|RewritingUrl)#';

    public function __construct(
        private readonly SharedCacheInvalidator $invalidator,
        private readonly SharedCachePurgeQueue $queue,
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

        $subscribed[ConfigEvent::POST_SAVE] = ['onWholeShopChange', -255];
        $subscribed[ConfigEvent::POST_DELETE] = ['onWholeShopChange', -255];
        $subscribed[ChoiceFilterEvent::POST_SAVE] = ['onChoiceFilterChange', -255];
        $subscribed[ChoiceFilterEvent::POST_DELETE] = ['onChoiceFilterChange', -255];
        $subscribed[ResourcePersistedEvent::class] = ['onResourceChange', -255];

        return $subscribed;
    }

    public function onCatalogueChange(object $event, string $eventName): void
    {
        $family = self::FAMILIES[$eventName] ?? null;
        $id = null === $family ? null : $this->idOf($event, $family);

        $this->purge($family, $id);
    }

    public function onWholeShopChange(): void
    {
        $this->invalidator->purge([SharedCacheKey::ALL]);
    }

    /**
     * The filters a category or a template offers are written straight to the table by the
     * back office, with no catalogue event: the pages that read them carry the `tfilters` key.
     */
    public function onChoiceFilterChange(ChoiceFilterEvent $event): void
    {
        $categoryId = $event->getModel()->getCategoryId();

        $this->invalidator->purge(null === $categoryId ? ['tfilters'] : ['tfilters', SharedCacheKey::item('categories', $categoryId)]);
    }

    public function onResourceChange(ResourcePersistedEvent $event): void
    {
        $model = $event->getModel();

        if (1 !== preg_match(self::CATALOGUE_MODEL, $model::class)) {
            return;
        }

        $family = self::MODEL_FAMILIES[substr(strrchr($model::class, '\\') ?: '', 1)] ?? null;
        $id = null !== $family && method_exists($model, 'getId') ? $model->getId() : null;

        $this->purge($family, null === $id ? null : (int) $id);
    }

    public function flush(): void
    {
        $this->queue->flush();
    }

    private function purge(?string $family, ?int $id): void
    {
        $this->invalidator->purge(null === $family || null === $id ? [SharedCacheKey::ALL] : SharedCacheKey::forChange($family, $id));
    }

    private function idOf(object $event, string $family): ?int
    {
        foreach (['get'.ucfirst($family), 'getCloned'.ucfirst($family)] as $getter) {
            if (!method_exists($event, $getter)) {
                continue;
            }

            $subject = $event->{$getter}();

            if (\is_object($subject) && method_exists($subject, 'getId') && null !== $subject->getId()) {
                return (int) $subject->getId();
            }
        }

        foreach (['get'.ucfirst($family).'Id', 'getObjectId'] as $getter) {
            if (method_exists($event, $getter) && null !== $event->{$getter}()) {
                return (int) $event->{$getter}();
            }
        }

        if (self::PRODUCT === $family && method_exists($event, 'getProductSaleElementId')) {
            return ProductSaleElementsQuery::create()->findPk($event->getProductSaleElementId())?->getProductId();
        }

        return null;
    }
}

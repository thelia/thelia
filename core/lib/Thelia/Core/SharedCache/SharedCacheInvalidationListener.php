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
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdateFilePositionEvent;
use Thelia\Model\BrandDocument;
use Thelia\Model\BrandImage;
use Thelia\Model\Category;
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryImage;
use Thelia\Model\ContentDocument;
use Thelia\Model\ContentImage;
use Thelia\Model\Event\BrandDocumentEvent;
use Thelia\Model\Event\BrandImageEvent;
use Thelia\Model\Event\CategoryDocumentEvent;
use Thelia\Model\Event\CategoryEvent;
use Thelia\Model\Event\CategoryImageEvent;
use Thelia\Model\Event\ChoiceFilterEvent;
use Thelia\Model\Event\ConfigEvent;
use Thelia\Model\Event\ContentDocumentEvent;
use Thelia\Model\Event\ContentImageEvent;
use Thelia\Model\Event\FolderDocumentEvent;
use Thelia\Model\Event\FolderImageEvent;
use Thelia\Model\Event\ProductDocumentEvent;
use Thelia\Model\Event\ProductImageEvent;
use Thelia\Model\Event\ProductSaleElementsProductDocumentEvent;
use Thelia\Model\Event\ProductSaleElementsProductImageEvent;
use Thelia\Model\Event\ProductSaleElementsProductVideoEvent;
use Thelia\Model\Event\ProductVideoEvent;
use Thelia\Model\FolderDocument;
use Thelia\Model\FolderImage;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductSaleElementsProductDocument;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Model\ProductSaleElementsProductVideo;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\ProductVideo;

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
        TheliaEvents::CONTENT_UPDATE_PUBLICATION => self::CONTENT,

        TheliaEvents::FOLDER_CREATE => self::FOLDER,
        TheliaEvents::FOLDER_UPDATE => self::FOLDER,
        TheliaEvents::FOLDER_DELETE => self::FOLDER,
        TheliaEvents::FOLDER_TOGGLE_VISIBILITY => self::FOLDER,
        TheliaEvents::FOLDER_UPDATE_POSITION => self::FOLDER,
        TheliaEvents::FOLDER_UPDATE_SEO => self::FOLDER,
        TheliaEvents::FOLDER_UPDATE_PUBLICATION => self::FOLDER,

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
        TheliaEvents::IMPORT_BEGIN,
        TheliaEvents::IMPORT_SUCCESS,
        TheliaEvents::TRANSLATION_WRITE_FILE,
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

    /**
     * The actions on an image, a document or a video: the page of the object the medium
     * belongs to is purged, the whole shop only when that object is not one of the five
     * families (the medium of a module).
     */
    private const MEDIA = [
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
        TheliaEvents::PRODUCT_VIDEO_CREATE,
        TheliaEvents::PRODUCT_VIDEO_UPDATE,
        TheliaEvents::PRODUCT_VIDEO_DELETE,
        TheliaEvents::PRODUCT_VIDEO_UPDATE_POSITION,
        TheliaEvents::PRODUCT_VIDEO_TOGGLE_VISIBILITY,
    ];

    /**
     * Writes the back office makes straight to a table, with no catalogue event: the move of
     * a category in the tree, the reorder of the media of a product, the quick edit of the
     * title of a medium and the association of a medium with a combination. Propel still
     * dispatches them.
     */
    private const WRITES = [
        CategoryEvent::POST_UPDATE,
        ProductImageEvent::POST_SAVE,
        ProductImageEvent::POST_DELETE,
        ProductDocumentEvent::POST_SAVE,
        ProductDocumentEvent::POST_DELETE,
        ProductVideoEvent::POST_SAVE,
        ProductVideoEvent::POST_DELETE,
        CategoryImageEvent::POST_SAVE,
        CategoryImageEvent::POST_DELETE,
        CategoryDocumentEvent::POST_SAVE,
        CategoryDocumentEvent::POST_DELETE,
        ContentImageEvent::POST_SAVE,
        ContentImageEvent::POST_DELETE,
        ContentDocumentEvent::POST_SAVE,
        ContentDocumentEvent::POST_DELETE,
        FolderImageEvent::POST_SAVE,
        FolderImageEvent::POST_DELETE,
        FolderDocumentEvent::POST_SAVE,
        FolderDocumentEvent::POST_DELETE,
        BrandImageEvent::POST_SAVE,
        BrandImageEvent::POST_DELETE,
        BrandDocumentEvent::POST_SAVE,
        BrandDocumentEvent::POST_DELETE,
        ProductSaleElementsProductImageEvent::POST_SAVE,
        ProductSaleElementsProductImageEvent::POST_DELETE,
        ProductSaleElementsProductDocumentEvent::POST_SAVE,
        ProductSaleElementsProductDocumentEvent::POST_DELETE,
        ProductSaleElementsProductVideoEvent::POST_SAVE,
        ProductSaleElementsProductVideoEvent::POST_DELETE,
    ];

    /**
     * Above the core action (128): the combination is still there to name its product.
     */
    private const BEFORE_THE_ACTION = [
        TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT => 255,
    ];

    /**
     * The media of the core, by the family of the object they belong to. The medium of a
     * module is not asked for its parent: nothing says the module answers it.
     */
    private const MEDIUM_FAMILIES = [
        ProductImage::class => self::PRODUCT,
        ProductDocument::class => self::PRODUCT,
        ProductVideo::class => self::PRODUCT,
        CategoryImage::class => self::CATEGORY,
        CategoryDocument::class => self::CATEGORY,
        ContentImage::class => self::CONTENT,
        ContentDocument::class => self::CONTENT,
        FolderImage::class => self::FOLDER,
        FolderDocument::class => self::FOLDER,
        BrandImage::class => self::BRAND,
        BrandDocument::class => self::BRAND,
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
            $subscribed[$eventName] = ['onCatalogueChange', self::BEFORE_THE_ACTION[$eventName] ?? -255];
        }

        foreach (self::WHOLE_SHOP as $eventName) {
            $subscribed[$eventName] = ['onWholeShopChange', -255];
        }

        foreach (self::MEDIA as $eventName) {
            $subscribed[$eventName] = ['onMediumChange', -255];
        }

        foreach (self::WRITES as $eventName) {
            $subscribed[$eventName] = ['onWrite', -255];
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

    public function onMediumChange(object $event): void
    {
        $medium = match (true) {
            $event instanceof FileCreateOrUpdateEvent => $event->getModel(),
            $event instanceof FileDeleteEvent => $event->getFileToDelete(),
            $event instanceof UpdateFilePositionEvent, $event instanceof FileToggleVisibilityEvent => $event->getQuery()?->findPk($event->getObjectId()),
            default => null,
        };

        $this->purgeTheParentOf($medium);
    }

    public function onWrite(object $event): void
    {
        $model = method_exists($event, 'getModel') ? $event->getModel() : null;

        match (true) {
            $model instanceof Category => $this->purge(self::CATEGORY, null === $model->getId() ? null : (int) $model->getId()),
            $model instanceof ProductSaleElementsProductImage,
            $model instanceof ProductSaleElementsProductDocument,
            $model instanceof ProductSaleElementsProductVideo => $this->purge(self::PRODUCT, ProductSaleElementsQuery::create()->findPk($model->getProductSaleElementsId())?->getProductId()),
            default => $this->purgeTheParentOf($model),
        };
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

    private function purgeTheParentOf(mixed $medium): void
    {
        foreach (self::MEDIUM_FAMILIES as $class => $family) {
            if ($medium instanceof $class) {
                $this->purge($family, (int) $medium->getParentId());

                return;
            }
        }

        $this->purge(null, null);
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

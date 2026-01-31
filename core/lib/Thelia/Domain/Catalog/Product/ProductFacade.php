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

namespace Thelia\Domain\Catalog\Product;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\FeatureProduct\FeatureProductDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductUpdateEvent;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductSetTemplateEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Domain\Catalog\Product\DTO\ProductCreateDTO;
use Thelia\Domain\Catalog\Product\DTO\ProductFeatureDTO;
use Thelia\Domain\Catalog\Product\DTO\ProductSeoDTO;
use Thelia\Domain\Catalog\Product\DTO\ProductUpdateDTO;
use Thelia\Domain\Catalog\Product\DTO\ProductWithPSECreateDTO;
use Thelia\Domain\Catalog\Product\Exception\ProductNotFoundException;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

final readonly class ProductFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function create(ProductCreateDTO $dto): Product
    {
        $event = new ProductCreateEvent();
        $event
            ->setRef($dto->ref)
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setDefaultCategory($dto->defaultCategoryId)
            ->setVisible($dto->visible)
            ->setVirtual($dto->virtual)
            ->setBasePrice($dto->basePrice)
            ->setBaseWeight($dto->baseWeight)
            ->setTaxRuleId($dto->taxRuleId)
            ->setCurrencyId($dto->currencyId)
            ->setBaseQuantity($dto->baseQuantity)
            ->setTemplateId($dto->templateId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_CREATE);

        return $event->getProduct();
    }

    public function update(int $productId, ProductUpdateDTO $dto): Product
    {
        $this->assertProductExists($productId);

        $event = new ProductUpdateEvent($productId);
        $event
            ->setRef($dto->ref)
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setDefaultCategory($dto->defaultCategoryId)
            ->setVisible($dto->visible)
            ->setVirtual($dto->virtual)
            ->setBasePrice($dto->basePrice)
            ->setBaseWeight($dto->baseWeight)
            ->setTaxRuleId($dto->taxRuleId)
            ->setCurrencyId($dto->currencyId)
            ->setBaseQuantity($dto->baseQuantity)
            ->setTemplateId($dto->templateId)
            ->setChapo($dto->chapo)
            ->setDescription($dto->description)
            ->setPostscriptum($dto->postscriptum)
            ->setBrandId($dto->brandId)
            ->setVirtualDocumentId($dto->virtualDocumentId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE);

        return $event->getProduct();
    }

    public function delete(int $productId): void
    {
        $this->assertProductExists($productId);

        $event = new ProductDeleteEvent($productId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_DELETE);
    }

    public function clone(Product $originalProduct, string $newRef, string $locale): Product
    {
        $event = new ProductCloneEvent($newRef, $locale, $originalProduct);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_CLONE);

        return $event->getClonedProduct();
    }

    public function toggleVisibility(int $productId): Product
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductToggleVisibilityEvent($product);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_TOGGLE_VISIBILITY);

        return $event->getProduct();
    }

    public function updatePosition(int $productId, int $categoryId, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $this->assertProductExists($productId);

        $event = new UpdatePositionEvent($productId, $mode, $position);
        $event->setReferrerId($categoryId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE_POSITION);
    }

    public function updateSeo(int $productId, ProductSeoDTO $dto): Product
    {
        $this->assertProductExists($productId);

        $event = new UpdateSeoEvent($productId);
        $event
            ->setLocale($dto->locale)
            ->setUrl($dto->url)
            ->setMetaTitle($dto->metaTitle)
            ->setMetaDescription($dto->metaDescription)
            ->setMetaKeywords($dto->metaKeywords);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE_SEO);

        return $event->getObject();
    }

    public function addToCategory(int $productId, int $categoryId): void
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductAddCategoryEvent($product, $categoryId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_ADD_CATEGORY);
    }

    public function removeFromCategory(int $productId, int $categoryId): void
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductDeleteCategoryEvent($product, $categoryId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_REMOVE_CATEGORY);
    }

    public function setDefaultCategory(int $productId, int $categoryId): void
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $product->setDefaultCategory($categoryId);
    }

    public function addContent(int $productId, int $contentId): void
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductAddContentEvent($product, $contentId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_ADD_CONTENT);
    }

    public function removeContent(int $productId, int $contentId): void
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductDeleteContentEvent($product, $contentId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_REMOVE_CONTENT);
    }

    public function addAccessory(int $productId, int $accessoryProductId): void
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductAddAccessoryEvent($product, $accessoryProductId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_ADD_ACCESSORY);
    }

    public function removeAccessory(int $productId, int $accessoryProductId): void
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductDeleteAccessoryEvent($product, $accessoryProductId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_REMOVE_ACCESSORY);
    }

    public function setTemplate(int $productId, ?int $templateId, ?int $currencyId = null): Product
    {
        $product = $this->getById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductSetTemplateEvent($product, $templateId, $currencyId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_SET_TEMPLATE);

        return $event->getProduct();
    }

    public function setFeatureValue(int $productId, ProductFeatureDTO $dto): void
    {
        $this->assertProductExists($productId);

        $event = new FeatureProductUpdateEvent($productId, $dto->featureId, $dto->featureValue, $dto->isTextValue);
        $event->setLocale($dto->locale);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE);
    }

    public function deleteFeatureValue(int $productId, int $featureId): void
    {
        $this->assertProductExists($productId);

        $event = new FeatureProductDeleteEvent($productId, $featureId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE);
    }

    public function getById(int $productId): ?Product
    {
        return ProductQuery::create()->findPk($productId);
    }

    public function createWithPSE(ProductWithPSECreateDTO $dto): Product
    {
        $product = $this->create($dto->toProductCreateDTO());

        $defaultPSE = $this->getDefaultPSE($product->getId());

        if (null !== $defaultPSE) {
            $pseUpdateDTO = $dto->toPSEUpdateDTO();

            $event = new \Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent($product, $defaultPSE->getId());
            $event
                ->setReference($pseUpdateDTO->reference)
                ->setPrice($pseUpdateDTO->price)
                ->setCurrencyId($pseUpdateDTO->currencyId)
                ->setWeight($pseUpdateDTO->weight)
                ->setQuantity($pseUpdateDTO->quantity)
                ->setSalePrice($pseUpdateDTO->salePrice)
                ->setOnsale($pseUpdateDTO->onSale ? 1 : 0)
                ->setIsnew($pseUpdateDTO->isNew ? 1 : 0)
                ->setIsdefault(true)
                ->setEanCode($pseUpdateDTO->eanCode)
                ->setTaxRuleId($pseUpdateDTO->taxRuleId)
                ->setFromDefaultCurrency(0);

            $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);
        }

        return $product;
    }

    public function getDefaultPSE(int $productId): ?ProductSaleElements
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->filterByIsDefault(true)
            ->findOne();
    }

    /**
     * @return ProductSaleElements[]
     */
    public function getPSEs(int $productId): array
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->orderByIsDefault('DESC')
            ->orderByPosition()
            ->find()
            ->getData();
    }

    private function assertProductExists(int $productId): void
    {
        if (null === ProductQuery::create()->findPk($productId)) {
            throw ProductNotFoundException::withId($productId);
        }
    }
}

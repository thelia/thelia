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
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementToggleVisibilityEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Domain\Catalog\Product\DTO\CombinationGenerationDTO;
use Thelia\Domain\Catalog\Product\DTO\PSECreateDTO;
use Thelia\Domain\Catalog\Product\DTO\PSEUpdateDTO;
use Thelia\Domain\Catalog\Product\Exception\ProductNotFoundException;
use Thelia\Domain\Catalog\Product\Exception\PSENotFoundException;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

final readonly class PSEFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function create(int $productId, PSECreateDTO $dto): ProductSaleElements
    {
        $product = $this->getProductById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductSaleElementCreateEvent($product, $dto->attributeAvIds, $dto->currencyId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT);

        return $event->getProductSaleElement();
    }

    public function update(int $productId, int $pseId, PSEUpdateDTO $dto): void
    {
        $product = $this->getProductById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $this->assertPSEExists($pseId);

        $event = new ProductSaleElementUpdateEvent($product, $pseId);
        $event
            ->setReference($dto->reference)
            ->setPrice($dto->price)
            ->setCurrencyId($dto->currencyId)
            ->setWeight($dto->weight)
            ->setQuantity($dto->quantity)
            ->setSalePrice($dto->salePrice)
            ->setOnsale($dto->onSale ? 1 : 0)
            ->setIsnew($dto->isNew ? 1 : 0)
            ->setIsdefault($dto->isDefault)
            ->setEanCode($dto->eanCode)
            ->setTaxRuleId($dto->taxRuleId)
            ->setFromDefaultCurrency($dto->fromDefaultCurrency ? 1 : 0);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);
    }

    public function delete(int $pseId, int $currencyId): void
    {
        $this->assertPSEExists($pseId);

        $event = new ProductSaleElementDeleteEvent($pseId, $currencyId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT);
    }

    public function toggleVisibility(int $pseId): void
    {
        $this->assertPSEExists($pseId);

        $event = new ProductSaleElementToggleVisibilityEvent($pseId);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_TOGGLE_VISIBILITY);
    }

    public function updatePosition(int $pseId, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $this->assertPSEExists($pseId);

        $event = new UpdatePositionEvent($pseId, $mode, $position);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_PRODUCT_SALE_ELEMENT_UPDATE_POSITION);
    }

    public function generateCombinations(int $productId, CombinationGenerationDTO $dto): void
    {
        $product = $this->getProductById($productId);

        if (null === $product) {
            throw ProductNotFoundException::withId($productId);
        }

        $event = new ProductCombinationGenerationEvent($product, $dto->currencyId, $dto->combinations);
        $event
            ->setReference($dto->reference)
            ->setPrice($dto->price)
            ->setWeight($dto->weight)
            ->setQuantity($dto->quantity)
            ->setSalePrice($dto->salePrice)
            ->setOnsale($dto->onSale ? 1 : 0)
            ->setIsnew($dto->isNew ? 1 : 0)
            ->setEanCode($dto->eanCode);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_COMBINATION_GENERATION);
    }

    public function getById(int $pseId): ?ProductSaleElements
    {
        return ProductSaleElementsQuery::create()->findPk($pseId);
    }

    /**
     * @return ProductSaleElements[]
     */
    public function getByProduct(int $productId): array
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->orderByIsDefault('DESC')
            ->orderByPosition()
            ->find()
            ->getData();
    }

    public function getDefaultPSE(int $productId): ?ProductSaleElements
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->filterByIsDefault(true)
            ->findOne();
    }

    public function getStock(int $pseId): float
    {
        $pse = $this->getById($pseId);

        if (null === $pse) {
            throw PSENotFoundException::withId($pseId);
        }

        return $pse->getQuantity();
    }

    public function updateStock(int $pseId, float $quantity): void
    {
        $pse = $this->getById($pseId);

        if (null === $pse) {
            throw PSENotFoundException::withId($pseId);
        }

        $pse->setQuantity($quantity)->save();
    }

    public function decreaseStock(int $pseId, float $quantity): void
    {
        $pse = $this->getById($pseId);

        if (null === $pse) {
            throw PSENotFoundException::withId($pseId);
        }

        $newQuantity = max(0, $pse->getQuantity() - $quantity);
        $pse->setQuantity($newQuantity)->save();
    }

    public function increaseStock(int $pseId, float $quantity): void
    {
        $pse = $this->getById($pseId);

        if (null === $pse) {
            throw PSENotFoundException::withId($pseId);
        }

        $pse->setQuantity($pse->getQuantity() + $quantity)->save();
    }

    private function getProductById(int $productId): ?Product
    {
        return ProductQuery::create()->findPk($productId);
    }

    private function assertPSEExists(int $pseId): void
    {
        if (null === ProductSaleElementsQuery::create()->findPk($pseId)) {
            throw PSENotFoundException::withId($pseId);
        }
    }
}

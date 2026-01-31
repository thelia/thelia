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

namespace Thelia\Domain\Catalog\Brand;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Brand\BrandCreateEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Brand\BrandToggleVisibilityEvent;
use Thelia\Core\Event\Brand\BrandUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Domain\Brand\DTO\BrandCreateDTO;
use Thelia\Domain\Brand\DTO\BrandSeoDTO;
use Thelia\Domain\Brand\DTO\BrandUpdateDTO;
use Thelia\Domain\Brand\Exception\BrandNotFoundException;
use Thelia\Model\Brand;
use Thelia\Model\BrandQuery;

final readonly class BrandFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function create(BrandCreateDTO $dto): Brand
    {
        $event = new BrandCreateEvent();
        $event
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setVisible($dto->visible);

        $this->dispatcher->dispatch($event, TheliaEvents::BRAND_CREATE);

        return $event->getBrand();
    }

    public function update(int $brandId, BrandUpdateDTO $dto): Brand
    {
        $event = new BrandUpdateEvent($brandId);
        $event
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setVisible($dto->visible)
            ->setChapo($dto->chapo)
            ->setDescription($dto->description)
            ->setPostscriptum($dto->postscriptum)
            ->setLogoImageId($dto->logoImageId);

        $this->dispatcher->dispatch($event, TheliaEvents::BRAND_UPDATE);

        return $event->getBrand();
    }

    public function delete(int $brandId): void
    {
        $event = new BrandDeleteEvent($brandId);

        $this->dispatcher->dispatch($event, TheliaEvents::BRAND_DELETE);
    }

    public function toggleVisibility(int $brandId): Brand
    {
        $brand = $this->getById($brandId);

        if (null === $brand) {
            throw BrandNotFoundException::withId($brandId);
        }

        $event = new BrandToggleVisibilityEvent($brand);

        $this->dispatcher->dispatch($event, TheliaEvents::BRAND_TOGGLE_VISIBILITY);

        return $event->getBrand();
    }

    public function updatePosition(int $brandId, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $event = new UpdatePositionEvent($brandId, $mode, $position);

        $this->dispatcher->dispatch($event, TheliaEvents::BRAND_UPDATE_POSITION);
    }

    public function updateSeo(int $brandId, BrandSeoDTO $dto): Brand
    {
        $brand = $this->getById($brandId);

        if (null === $brand) {
            throw BrandNotFoundException::withId($brandId);
        }

        $event = new UpdateSeoEvent($brandId);
        $event
            ->setLocale($dto->locale)
            ->setUrl($dto->url)
            ->setMetaTitle($dto->metaTitle)
            ->setMetaDescription($dto->metaDescription)
            ->setMetaKeywords($dto->metaKeywords);

        $this->dispatcher->dispatch($event, TheliaEvents::BRAND_UPDATE_SEO);

        $brand->reload();

        return $brand;
    }

    public function getById(int $brandId): ?Brand
    {
        return BrandQuery::create()->findPk($brandId);
    }

    public function getAll(bool $visibleOnly = false): array
    {
        $query = BrandQuery::create()
            ->orderByPosition();

        if ($visibleOnly) {
            $query->filterByVisible(true);
        }

        return $query->find()->getData();
    }
}

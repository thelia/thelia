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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeCreateEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeDeleteEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeToggleVisibleEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\Lang;
use Thelia\Model\ProductAssociationType as ProductAssociationTypeModel;
use Thelia\Model\ProductAssociationTypeI18nQuery;
use Thelia\Model\ProductAssociationTypeQuery;

/**
 * The merchant's side of the types a relation between two products can carry: which
 * blocks the product sheet offers, in which order, and which of them relate the other
 * product back.
 *
 * The code is set once and never changes. The core names the accessory type by its code
 * to keep addAccessory() meaning what it always meant, the front office blocks are keyed
 * on it, and a merchant renaming it would break both — the translatable title is what
 * they are meant to reword instead.
 *
 * Codes listed in ProductAssociationType::UNDELETABLE_CODES are refused deletion, and a
 * type still carrying relations is refused too: the foreign key would refuse it anyway,
 * with a Propel error nobody can act on. Hiding a type is the answer to "stop offering
 * this", and it keeps the relations already saved under it.
 */
class ProductAssociationType extends BaseAction implements EventSubscriberInterface
{
    public function create(ProductAssociationTypeCreateEvent $event): void
    {
        $type = new ProductAssociationTypeModel();

        $type
            ->setCode($event->getCode())
            ->setVisible($event->getVisible())
            ->setReciprocal($event->getReciprocal())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->save();

        $this->fillShopLanguage($type, $event->getLocale(), $event->getTitle(), $event->getDescription());

        $event->setProductAssociationType($type);
    }

    /**
     * The code is deliberately left out: it is the stable identifier the core and the
     * themes hold this type by.
     */
    public function update(ProductAssociationTypeUpdateEvent $event): void
    {
        $type = $this->getProductAssociationType($event->getProductAssociationTypeId());

        $type
            ->setVisible($event->getVisible())
            ->setReciprocal($event->getReciprocal())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->save();

        $this->fillShopLanguage($type, $event->getLocale(), $event->getTitle(), $event->getDescription());

        $event->setProductAssociationType($type);
    }

    /**
     * @throws \LogicException when the type is one the shop may not do without, or still holds relations
     */
    public function delete(ProductAssociationTypeDeleteEvent $event): void
    {
        $type = $this->getProductAssociationType($event->getProductAssociationTypeId());

        if (!$type->isDeletable()) {
            throw new \LogicException(Translator::getInstance()->trans('The product relation type "%code" is required by the shop and cannot be deleted. It can still be hidden.', ['%code' => (string) $type->getCode()]));
        }

        $relations = AccessoryQuery::create()
            ->filterByTypeId($type->getId())
            ->count();

        if ($relations > 0) {
            throw new \LogicException(Translator::getInstance()->trans('The product relation type "%code" still holds %count relations and cannot be deleted. Remove them first, or hide the type.', ['%code' => (string) $type->getCode(), '%count' => $relations]));
        }

        $type->delete();

        $event->setProductAssociationType($type);
    }

    /**
     * Hiding a type stops the product sheet offering it, and keeps the relations already
     * saved under it.
     */
    public function toggleVisible(ProductAssociationTypeToggleVisibleEvent $event): void
    {
        $type = $this->getProductAssociationType($event->getProductAssociationTypeId());

        $type
            ->setVisible($type->isVisible() ? 0 : 1)
            ->save();

        $event->setProductAssociationType($type);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(ProductAssociationTypeQuery::create(), $event, $dispatcher);
    }

    /**
     * Makes sure the shop language never ends up without a title for the type.
     *
     * The back office writes one language at a time, and the language it is being read in
     * is not necessarily the one of the shop: a merchant working in French on a shop whose
     * default language is English produces a type with no English title in three clicks.
     * I18n then forges the literal string "DEFAULT TITLE", which is what would title a
     * block of every product sheet. Copying what was just written is a poor translation,
     * and a far better placeholder.
     *
     * A title the shop language already has is left alone: this fills a hole, it does not
     * overwrite a translation.
     */
    private function fillShopLanguage(ProductAssociationTypeModel $type, string $writtenLocale, ?string $title, ?string $description): void
    {
        $shopLocale = (string) Lang::getDefaultLanguage()->getLocale();

        if ($shopLocale === $writtenLocale) {
            return;
        }

        $shopWording = ProductAssociationTypeI18nQuery::create()
            ->filterById($type->getId())
            ->filterByLocale($shopLocale)
            ->findOne();

        if (null !== $shopWording && '' !== (string) $shopWording->getTitle()) {
            return;
        }

        $type
            ->setLocale($shopLocale)
            ->setTitle($title)
            ->setDescription($description)
            ->save();

        // The caller reads the type back in the language it was written in.
        $type->setLocale($writtenLocale);
    }

    private function getProductAssociationType(int $productAssociationTypeId): ProductAssociationTypeModel
    {
        return ProductAssociationTypeQuery::create()->findPk($productAssociationTypeId)
            ?? throw new \LogicException(\sprintf('Product association type %d not found', $productAssociationTypeId));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_CREATE => ['create', 128],
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE => ['update', 128],
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_DELETE => ['delete', 128],
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_TOGGLE_VISIBLE => ['toggleVisible', 128],
        ];
    }
}

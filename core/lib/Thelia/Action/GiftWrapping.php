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
use Thelia\Core\Event\GiftWrapping\GiftWrappingCreateEvent;
use Thelia\Core\Event\GiftWrapping\GiftWrappingDeleteEvent;
use Thelia\Core\Event\GiftWrapping\GiftWrappingToggleActiveEvent;
use Thelia\Core\Event\GiftWrapping\GiftWrappingUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\GiftWrapping as GiftWrappingModel;
use Thelia\Model\GiftWrappingI18nQuery;
use Thelia\Model\GiftWrappingQuery;
use Thelia\Model\Lang;

/**
 * The merchant's side of the gift wrapping services: the list of wrappings the buyer is
 * offered at checkout, with the price and the tax rule each of them carries.
 *
 * Deletion is allowed here, unlike a consent: an order does not point back at the
 * wrapping row, it carries its own frozen line. A shop that merely wants to stop
 * offering one turns it off, which is the answer in almost every case.
 */
class GiftWrapping extends BaseAction implements EventSubscriberInterface
{
    public function create(GiftWrappingCreateEvent $event): void
    {
        $giftWrapping = new GiftWrappingModel();

        $giftWrapping
            ->setCode($event->getCode())
            ->setPrice($event->getPrice())
            ->setTaxRuleId($event->getTaxRuleId())
            ->setActive($event->getActive())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->save();

        $this->fillShopLanguage($giftWrapping, $event->getLocale(), $event->getTitle(), $event->getDescription());

        $event->setGiftWrapping($giftWrapping);
    }

    public function update(GiftWrappingUpdateEvent $event): void
    {
        $giftWrapping = $this->getGiftWrapping($event->getGiftWrappingId());

        $giftWrapping
            ->setPrice($event->getPrice())
            ->setTaxRuleId($event->getTaxRuleId())
            ->setActive($event->getActive())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->save();

        $this->fillShopLanguage($giftWrapping, $event->getLocale(), $event->getTitle(), $event->getDescription());

        $event->setGiftWrapping($giftWrapping);
    }

    /**
     * A wrapping a cart still points at is released by the ON DELETE SET NULL of the
     * foreign key: the buyer loses the choice, which is what deleting the service means.
     * Orders keep their line, wording and tax rule included, because that line is a copy.
     */
    public function delete(GiftWrappingDeleteEvent $event): void
    {
        $giftWrapping = $this->getGiftWrapping($event->getGiftWrappingId());

        $giftWrapping->delete();

        $event->setGiftWrapping($giftWrapping);
    }

    public function toggleActive(GiftWrappingToggleActiveEvent $event): void
    {
        $giftWrapping = $this->getGiftWrapping($event->getGiftWrappingId());

        $giftWrapping
            ->setActive($giftWrapping->isActive() ? 0 : 1)
            ->save();

        $event->setGiftWrapping($giftWrapping);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(GiftWrappingQuery::create(), $event, $dispatcher);
    }

    /**
     * Makes sure the shop language never ends up without a wording for the wrapping.
     *
     * Same hole, and same filling, as the consents: the back office writes one language at
     * a time, and a merchant working in French on an English shop would otherwise leave the
     * buyer with the literal string I18n forges when it finds no wording — on a line of
     * their basket, and then frozen on their invoice. A wording the shop language already
     * has is left alone.
     */
    private function fillShopLanguage(GiftWrappingModel $giftWrapping, string $writtenLocale, ?string $title, ?string $description): void
    {
        $shopLocale = (string) Lang::getDefaultLanguage()->getLocale();

        if ($shopLocale === $writtenLocale) {
            return;
        }

        $shopWording = GiftWrappingI18nQuery::create()
            ->filterById($giftWrapping->getId())
            ->filterByLocale($shopLocale)
            ->findOne();

        if (null !== $shopWording && '' !== (string) $shopWording->getTitle()) {
            return;
        }

        $giftWrapping
            ->setLocale($shopLocale)
            ->setTitle($title)
            ->setDescription($description)
            ->save();

        // The caller reads the wrapping back in the language it was written in.
        $giftWrapping->setLocale($writtenLocale);
    }

    private function getGiftWrapping(int $giftWrappingId): GiftWrappingModel
    {
        return GiftWrappingQuery::create()->findPk($giftWrappingId)
            ?? throw new \LogicException(\sprintf('Gift wrapping %d not found', $giftWrappingId));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::GIFT_WRAPPING_CREATE => ['create', 128],
            TheliaEvents::GIFT_WRAPPING_UPDATE => ['update', 128],
            TheliaEvents::GIFT_WRAPPING_DELETE => ['delete', 128],
            TheliaEvents::GIFT_WRAPPING_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::GIFT_WRAPPING_TOGGLE_ACTIVE => ['toggleActive', 128],
        ];
    }
}

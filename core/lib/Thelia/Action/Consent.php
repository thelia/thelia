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
use Thelia\Core\Event\Consent\ConsentCreateEvent;
use Thelia\Core\Event\Consent\ConsentDeleteEvent;
use Thelia\Core\Event\Consent\ConsentToggleActiveEvent;
use Thelia\Core\Event\Consent\ConsentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Consent as ConsentModel;
use Thelia\Model\ConsentI18nQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Lang;

/**
 * The merchant's side of the checkout consents: the list of boxes the buyer is shown at
 * the payment step.
 *
 * Codes listed in Consent::UNDELETABLE_CODES are the ones a shop cannot do without —
 * the terms and conditions, for one. They are refused deletion, and nothing else: a
 * shop whose theme cannot display the box yet has to be able to stop asking for it, or
 * its checkout is over. Turning one off, making it optional and rewording it stay the
 * merchant's call; the row itself stays, with the proof it already collected.
 */
class Consent extends BaseAction implements EventSubscriberInterface
{
    public function create(ConsentCreateEvent $event): void
    {
        $consent = new ConsentModel();

        $consent
            ->setCode($event->getCode())
            ->setContentId($event->getContentId())
            ->setMandatory($event->getMandatory())
            ->setActive($event->getActive())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->save();

        $this->fillShopLanguage($consent, $event->getLocale(), $event->getTitle(), $event->getDescription());

        $event->setConsent($consent);
    }

    public function update(ConsentUpdateEvent $event): void
    {
        $consent = $this->getConsent($event->getConsentId());

        $consent
            ->setContentId($event->getContentId())
            ->setMandatory($event->getMandatory())
            ->setActive($event->getActive())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->save();

        $this->fillShopLanguage($consent, $event->getLocale(), $event->getTitle(), $event->getDescription());

        $event->setConsent($consent);
    }

    /**
     * @throws \LogicException when the consent is one the shop may not do without
     */
    public function delete(ConsentDeleteEvent $event): void
    {
        $consent = $this->getConsent($event->getConsentId());

        if (!$consent->isDeletable()) {
            throw new \LogicException(Translator::getInstance()->trans('The consent "%code" is required by the shop and cannot be deleted. It can still be turned off, or made optional.', ['%code' => (string) $consent->getCode()]));
        }

        $consent->delete();

        $event->setConsent($consent);
    }

    /**
     * Turning a consent off keeps the acceptances already collected under it, which is
     * what makes it the answer to "stop asking for this" rather than deletion.
     */
    public function toggleActive(ConsentToggleActiveEvent $event): void
    {
        $consent = $this->getConsent($event->getConsentId());

        $consent
            ->setActive($consent->isActive() ? 0 : 1)
            ->save();

        $event->setConsent($consent);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(ConsentQuery::create(), $event, $dispatcher);
    }

    /**
     * Makes sure the shop language never ends up without a wording for the consent.
     *
     * The back office writes one language at a time, and the language it is being read
     * in is not necessarily the one of the shop: a merchant working in French on a shop
     * whose default language is English produces a consent with no English wording in
     * three clicks. I18n then forges the literal string "DEFAULT TITLE" — shown to the
     * buyer on a box they must tick, and frozen on their order as the proof. Copying
     * what was just written is a poor translation, and a far better placeholder.
     *
     * A wording the shop language already has is left alone: this fills a hole, it does
     * not overwrite a translation.
     */
    private function fillShopLanguage(ConsentModel $consent, string $writtenLocale, ?string $title, ?string $description): void
    {
        $shopLocale = (string) Lang::getDefaultLanguage()->getLocale();

        if ($shopLocale === $writtenLocale) {
            return;
        }

        $shopWording = ConsentI18nQuery::create()
            ->filterById($consent->getId())
            ->filterByLocale($shopLocale)
            ->findOne();

        if (null !== $shopWording && '' !== (string) $shopWording->getTitle()) {
            return;
        }

        $consent
            ->setLocale($shopLocale)
            ->setTitle($title)
            ->setDescription($description)
            ->save();

        // The caller reads the consent back in the language it was written in.
        $consent->setLocale($writtenLocale);
    }

    private function getConsent(int $consentId): ConsentModel
    {
        return ConsentQuery::create()->findPk($consentId)
            ?? throw new \LogicException(\sprintf('Consent %d not found', $consentId));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CONSENT_CREATE => ['create', 128],
            TheliaEvents::CONSENT_UPDATE => ['update', 128],
            TheliaEvents::CONSENT_DELETE => ['delete', 128],
            TheliaEvents::CONSENT_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::CONSENT_TOGGLE_ACTIVE => ['toggleActive', 128],
        ];
    }
}

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
use Thelia\Model\ConsentQuery;

/**
 * The merchant's side of the checkout consents: the list of boxes the buyer is shown at
 * the payment step.
 *
 * Codes listed in Consent::UNDELETABLE_CODES are the ones a shop cannot do without —
 * the terms and conditions, for one. They are refused deletion, and refused the two
 * edits that amount to the same thing: turning them off, and making them optional.
 * Everything else about them stays editable, the wording first of all.
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

        $event->setConsent($consent);
    }

    public function update(ConsentUpdateEvent $event): void
    {
        $consent = $this->getConsent($event->getConsentId());

        $consent
            ->setContentId($event->getContentId())
            ->setMandatory($consent->isDeletable() ? $event->getMandatory() : 1)
            ->setActive($consent->isDeletable() ? $event->getActive() : 1)
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->save();

        $event->setConsent($consent);
    }

    /**
     * @throws \LogicException when the consent is one the shop may not do without
     */
    public function delete(ConsentDeleteEvent $event): void
    {
        $consent = $this->getConsent($event->getConsentId());

        if (!$consent->isDeletable()) {
            throw new \LogicException(Translator::getInstance()->trans('The consent "%code" is required by the shop and cannot be deleted or turned off. Only its wording can be changed.', ['%code' => (string) $consent->getCode()]));
        }

        $consent->delete();

        $event->setConsent($consent);
    }

    /**
     * Turning a consent off keeps the acceptances already collected under it, which is
     * what makes it the answer to "stop asking for this" rather than deletion.
     *
     * @throws \LogicException when the consent is one the shop may not do without
     */
    public function toggleActive(ConsentToggleActiveEvent $event): void
    {
        $consent = $this->getConsent($event->getConsentId());

        if (!$consent->isDeletable()) {
            throw new \LogicException(Translator::getInstance()->trans('The consent "%code" is required by the shop and cannot be turned off.', ['%code' => (string) $consent->getCode()]));
        }

        $consent
            ->setActive($consent->isActive() ? 0 : 1)
            ->save();

        $event->setConsent($consent);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(ConsentQuery::create(), $event, $dispatcher);
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

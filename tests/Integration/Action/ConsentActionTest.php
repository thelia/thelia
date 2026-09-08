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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Consent\ConsentCreateEvent;
use Thelia\Core\Event\Consent\ConsentDeleteEvent;
use Thelia\Core\Event\Consent\ConsentToggleActiveEvent;
use Thelia\Core\Event\Consent\ConsentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\Consent;
use Thelia\Model\ConsentI18nQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ConsentActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsTheConsentWithItsWording(): void
    {
        $event = (new ConsentCreateEvent())
            ->setCode('newsletter-optin')
            ->setLocale('en_US')
            ->setTitle('Send me the newsletter')
            ->setDescription('You can unsubscribe at any time.')
            ->setMandatory(0)
            ->setActive(1);

        $this->dispatch($event, TheliaEvents::CONSENT_CREATE);

        $consent = $event->getConsent();
        self::assertNotNull($consent);
        self::assertSame('newsletter-optin', $consent->getCode());
        self::assertSame('Send me the newsletter', $consent->setLocale('en_US')->getTitle());
        self::assertSame(0, $consent->getMandatory());
        self::assertSame(1, $consent->getActive());
        self::assertGreaterThan(0, $consent->getPosition());
    }

    public function testUpdateReplacesTheWordingAndTheFlags(): void
    {
        $consent = $this->createConsent('newsletter-optin', 'Old wording');

        $event = (new ConsentUpdateEvent($consent->getId()))
            ->setCode('newsletter-optin')
            ->setLocale('en_US')
            ->setTitle('New wording')
            ->setDescription(null)
            ->setMandatory(1)
            ->setActive(0);

        $this->dispatch($event, TheliaEvents::CONSENT_UPDATE);

        $reloaded = ConsentQuery::create()->findPk($consent->getId());
        self::assertNotNull($reloaded);
        self::assertSame('New wording', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame(1, $reloaded->getMandatory());
        self::assertSame(0, $reloaded->getActive());
    }

    public function testDeleteRemovesAnOrdinaryConsent(): void
    {
        $consent = $this->createConsent('newsletter-optin', 'Send me the newsletter');
        $consentId = $consent->getId();

        $this->dispatch(new ConsentDeleteEvent($consentId), TheliaEvents::CONSENT_DELETE);

        self::assertNull(ConsentQuery::create()->findPk($consentId));
    }

    public function testTheTermsAndConditionsConsentCannotBeDeleted(): void
    {
        $consent = $this->termsAndConditionsConsent();

        $this->expectException(\LogicException::class);

        try {
            $this->dispatch(new ConsentDeleteEvent($consent->getId()), TheliaEvents::CONSENT_DELETE);
        } finally {
            self::assertNotNull(ConsentQuery::create()->findPk($consent->getId()));
        }
    }

    /**
     * A shop whose theme cannot yet display the box has to be able to stop asking for
     * it, or its checkout is over. Deletion stays refused, turning off does not.
     */
    public function testTheTermsAndConditionsConsentCanBeTurnedOffAndBackOn(): void
    {
        $consent = $this->termsAndConditionsConsent();

        $this->dispatch(new ConsentToggleActiveEvent($consent->getId()), TheliaEvents::CONSENT_TOGGLE_ACTIVE);
        self::assertSame(0, ConsentQuery::create()->findPk($consent->getId())?->getActive());

        $this->dispatch(new ConsentToggleActiveEvent($consent->getId()), TheliaEvents::CONSENT_TOGGLE_ACTIVE);
        self::assertSame(1, ConsentQuery::create()->findPk($consent->getId())?->getActive());
    }

    public function testTheTermsAndConditionsConsentCanBeMadeOptionalAndRequiredAgain(): void
    {
        $consent = $this->termsAndConditionsConsent();

        $this->dispatch(
            (new ConsentUpdateEvent($consent->getId()))
                ->setLocale('en_US')
                ->setTitle('Reworded terms')
                ->setMandatory(0)
                ->setActive(1),
            TheliaEvents::CONSENT_UPDATE,
        );

        $reloaded = ConsentQuery::create()->findPk($consent->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Reworded terms', $reloaded->setLocale('en_US')->getTitle(), 'The wording stays the merchant\'s to write.');
        self::assertSame(0, $reloaded->getMandatory(), 'A shop must be able to stop requiring the box while its theme cannot display it.');

        $this->dispatch(
            (new ConsentUpdateEvent($consent->getId()))
                ->setLocale('en_US')
                ->setTitle('Reworded terms')
                ->setMandatory(1)
                ->setActive(1),
            TheliaEvents::CONSENT_UPDATE,
        );

        self::assertSame(1, ConsentQuery::create()->findPk($consent->getId())?->getMandatory());
    }

    public function testToggleActiveTurnsAConsentOffAndBackOn(): void
    {
        $consent = $this->createConsent('newsletter-optin', 'Send me the newsletter');

        $this->dispatch(new ConsentToggleActiveEvent($consent->getId()), TheliaEvents::CONSENT_TOGGLE_ACTIVE);
        self::assertSame(0, ConsentQuery::create()->findPk($consent->getId())?->getActive());

        $this->dispatch(new ConsentToggleActiveEvent($consent->getId()), TheliaEvents::CONSENT_TOGGLE_ACTIVE);
        self::assertSame(1, ConsentQuery::create()->findPk($consent->getId())?->getActive());
    }

    public function testUpdatePositionMovesAConsentUp(): void
    {
        $first = $this->createConsent('first-consent', 'First');
        $second = $this->createConsent('second-consent', 'Second');

        self::assertLessThan($second->getPosition(), $first->getPosition());

        $event = new UpdatePositionEvent(
            $second->getId(),
            UpdatePositionEvent::POSITION_UP,
        );

        $this->dispatch($event, TheliaEvents::CONSENT_UPDATE_POSITION);

        self::assertLessThan(
            ConsentQuery::create()->findPk($first->getId())?->getPosition(),
            ConsentQuery::create()->findPk($second->getId())?->getPosition(),
        );
    }

    /**
     * The wording of a consent is written in one language at a time, and the language of
     * the back office is not necessarily the one of the shop. Without a row in the shop
     * language, I18n forges a "DEFAULT TITLE" placeholder — which the buyer would be
     * asked to tick, and which would be frozen on their order as the proof.
     */
    public function testCreatingAConsentInAnotherLanguageAlsoFillsTheShopLanguage(): void
    {
        $event = (new ConsentCreateEvent())
            ->setCode('newsletter-optin')
            ->setLocale('fr_FR')
            ->setTitle('Je souhaite recevoir des offres')
            ->setDescription('Vous pouvez vous désinscrire à tout moment.')
            ->setMandatory(0)
            ->setActive(1);

        $this->dispatch($event, TheliaEvents::CONSENT_CREATE);

        $consent = $event->getConsent();
        self::assertNotNull($consent);

        $shopWording = ConsentI18nQuery::create()
            ->filterById($consent->getId())
            ->filterByLocale('en_US')
            ->findOne();

        self::assertNotNull($shopWording, 'The shop language must never be left without a wording.');
        self::assertSame('Je souhaite recevoir des offres', $shopWording->getTitle());
        self::assertSame('Vous pouvez vous désinscrire à tout moment.', $shopWording->getDescription());
    }

    public function testUpdatingAConsentInAnotherLanguageFillsTheShopLanguageWhenItIsMissing(): void
    {
        $consent = (new Consent())
            ->setCode('newsletter-optin')
            ->setMandatory(0)
            ->setActive(1)
            ->setLocale('fr_FR')
            ->setTitle('Ancien libellé');
        $consent->save($this->getPropelConnection());

        $this->dispatch(
            (new ConsentUpdateEvent($consent->getId()))
                ->setLocale('fr_FR')
                ->setTitle('Nouveau libellé')
                ->setDescription(null)
                ->setMandatory(0)
                ->setActive(1),
            TheliaEvents::CONSENT_UPDATE,
        );

        $shopWording = ConsentI18nQuery::create()
            ->filterById($consent->getId())
            ->filterByLocale('en_US')
            ->findOne();

        self::assertNotNull($shopWording);
        self::assertSame('Nouveau libellé', $shopWording->getTitle());
    }

    public function testUpdatingAConsentLeavesAWordingTheShopLanguageAlreadyHas(): void
    {
        $consent = $this->createConsent('newsletter-optin', 'The English wording');

        $this->dispatch(
            (new ConsentUpdateEvent($consent->getId()))
                ->setLocale('fr_FR')
                ->setTitle('Le libellé français')
                ->setDescription(null)
                ->setMandatory(0)
                ->setActive(1),
            TheliaEvents::CONSENT_UPDATE,
        );

        self::assertSame(
            'The English wording',
            ConsentI18nQuery::create()->filterById($consent->getId())->filterByLocale('en_US')->findOne()?->getTitle(),
            'Filling in the shop language is a last resort, not a translation that overwrites one.',
        );
    }

    private function termsAndConditionsConsent(): Consent
    {
        return ConsentQuery::create()->findOneByCode(Consent::CODE_TERMS_AND_CONDITIONS)
            ?? throw new \RuntimeException('The seeded terms and conditions consent is missing — run bin/test-prepare.');
    }

    private function createConsent(string $code, string $title): Consent
    {
        $consent = (new Consent())
            ->setCode($code)
            ->setMandatory(0)
            ->setActive(1)
            ->setLocale('en_US')
            ->setTitle($title);
        $consent->save($this->getPropelConnection());

        return $consent;
    }
}

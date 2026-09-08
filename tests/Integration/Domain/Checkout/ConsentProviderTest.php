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

namespace Thelia\Tests\Integration\Domain\Checkout;

use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Model\Consent;
use Thelia\Test\IntegrationTestCase;

/**
 * The wording a buyer is shown, and the one frozen on their order as the proof.
 *
 * The one string it may never be is the "DEFAULT TITLE" placeholder I18n forges when it
 * finds no translation: a box saying that means nothing to the buyer, and a row saying
 * that proves nothing to the merchant.
 */
final class ConsentProviderTest extends IntegrationTestCase
{
    public function testTheAskedLanguageComesFirst(): void
    {
        $consent = $this->createConsent('newsletter-optin', ['en_US' => 'Send me the newsletter', 'fr_FR' => 'Envoyez-moi la lettre']);

        self::assertSame('Envoyez-moi la lettre', $this->provider()->title($consent, 'fr_FR'));
    }

    public function testAnUntranslatedLanguageFallsBackToAWordingThatWasWritten(): void
    {
        $consent = $this->createConsent('newsletter-optin', ['fr_FR' => 'Envoyez-moi la lettre']);

        self::assertSame(
            'Envoyez-moi la lettre',
            $this->provider()->title($consent, 'it_IT'),
            'A wording written in another language beats a forged placeholder.',
        );
    }

    public function testAConsentWithNoWordingAtAllFallsBackToItsCode(): void
    {
        $consent = $this->createConsent('newsletter-optin', []);

        self::assertSame('newsletter-optin', $this->provider()->title($consent, 'en_US'));
    }

    public function testTheDescriptionIsLeftEmptyRatherThanForged(): void
    {
        $consent = $this->createConsent('newsletter-optin', ['en_US' => 'Send me the newsletter']);

        self::assertSame('', $this->provider()->description($consent, 'it_IT'));
    }

    private function provider(): ConsentProvider
    {
        return $this->getService(ConsentProvider::class);
    }

    /**
     * @param array<string, string> $wordings the title written in each locale
     */
    private function createConsent(string $code, array $wordings): Consent
    {
        $consent = (new Consent())
            ->setCode($code)
            ->setMandatory(0)
            ->setActive(1);

        foreach ($wordings as $locale => $title) {
            $consent->setLocale($locale)->setTitle($title);
        }

        $consent->save($this->getPropelConnection());

        return $consent;
    }
}

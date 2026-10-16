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

namespace Thelia\Tests\Integration\Mailer;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Email;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Order\EventListener\SendShippingEmailListener;
use Thelia\Mailer\MailerFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What the shipping e-mail template of the installed mail theme does with the values
 * the core passes: the customer reads the carrier, the number and a working link, and
 * an order shipped without a number still gets a mail that makes sense.
 *
 * {@see \Thelia\Tests\Integration\Domain\Order\SendShippingEmailListenerTest} pins the
 * core's half of the contract; this pins the template's half.
 */
final class OrderShippedTemplateTest extends IntegrationTestCase
{
    public function testTheMailShowsTheCarrierTheNumberAndTheLink(): void
    {
        $this->skipUnlessTheInstalledThemeCarriesTheTemplate();

        $email = $this->render([
            'order_ref' => 'ORD000123',
            'carrier' => 'Colissimo',
            'delivery_ref' => '6A12345678901',
            'tracking_url' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=6A12345678901',
        ], 'en_US');

        self::assertStringContainsString('ORD000123', (string) $email->getSubject());
        $html = (string) $email->getHtmlBody();
        self::assertStringContainsString('Colissimo', $html);
        self::assertStringContainsString('6A12345678901', $html);
        self::assertStringContainsString('href="https://www.laposte.fr/outils/suivre-vos-envois?code=6A12345678901"', $html);
        self::assertStringContainsString('https://www.laposte.fr/outils/suivre-vos-envois?code=6A12345678901', (string) $email->getTextBody());
    }

    public function testWithoutTrackingNumberTheMailAnnouncesTheShipmentWithoutLink(): void
    {
        $this->skipUnlessTheInstalledThemeCarriesTheTemplate();

        $email = $this->render(['order_ref' => 'ORD000123', 'carrier' => 'Colissimo', 'delivery_ref' => null, 'tracking_url' => null], 'en_US');

        self::assertStringContainsString('ORD000123', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('Tracking number', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('Track my parcel', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('Track your parcel', (string) $email->getTextBody());
    }

    public function testTheMailIsWrittenInTheLanguageOfTheCustomer(): void
    {
        $this->skipUnlessTheInstalledThemeCarriesTheTemplate();

        $email = $this->render(['order_ref' => 'ORD000123', 'carrier' => 'Colissimo', 'delivery_ref' => '6A1', 'tracking_url' => 'https://carrier.example/6A1'], 'fr_FR');

        self::assertStringContainsString('Votre commande ORD000123 a été expédiée', (string) $email->getSubject());
        self::assertStringContainsString('Suivre mon colis', (string) $email->getHtmlBody());
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(array $parameters, string $locale): Email
    {
        $mailerFactory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            new NullTransport(),
        );

        return $mailerFactory->createEmailMessage(
            SendShippingEmailListener::MESSAGE_CODE,
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            $parameters,
            $locale,
        );
    }

    /**
     * The template is a package of its own (thelia/email-default-template) and this
     * checkout may hold a version older than the shipping e-mail: an older package
     * skips rather than fails.
     */
    private function skipUnlessTheInstalledThemeCarriesTheTemplate(): void
    {
        $templatePath = $this->getService(TemplateHelperInterface::class)
            ->getActiveMailTemplate()
            ->getAbsolutePath().DS.'order_shipped.html.twig';

        if (!is_file($templatePath)) {
            self::markTestSkipped('The installed mail template has no shipping e-mail yet.');
        }
    }
}

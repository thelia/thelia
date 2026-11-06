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
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Reporting\Mailing\ReportMailer;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\MessageQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The report e-mail seeded with the shop carries a body of its own: a mail theme that has
 * no template for it (older than the message, or the published theme of a CI) still
 * gives a mail, rendered from that body with the figures of the report.
 */
final class ReportMailStoredBodyTest extends IntegrationTestCase
{
    public function testWithoutATemplateInTheThemeTheStoredBodyIsRendered(): void
    {
        $message = MessageQuery::create()->findOneByName(ReportMailer::MESSAGE_CODE);
        $message->setHtmlTemplateFileName('no_such_template.html')->setTextTemplateFileName('no_such_template.txt')->save();

        foreach (['en_US', 'fr_FR', 'de_DE'] as $locale) {
            $email = $this->render($locale, ['download_url' => 'https://shop.example/report-download/a.csv?name=x.csv&_hash=h', 'download_expires_at' => '07/11/2026']);

            self::assertStringContainsString('Weekly sales', (string) $email->getSubject(), $locale);
            $html = (string) $email->getHtmlBody();
            self::assertStringContainsString('Revenue incl. tax', $html, $locale);
            self::assertStringContainsString('3 512,40', $html, $locale);
            self::assertStringContainsString('href="https://shop.example/report-download/a.csv?name=x.csv&amp;_hash=h"', $html, $locale);
            self::assertStringContainsString('07/11/2026', $html, $locale);
            self::assertStringContainsString('https://shop.example/report-download/a.csv?name=x.csv&_hash=h', (string) $email->getTextBody(), $locale);
        }

        $empty = $this->render('en_US', ['is_empty' => true, 'summary' => []]);
        self::assertStringContainsString('Nothing to report for this period', (string) $empty->getHtmlBody());
        self::assertStringNotContainsString('3 512,40', (string) $empty->getHtmlBody());

        $attached = $this->render('fr_FR', ['attachment_name' => 'weekly.csv']);
        self::assertStringContainsString('Le fichier complet est joint : weekly.csv', (string) $attached->getHtmlBody());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function render(string $locale, array $overrides): \Symfony\Component\Mime\Email
    {
        $factory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            new NullTransport(),
        );

        return $factory->createEmailMessage(
            ReportMailer::MESSAGE_CODE,
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            $overrides + [
                'report_title' => 'Weekly sales',
                'export_title' => 'Orders',
                'period_start' => '27/10/2026',
                'period_end' => '02/11/2026',
                'is_empty' => false,
                'summary' => [['label' => 'Orders', 'value' => '42'], ['label' => 'Revenue incl. tax', 'value' => '3 512,40 €']],
                'attachment_name' => null,
                'download_url' => null,
                'download_expires_at' => null,
            ],
            $locale,
        );
    }
}

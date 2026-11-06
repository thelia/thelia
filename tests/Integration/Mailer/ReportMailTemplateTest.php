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
use Thelia\Domain\Reporting\Mailing\ReportMailer;
use Thelia\Mailer\MailerFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What the report e-mail template of the installed mail theme does with the parameters the
 * core passes: the title, the period, the figures, and the file, as an attachment or as a
 * link, or the statement that the period is empty.
 *
 * {@see \Thelia\Tests\Integration\Domain\Reporting\Mailing\ReportMailerTest} pins the
 * core's half of the contract; this pins the template's half.
 */
final class ReportMailTemplateTest extends IntegrationTestCase
{
    private const array SUMMARY = [
        ['label' => 'Orders', 'value' => '42'],
        ['label' => 'Revenue incl. tax', 'value' => '3 512,40 €'],
        ['label' => 'Average order value', 'value' => '83,63 €'],
    ];

    public function testAnAttachedReportShowsTheFiguresAndNamesTheFile(): void
    {
        $this->skipUnlessTheInstalledThemeCarriesTheTemplate();

        $email = $this->render($this->parameters(['attachment_name' => 'weekly-sales-2026-10-27-2026-11-02.csv']), 'en_US');

        self::assertStringContainsString('Weekly sales', (string) $email->getSubject());
        self::assertStringContainsString('27/10/2026', (string) $email->getSubject());
        self::assertStringContainsString('02/11/2026', (string) $email->getSubject());
        $html = (string) $email->getHtmlBody();
        self::assertStringContainsString('Weekly sales', $html);
        self::assertStringContainsString('Revenue incl. tax', $html);
        self::assertStringContainsString('3 512,40', $html);
        self::assertStringContainsString('83,63', $html);
        self::assertStringContainsString('weekly-sales-2026-10-27-2026-11-02.csv', $html.(string) $email->getTextBody());
        self::assertStringNotContainsString('/report-download/', $html);
    }

    public function testALinkedReportCarriesItsLinkAndItsExpiry(): void
    {
        $this->skipUnlessTheInstalledThemeCarriesTheTemplate();

        $url = 'https://shop.example/report-download/0123456789abcdef0123456789abcdef.csv?name=weekly-sales.csv&_expiration=1794065231&_hash=abc';
        $email = $this->render($this->parameters(['download_url' => $url, 'download_expires_at' => '07/11/2026']), 'en_US');

        self::assertStringContainsString('/report-download/0123456789abcdef0123456789abcdef.csv', (string) $email->getHtmlBody());
        self::assertStringContainsString($url, (string) $email->getTextBody());
        self::assertStringContainsString('07/11/2026', (string) $email->getHtmlBody());
    }

    public function testAnEmptyPeriodSaysSoWithoutFiguresNorFile(): void
    {
        $this->skipUnlessTheInstalledThemeCarriesTheTemplate();

        $email = $this->render($this->parameters(['is_empty' => true, 'summary' => []]), 'en_US');

        self::assertStringContainsString('Weekly sales', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('3 512,40', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('/report-download/', (string) $email->getHtmlBody());
    }

    public function testTheSubjectIsWrittenInTheLanguageOfTheMailing(): void
    {
        $this->skipUnlessTheInstalledThemeCarriesTheTemplate();

        $email = $this->render($this->parameters([]), 'fr_FR');

        self::assertSame('Weekly sales : du 27/10/2026 au 02/11/2026', $email->getSubject());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function parameters(array $overrides): array
    {
        return $overrides + [
            'report_title' => 'Weekly sales',
            'export_title' => 'Orders',
            'period_start' => '27/10/2026',
            'period_end' => '02/11/2026',
            'is_empty' => false,
            'summary' => self::SUMMARY,
            'attachment_name' => null,
            'download_url' => null,
            'download_expires_at' => null,
        ];
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
            ReportMailer::MESSAGE_CODE,
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            $parameters,
            $locale,
        );
    }

    /**
     * The template is a package of its own (thelia/email-default-template) and this
     * checkout may hold a version older than the report e-mail: an older package skips
     * rather than fails.
     */
    private function skipUnlessTheInstalledThemeCarriesTheTemplate(): void
    {
        $templatePath = $this->getService(TemplateHelperInterface::class)
            ->getActiveMailTemplate()
            ->getAbsolutePath().DS.'report_mail.html.twig';

        if (!is_file($templatePath)) {
            self::markTestSkipped('The installed mail template has no report e-mail yet.');
        }
    }
}

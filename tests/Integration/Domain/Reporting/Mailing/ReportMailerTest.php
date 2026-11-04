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

namespace Thelia\Tests\Integration\Domain\Reporting\Mailing;

use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\Reporting\Mailing\ReportDeliveryMode;
use Thelia\Domain\Reporting\Mailing\ReportDownloadLinkGenerator;
use Thelia\Domain\Reporting\Mailing\ReportMailer;
use Thelia\Domain\Reporting\Mailing\ReportMailingSettings;
use Thelia\Domain\Reporting\Mailing\ReportMailStorage;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderStatus;

final class ReportMailerTest extends ReportMailingTestCase
{
    public function testTheFileIsAttachedAndTheSummaryTravelsInTheParameters(): void
    {
        $this->orderYesterday();
        $this->orderYesterday();
        $mailing = $this->mailing(['one@example.com', 'two@example.com']);

        $result = $this->mailer()->send($mailing, $this->yesterday());

        self::assertSame(ReportDeliveryMode::Attached, $result->delivery);
        self::assertFalse($result->empty);
        self::assertSame(['one@example.com', 'two@example.com'], $result->delivered);
        self::assertSame([], $result->failed);
        self::assertEmailCount(2);

        $email = $this->sentEmail(0);
        self::assertCount(1, $email->getAttachments());
        $attachment = $email->getAttachments()[0];
        self::assertSame(\sprintf('weekly-sales-%s-%s.csv', (new \DateTimeImmutable('yesterday'))->format('Y-m-d'), (new \DateTimeImmutable('yesterday'))->format('Y-m-d')), $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
        self::assertStringContainsString('text/csv', $attachment->getMediaType().'/'.$attachment->getMediaSubtype());
        self::assertStringContainsString('RPT-', $this->mailerFactory()->attachedBodies[0], 'the file was read when the mail went out');

        $parameters = $this->renderedWith()[0];
        self::assertSame('Weekly sales', $parameters['report_title']);
        self::assertFalse($parameters['is_empty']);
        self::assertNull($parameters['download_url']);
        self::assertNull($parameters['download_expires_at']);
        self::assertStringEndsWith('.csv', $parameters['attachment_name']);
        self::assertSame(['Orders', 'Paid orders', 'Revenue incl. tax', 'Average order value'], array_column($parameters['summary'], 'label'));
        self::assertSame('2', $parameters['summary'][0]['value']);
        self::assertSame('2', $parameters['summary'][1]['value']);
        self::assertStringContainsString('40', $parameters['summary'][2]['value'], 'two orders of 2 x 10');
        self::assertStringContainsString('20', $parameters['summary'][3]['value']);
    }

    public function testTheRevenueCountsThePaidOrdersOnly(): void
    {
        $this->orderYesterday();
        $this->orderYesterday(1.0, 5, OrderStatus::CODE_CANCELED);
        $this->orderYesterday(1.0, 7, OrderStatus::CODE_NOT_PAID);

        $this->mailer()->send($this->mailing(), $this->yesterday());

        $summary = $this->renderedWith()[0]['summary'];
        self::assertSame('3', $summary[0]['value'], 'every order of the period');
        self::assertSame('1', $summary[1]['value']);
        self::assertStringContainsString('20', $summary[2]['value']);
        self::assertStringNotContainsString('70', $summary[2]['value']);
        self::assertStringContainsString('20', $summary[3]['value'], 'the paid order alone');
    }

    /**
     * Two orders worth the same money: 20 in the default currency, and 40 in a currency
     * that is worth 2 units of it. Summed as they stand they would make 60.
     */
    public function testTheRevenueBringsEveryOrderBackToTheDefaultCurrency(): void
    {
        $this->orderYesterday(1.0, 2);
        $this->orderYesterday(2.0, 4);

        $this->mailer()->send($this->mailing(), $this->yesterday());

        $summary = $this->renderedWith()[0]['summary'];
        self::assertSame('2', $summary[0]['value']);
        self::assertStringContainsString('40', $summary[2]['value']);
        self::assertStringNotContainsString('60', $summary[2]['value']);
        self::assertStringContainsString('20', $summary[3]['value']);
    }

    public function testTheSummaryIsWrittenInTheLanguageOfTheMailing(): void
    {
        $this->orderYesterday();

        $this->mailer()->send($this->mailing(locale: 'fr_FR'), $this->yesterday());

        self::assertSame(['Commandes', 'Commandes payées', 'Chiffre d\'affaires TTC', 'Panier moyen'], array_column($this->renderedWith()[0]['summary'], 'label'));
        self::assertSame((new \DateTimeImmutable('yesterday'))->format('d/m/Y'), $this->renderedWith()[0]['period_start'], 'short, in the language of the mailing');
    }

    public function testAPeriodWithoutDataGetsAMailThatSaysSoAndCarriesNoFile(): void
    {
        $mailing = $this->mailing();

        $result = $this->mailer()->send($mailing, $this->yesterday());

        self::assertTrue($result->empty);
        self::assertSame(ReportDeliveryMode::None, $result->delivery);
        self::assertSame(['one@example.com'], $result->delivered);
        self::assertEmailCount(1);
        self::assertCount(0, $this->sentEmail(0)->getAttachments());

        $parameters = $this->renderedWith()[0];
        self::assertTrue($parameters['is_empty']);
        self::assertSame([], $parameters['summary']);
        self::assertNull($parameters['attachment_name']);
        self::assertNull($parameters['download_url']);
    }

    public function testABigFileStaysOnTheServerBehindASignedLink(): void
    {
        $this->orderYesterday();
        ConfigQuery::write(ReportMailingSettings::ATTACHMENT_MAX_MB, '0');
        ConfigQuery::write('url_site', 'https://shop.example');
        ConfigQuery::resetCache();

        $result = $this->mailer()->send($this->mailing(), $this->yesterday());

        self::assertSame(ReportDeliveryMode::Linked, $result->delivery);
        self::assertCount(0, $this->sentEmail(0)->getAttachments());

        $parameters = $this->renderedWith()[0];
        self::assertNull($parameters['attachment_name']);
        self::assertMatchesRegularExpression('#^https://shop\.example/report-download/[a-f0-9]{32}\.csv\?#', $parameters['download_url']);
        parse_str((string) parse_url($parameters['download_url'], \PHP_URL_QUERY), $query);
        self::assertMatchesRegularExpression('/^weekly-sales-[0-9-]+\.csv$/', $query['name']);
        self::assertArrayHasKey('_hash', $query);
        self::assertArrayHasKey('_expiration', $query);
        self::assertSame((new \DateTimeImmutable('+3 days'))->format('n/j/y'), $parameters['download_expires_at']);

        $files = glob(ReportMailStorage::directory().'/*.csv');
        self::assertCount(1, $files, 'the file waits for the download');
        self::assertStringContainsString(basename($files[0]), $parameters['download_url']);
    }

    public function testTheLinkLifetimeFollowsTheConfiguration(): void
    {
        $this->orderYesterday();
        ConfigQuery::write(ReportMailingSettings::ATTACHMENT_MAX_MB, '0');
        ConfigQuery::write(ReportMailingSettings::LINK_LIFETIME_DAYS, '10');
        ConfigQuery::write('url_site', 'https://shop.example');
        ConfigQuery::resetCache();

        $this->mailer()->send($this->mailing(), $this->yesterday());

        self::assertSame((new \DateTimeImmutable('+10 days'))->format('n/j/y'), $this->renderedWith()[0]['download_expires_at']);
    }

    public function testTheExportFileIsNotLeftInTheExportFolderOnceSent(): void
    {
        $this->orderYesterday();
        $before = glob(THELIA_CACHE_DIR.'export/*') ?: [];

        $this->mailer()->send($this->mailing(), $this->yesterday());

        self::assertSame($before, glob(THELIA_CACHE_DIR.'export/*') ?: []);
    }

    public function testAnInvalidRecipientIsSetAsideAndTheOthersGetTheirMail(): void
    {
        $this->orderYesterday();

        $result = $this->mailer()->send($this->mailing(['not an address', 'good@example.com']), $this->yesterday());

        self::assertSame(['good@example.com'], $result->delivered);
        self::assertSame(['not an address'], $result->rejected);
        self::assertSame([], $result->failed, 'an invalid address is not a failure to try again');
        self::assertEmailCount(1);
    }

    public function testOnlyTheRecipientsGivenGetTheMail(): void
    {
        $this->orderYesterday();

        $result = $this->mailer()->send($this->mailing(['one@example.com', 'two@example.com']), $this->yesterday(), ['two@example.com']);

        self::assertSame(['two@example.com'], $result->delivered);
        self::assertEmailCount(1);
    }

    public function testEachRecipientServedIsToldToTheCallerAsItIsServed(): void
    {
        $this->orderYesterday();
        $served = [];

        $this->mailer()->send(
            $this->mailing(['one@example.com', 'not an address', 'two@example.com']),
            $this->yesterday(),
            null,
            static function (string $recipient) use (&$served): void {
                $served[] = [$recipient, \count(self::getMailerMessages())];
            },
        );

        // The mail of a recipient has gone out by the time the caller is told, and an invalid address is served too.
        self::assertSame([['one@example.com', 1], ['not an address', 1], ['two@example.com', 2]], $served);
    }

    public function testTheLinkedFileIsRemovedWhenNoLinkCanBeMade(): void
    {
        $this->orderYesterday();
        ConfigQuery::write(ReportMailingSettings::ATTACHMENT_MAX_MB, '0');
        ConfigQuery::resetCache();
        $before = $this->filesLeftBehind();

        try {
            $this->mailer($this->linkGeneratorThatFails())->send($this->mailing(), $this->yesterday());
            self::fail('The failure of the link must go on.');
        } catch (\LogicException $failure) {
            self::assertSame('No route.', $failure->getMessage());
        }

        self::assertSame($before, $this->filesLeftBehind(), 'neither the export file nor the stored file stays');
        self::assertEmailCount(0);
    }

    public function testTheExportFileIsRemovedWhenItCannotBeStoredForTheLink(): void
    {
        $this->orderYesterday();
        ConfigQuery::write(ReportMailingSettings::ATTACHMENT_MAX_MB, '0');
        ConfigQuery::resetCache();
        $before = $this->filesLeftBehind();
        // A file where the folder of the stored files should be: the file cannot be moved there.
        (new Filesystem())->remove(ReportMailStorage::directory());
        file_put_contents(ReportMailStorage::directory(), 'not a folder');

        try {
            $this->mailer()->send($this->mailing(), $this->yesterday());
            self::fail('The failure of the storage must go on.');
        } catch (\Throwable) {
            // expected
        }

        self::assertSame($before, array_values(array_diff($this->filesLeftBehind(), [ReportMailStorage::directory()])), 'the export file does not stay');
        self::assertEmailCount(0);
    }

    public function testTheFunnelReportCarriesItsOwnSummary(): void
    {
        $result = $this->mailer()->send($this->mailing(exportRef: 'thelia.export.conversion_funnel', period: 'last_7_days'), $this->yesterday());

        self::assertFalse($result->empty);
        self::assertSame(['Carts created', 'Orders created', 'Orders paid'], \array_slice(array_column($this->renderedWith()[0]['summary'], 'label'), 0, 3));
    }

    private function mailer(?ReportDownloadLinkGenerator $linkGenerator = null): ReportMailer
    {
        return new ReportMailer(
            $this->getService(ExportHandler::class),
            $this->getService(SerializerManager::class),
            $this->mailerFactory(),
            new ReportMailingSettings(),
            $linkGenerator ?? $this->getService(ReportDownloadLinkGenerator::class),
        );
    }

    private function linkGeneratorThatFails(): ReportDownloadLinkGenerator
    {
        $urlGenerator = new class implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                throw new \LogicException('No route.');
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }
        };

        return new ReportDownloadLinkGenerator(new UriSigner('secret'), $urlGenerator, new MockClock(), new ReportMailingSettings());
    }

    /**
     * @return list<string> the files of the export folder and of the folder of the stored files
     */
    private function filesLeftBehind(): array
    {
        return array_values(array_merge(glob(THELIA_CACHE_DIR.'export/*') ?: [], glob(ReportMailStorage::directory().'/*') ?: []));
    }
}

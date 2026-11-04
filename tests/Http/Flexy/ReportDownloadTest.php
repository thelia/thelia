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

namespace Thelia\Tests\Http\Flexy;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\File\FolderFile;
use Thelia\Domain\Reporting\Mailing\ReportDownloadLinkGenerator;
use Thelia\Domain\Reporting\Mailing\ReportMailingSettings;
use Thelia\Domain\Reporting\Mailing\ReportMailStorage;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The file of a report too big to be attached is served to whoever holds the signed link
 * of the e-mail, until it expires, and to nobody else: the path, the name the file is
 * saved under and the expiry are all signed, and nothing outside the report folder is
 * ever read.
 */
final class ReportDownloadTest extends WebIntegrationTestCase
{
    private const string LIMITER = 'limiter.report_mail_download_per_client';

    private const string CONTENT = "ref;total\nRPT-1;20.00\n";

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        self::getContainer()->get(self::LIMITER)->create('127.0.0.1')->reset();

        FolderFile::ensureFolder(ReportMailStorage::directory());
        $path = ReportMailStorage::directory().'/'.bin2hex(random_bytes(16)).'.csv';
        file_put_contents($path, self::CONTENT);
        $this->file = basename($path);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(ReportMailStorage::directory());

        parent::tearDown();
    }

    public function testAValidLinkServesTheFileAsADownloadThatIsNeverCached(): void
    {
        $this->client->request('GET', $this->link()->url);
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::CONTENT, $this->client->getInternalResponse()->getContent());
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('weekly-sales-2026-10-27-2026-11-02.csv', (string) $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function testAnExpiredLinkAnswersForbidden(): void
    {
        $this->client->request('GET', $this->link(new MockClock('-10 days'))->url);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAnAlteredSignatureAnswersForbidden(): void
    {
        $this->client->request('GET', preg_replace('/_hash=(.)/', '_hash=x$1', $this->link()->url) ?? '');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testALinkWithoutSignatureAnswersForbidden(): void
    {
        $this->client->request('GET', '/report-download/'.$this->file);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTheSignatureOfAFileDoesNotOpenAnother(): void
    {
        $other = bin2hex(random_bytes(16)).'.csv';
        file_put_contents(ReportMailStorage::directory().'/'.$other, "another report\n");

        $this->client->request('GET', str_replace($this->file, $other, $this->link()->url));

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTheNameTheFileIsSavedUnderIsSigned(): void
    {
        $this->client->request('GET', str_replace('name=weekly-sales', 'name=evil', $this->link()->url));

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testASignedLinkToAFileThatIsGoneAnswersNotFound(): void
    {
        unlink(ReportMailStorage::directory().'/'.$this->file);

        $this->client->request('GET', $this->link()->url);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsOutOfTheFolder(): iterable
    {
        yield 'parent folder' => ['/report-download/..%2F..%2F.env'];
        yield 'dots' => ['/report-download/../../composer.json'];
        yield 'a name that is not a stored file' => ['/report-download/composer.json'];
        yield 'a stored name in capitals' => ['/report-download/ABCDEFABCDEFABCDEFABCDEFABCDEFAB.csv'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pathsOutOfTheFolder')]
    public function testNothingOutsideTheReportFolderCanBeAsked(string $path): void
    {
        $this->client->request('GET', $path);

        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
        self::assertStringNotContainsString('DATABASE', (string) $this->client->getInternalResponse()->getContent());
    }

    /**
     * The signature proves the link came from the shop, not that its path is safe: even a
     * signed path that climbs out of the report folder opens nothing.
     */
    public function testASignedPathThatClimbsOutOfTheFolderOpensNothing(): void
    {
        $signer = $this->getService(UriSigner::class);
        $site = $this->getService(UrlGeneratorInterface::class)->generate(ReportDownloadLinkGenerator::ROUTE, ['file' => $this->file], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach (['..%2F..%2Fcomposer.json', '..%2F..%2F..%2Fvar%2Freport-mail%2F'.$this->file, '../../composer.json'] as $climb) {
            $this->client->request('GET', $signer->sign(str_replace($this->file, $climb, $site).'?name=x.csv', new \DateTimeImmutable('+1 day')));

            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $climb);
            self::assertStringNotContainsString('thelia/thelia', (string) $this->client->getInternalResponse()->getContent());
        }
    }

    public function testALinkThroughASymbolicLinkToAnotherFileIsRefused(): void
    {
        $name = bin2hex(random_bytes(16)).'.csv';
        symlink(THELIA_ROOT.'composer.json', ReportMailStorage::directory().'/'.$name);
        $link = $this->linkFor($name);

        $this->client->request('GET', $link->url);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    private function link(?ClockInterface $clock = null): \Thelia\Domain\Reporting\Mailing\ReportDownloadLink
    {
        return $this->linkFor($this->file, $clock);
    }

    private function linkFor(string $file, ?ClockInterface $clock = null): \Thelia\Domain\Reporting\Mailing\ReportDownloadLink
    {
        // With no `url_site` the address is the one the router knows, which is the one of the client.
        return (new ReportDownloadLinkGenerator(
            $this->getService(UriSigner::class),
            $this->getService(UrlGeneratorInterface::class),
            $clock ?? new MockClock(),
            new ReportMailingSettings(),
        ))->generate($file, 'weekly-sales-2026-10-27-2026-11-02.csv');
    }
}

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
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Domain\Reporting\Mailing\ReportDownloadLinkGenerator;
use Thelia\Domain\Reporting\Mailing\ReportMailingSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * A shop installed in a subdirectory has it in `url_site` (Thelia\Tools\URL::absoluteUrl()
 * compensates for it for the same reason). The link built while a request is served (the
 * "Send now" of the back office) must be the same as the one built from the command line,
 * where the router knows no base path: the subdirectory appears once.
 */
final class ReportDownloadLinkGeneratorTest extends IntegrationTestCase
{
    private ?string $previousBaseUrl = null;

    protected function tearDown(): void
    {
        if (null !== $this->previousBaseUrl) {
            $this->router()->getContext()->setBaseUrl($this->previousBaseUrl);
        }

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testALinkBuiltDuringARequestInASubdirectoryNamesTheSubdirectoryOnce(): void
    {
        ConfigQuery::write('url_site', 'https://shop.example/boutique');
        ConfigQuery::resetCache();

        $router = $this->router();
        $this->previousBaseUrl = $router->getContext()->getBaseUrl();

        $router->getContext()->setBaseUrl('');
        $fromTheCommandLine = $this->generator()->generate(str_repeat('a', 32).'.csv', 'report.csv')->url;

        // What RouterListener sets from a request served by /boutique/index.php behind rewriting.
        $router->getContext()->setBaseUrl('/boutique');
        $fromARequest = $this->generator()->generate(str_repeat('a', 32).'.csv', 'report.csv')->url;

        self::assertStringStartsWith('https://shop.example/boutique/report-download/', $fromTheCommandLine);
        self::assertStringStartsWith('https://shop.example/boutique/report-download/', $fromARequest, 'the subdirectory is not doubled');
    }

    private function router(): UrlGeneratorInterface
    {
        $router = $this->getService(UrlGeneratorInterface::class);
        self::assertInstanceOf(UrlGeneratorInterface::class, $router);

        return $router;
    }

    private function generator(): ReportDownloadLinkGenerator
    {
        $signer = $this->getService(UriSigner::class);
        self::assertInstanceOf(UriSigner::class, $signer);

        return new ReportDownloadLinkGenerator($signer, $this->router(), new MockClock('2026-11-09 10:00:00'), new ReportMailingSettings());
    }
}

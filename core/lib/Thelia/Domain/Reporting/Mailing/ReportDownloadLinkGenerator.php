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

namespace Thelia\Domain\Reporting\Mailing;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Model\ConfigQuery;

/**
 * Builds the link a recipient downloads a report with when it is too big to be attached.
 *
 * The link is signed with the application secret and carries its expiry, the file name
 * the download is saved under included: whoever holds it gets the file until then, without
 * signing in. The address is the one of the shop (`url_site`) and not the one of a
 * request, as the link is built from a command, where there is none.
 */
final readonly class ReportDownloadLinkGenerator
{
    public const string ROUTE = 'report_mail.download';

    public function __construct(
        private UriSigner $uriSigner,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
        private ReportMailingSettings $settings,
    ) {
    }

    public function generate(string $storedFile, string $downloadName): ReportDownloadLink
    {
        $expiresAt = $this->clock->now()->modify(\sprintf('+%d days', $this->settings->linkLifetimeDays()));

        $siteUrl = rtrim((string) ConfigQuery::read('url_site'), '/');
        $parameters = ['file' => $storedFile];

        $url = '' === $siteUrl
            ? $this->urlGenerator->generate(self::ROUTE, $parameters, UrlGeneratorInterface::ABSOLUTE_URL)
            : $siteUrl.$this->urlGenerator->generate(self::ROUTE, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);

        // Not a route parameter: it travels as a query parameter, signed with the rest.
        $url .= '?name='.rawurlencode($downloadName);

        return new ReportDownloadLink($this->uriSigner->sign($url, $expiresAt), \DateTimeImmutable::createFromInterface($expiresAt));
    }
}

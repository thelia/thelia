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

namespace Thelia\Mailer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\SaleUnitQuery;

/**
 * The address the links of an e-mail are built on.
 *
 * An e-mail is read away from the page that sent it, and some of its links carry a token
 * (choose a password, confirm an account): they must lead to the shop, whatever Host
 * header came with the request that sent the mail.
 *
 * The host of the request is kept when the shop declares it: the host of its address
 * (`url_site`), the domain of a language when `one_domain_foreach_lang` is on, the domain
 * of an active sale unit, or any host once Symfony checks them against
 * `framework.trusted_hosts`. A shop served on several domains thus keeps mailing links
 * to the one the visitor uses. Any other host is replaced by the address the shop is
 * configured with: the domain of the language of the mail, else `url_site`.
 *
 * A shop that declares no address at all leaves nothing to check the host against: its
 * links keep following the request, and a warning names the setting to fill in.
 */
final readonly class MailLinkBaseUrl
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * The address to build the links of a mail written in $locale on, or null to keep the
     * address of the request (no request, or a host the shop declares).
     */
    public function resolve(?Request $request, string $locale): ?string
    {
        // From a command, the links are already built on the configured address.
        if (null === $request) {
            return null;
        }

        // Symfony refuses any other host before the request reaches the shop.
        if ([] !== Request::getTrustedHosts()) {
            return null;
        }

        if ($this->isDeclaredHost(strtolower($request->getHost()))) {
            return null;
        }

        $configured = $this->configuredBaseUrl($locale);

        if (null === $configured) {
            $this->logger->warning(
                'The links of the e-mails follow the host of the request ({host}): set the address of the shop (url_site) so that they always lead to it.',
                ['host' => $request->getHost()],
            );
        }

        return $configured;
    }

    private function isDeclaredHost(string $host): bool
    {
        $urls = [ConfigQuery::getConfiguredShopUrl()];

        if (ConfigQuery::isMultiDomainActivated()) {
            foreach (LangQuery::create()->find() as $lang) {
                $urls[] = $lang->getUrl();
            }
        }

        foreach ($urls as $url) {
            if ($host === self::hostOf((string) $url)) {
                return true;
            }
        }

        // `sale_unit.url` holds the bare host.
        return SaleUnitQuery::create()
            ->filterByActive(true)
            ->filterByUrl($host)
            ->exists();
    }

    private function configuredBaseUrl(string $locale): ?string
    {
        $candidates = [];

        if (ConfigQuery::isMultiDomainActivated()) {
            $candidates[] = (string) LangQuery::create()->findOneByLocale($locale)?->getUrl();
        }

        $candidates[] = (string) ConfigQuery::getConfiguredShopUrl();

        foreach ($candidates as $url) {
            $url = rtrim(trim($url), '/');

            // Links are absolute: an address without a scheme is not one to build them on.
            if (null !== parse_url($url, \PHP_URL_SCHEME) && null !== self::hostOf($url)) {
                return $url;
            }
        }

        return null;
    }

    private static function hostOf(string $url): ?string
    {
        $url = trim($url);

        if ('' === $url) {
            return null;
        }

        $host = parse_url(str_contains($url, '//') ? $url : '//'.$url, \PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? strtolower($host) : null;
    }
}

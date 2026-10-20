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

namespace Thelia\Domain\Webhook;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;
use Thelia\Domain\Webhook\Exception\InvalidWebhookUrlException;

final readonly class WebhookUrlValidator
{
    private const array NON_ROUTABLE_IPV4 = ['192.0.0.0/24', '192.0.2.0/24', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4'];
    private const array INTERNAL_SUFFIXES = ['.localhost', '.local', '.internal', '.lan', '.home', '.corp', '.intranet'];

    public function __construct(
        #[Autowire(param: 'thelia.webhook.allow_insecure_urls')]
        private bool $allowInsecure = false,
    ) {
    }

    /**
     * @throws InvalidWebhookUrlException
     */
    public function validate(string $url): void
    {
        if ('' === $url || 1 === preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new InvalidWebhookUrlException('The address is empty or contains a control character.');
        }

        $parts = parse_url($url);

        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidWebhookUrlException('The address must be an absolute URL.');
        }

        $scheme = strtolower($parts['scheme']);

        if ('https' !== $scheme && !('http' === $scheme && $this->allowInsecure)) {
            throw new InvalidWebhookUrlException('The address must use https.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidWebhookUrlException('The address must not carry credentials.');
        }

        if (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535)) {
            throw new InvalidWebhookUrlException('The port is out of range.');
        }

        $this->assertPublicHost(strtolower($parts['host']));
    }

    private function assertPublicHost(string $host): void
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $this->assertPublicIp(substr($host, 1, -1), true);

            return;
        }

        if (str_ends_with($host, '.')) {
            $host = rtrim($host, '.');
        }

        if ('' === $host || 'localhost' === $host) {
            throw new InvalidWebhookUrlException('The host is not a public address.');
        }

        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                throw new InvalidWebhookUrlException('The host is not a public address.');
            }
        }

        $labels = explode('.', $host);
        $last = end($labels);

        if (1 === preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', $last)) {
            if (false === filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
                throw new InvalidWebhookUrlException('The host is written as a number the system would read as another address.');
            }

            $this->assertPublicIp($host, false);
        }
    }

    private function assertPublicIp(string $ip, bool $expectIpv6): void
    {
        $flag = $expectIpv6 ? \FILTER_FLAG_IPV6 : \FILTER_FLAG_IPV4;

        if (false === filter_var($ip, \FILTER_VALIDATE_IP, $flag)) {
            throw new InvalidWebhookUrlException('The host is not a valid IP address.');
        }

        if ($expectIpv6 && $this->embedsAnIpv4Address($ip)) {
            throw new InvalidWebhookUrlException('The host embeds an IPv4 address.');
        }

        if (false === filter_var($ip, \FILTER_VALIDATE_IP, $flag | \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) || IpUtils::isPrivateIp($ip) || (!$expectIpv6 && IpUtils::checkIp($ip, self::NON_ROUTABLE_IPV4))) {
            throw new InvalidWebhookUrlException('The host is not a public address.');
        }
    }

    private function embedsAnIpv4Address(string $ip): bool
    {
        $packed = inet_pton($ip);

        if (false === $packed) {
            return true;
        }

        $prefix = bin2hex(substr($packed, 0, 12));

        return str_starts_with($prefix, '0064ff9b') || str_starts_with($prefix, '2002');
    }
}

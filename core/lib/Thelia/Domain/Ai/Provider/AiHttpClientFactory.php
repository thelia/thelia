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

namespace Thelia\Domain\Ai\Provider;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Webhook\WebhookUrlValidator;

/**
 * The HTTP client the AI providers are called with: the protections of the webhook client (no private
 * network, no redirection) with the delays of a model, which thinks for a while before its first token and
 * streams for minutes.
 *
 * A connection may reach a private network (a self-hosted Ollama, a server of the LAN) only when its
 * administrator allowed it. Even then the link-local range stays closed: it holds the metadata service of
 * the cloud providers, which hands out credentials.
 *
 * @internal
 */
final class AiHttpClientFactory
{
    public const array LINK_LOCAL_SUBNETS = ['169.254.0.0/16', 'fe80::/10'];

    private ?HttpClientInterface $transport = null;

    /** @var array<string, string> */
    private array $pinnedHosts = [];

    public function __construct(
        #[Autowire(param: 'thelia.ai.http_timeout')]
        private readonly float $inactivityTimeout,
        #[Autowire(param: 'thelia.ai.http_max_duration')]
        private readonly float $maximumDuration,
    ) {
    }

    /**
     * The same factory over another transport, for a test that must not reach the network; the hosts it names
     * are not looked up in the DNS (host => address).
     *
     * @param array<string, string> $pinnedHosts
     */
    public function withTransport(HttpClientInterface $transport, array $pinnedHosts = []): self
    {
        $factory = clone $this;
        $factory->transport = $transport;
        $factory->pinnedHosts = $pinnedHosts;

        return $factory;
    }

    public function create(bool $allowPrivateNetwork): HttpClientInterface
    {
        $refusedSubnets = $allowPrivateNetwork
            ? [...self::LINK_LOCAL_SUBNETS, ...WebhookUrlValidator::NON_ROUTABLE]
            : [...IpUtils::PRIVATE_SUBNETS, ...WebhookUrlValidator::NON_ROUTABLE];

        $options = [
            'max_redirects' => 0,
            'timeout' => $this->inactivityTimeout,
            'max_duration' => $this->maximumDuration,
        ];

        if ([] !== $this->pinnedHosts) {
            $options['resolve'] = $this->pinnedHosts;
        }

        return (new NoPrivateNetworkHttpClient($this->transport ?? HttpClient::create(), $refusedSubnets))->withOptions($options);
    }
}

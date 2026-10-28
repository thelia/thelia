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

namespace Thelia\Domain\Ai\Tool\Mcp;

use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;

/**
 * The hosts the MCP endpoint answers for, the allow-list of its DNS rebinding protection.
 *
 * THELIA_MCP_ALLOWED_HOSTS (comma separated, no port) wins when it holds something. Without it, the hosts are the
 * ones of the shop: the host of `url_site`, the domain of each language when `one_domain_foreach_lang` is on, and
 * the loopback names (`localhost`, `127.0.0.1`, `[::1]`). They are read from the database when a request comes in,
 * never when the container is built: a console command or a fresh install reads nothing.
 *
 * Public for the back office, which tells the administrator whether the host he uses is accepted (hosts()).
 */
final readonly class McpAllowedHosts
{
    private const array LOOPBACK = ['localhost', '127.0.0.1', '[::1]'];

    public function __construct(
        #[Autowire('%env(THELIA_MCP_ALLOWED_HOSTS)%')]
        private string $configured,
    ) {
    }

    /**
     * @param list<string> $allowedOrigins
     */
    public function middlewareFactory(array $allowedOrigins): MiddlewareFactory
    {
        return new MiddlewareFactory($this->hosts(), $allowedOrigins);
    }

    /**
     * @return list<string>
     */
    public function hosts(): array
    {
        $configured = array_values(array_filter(array_map('trim', explode(',', $this->configured)), static fn (string $host): bool => '' !== $host));

        if ([] !== $configured) {
            return $configured;
        }

        $hosts = self::LOOPBACK;
        $urls = [ConfigQuery::getConfiguredShopUrl()];

        if (ConfigQuery::isMultiDomainActivated()) {
            foreach (LangQuery::create()->find() as $lang) {
                $urls[] = $lang->getUrl();
            }
        }

        foreach ($urls as $url) {
            $host = self::hostOf((string) $url);

            if (null !== $host && !\in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }

        return $hosts;
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

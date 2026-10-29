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
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Webhook\Exception\InvalidWebhookUrlException;
use Thelia\Domain\Webhook\WebhookUrlValidator;

/**
 * Checks the base URL of a connection: a public https address, or, when the administrator allowed the
 * private network (a self-hosted server), any http or https address. The HTTP client checks again where
 * the name resolves at the time of the call.
 *
 * The public address is checked by the rules of the webhooks, but plain http to a public host is decided by
 * `thelia.ai.allow_insecure_urls` alone: the flag of the webhooks says nothing about a connection that sends
 * an API key.
 *
 * @internal
 */
final readonly class AiBaseUrlValidator
{
    private WebhookUrlValidator $publicUrlValidator;

    public function __construct(
        WebhookUrlValidator $publicUrlValidator,
        #[Autowire(param: 'thelia.ai.allow_insecure_urls')]
        bool $allowInsecure = false,
    ) {
        $this->publicUrlValidator = $publicUrlValidator->withInsecureUrls($allowInsecure);
    }

    /**
     * @throws InvalidAiArgumentException
     */
    public function validate(string $baseUrl, bool $allowPrivateNetwork): void
    {
        if (!$allowPrivateNetwork) {
            try {
                $this->publicUrlValidator->validate($baseUrl);
            } catch (InvalidWebhookUrlException $refusal) {
                throw new InvalidAiArgumentException('The base URL is not allowed: '.$refusal->getMessage(), 0, $refusal);
            }

            return;
        }

        if (1 === preg_match('/[\x00-\x20\x7f]/', $baseUrl)) {
            throw new InvalidAiArgumentException('The base URL is not allowed: it contains a space or a control character.');
        }

        $parts = parse_url($baseUrl);

        if (false === $parts || !isset($parts['scheme'], $parts['host']) || !\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new InvalidAiArgumentException('The base URL is not allowed: it must be an absolute http or https address.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidAiArgumentException('The base URL is not allowed: it must not carry credentials.');
        }
    }
}

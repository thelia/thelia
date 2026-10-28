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

use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Exception\RetryableExceptionInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;

/**
 * Turns what the library and the HTTP client throw into AiProviderUnavailableException, whose message never
 * holds the API key: a provider may repeat the key it received in its error message.
 *
 * @internal
 */
final readonly class AiProviderErrorTranslator
{
    private const int MESSAGE_LIMIT = 500;

    /**
     * @return AiProviderUnavailableException|null null for a throwable the layer does not own: a bug, to let through
     */
    public function translate(\Throwable $error, AiTarget $target): ?AiProviderUnavailableException
    {
        if (!$error instanceof PlatformException && !$error instanceof HttpClientException && !$error instanceof \JsonException) {
            return null;
        }

        $apiKey = $target->apiKey();
        $message = self::redact($error->getMessage(), $apiKey);

        return new AiProviderUnavailableException(
            $target->connectionCode,
            self::isRetryable($error),
            \sprintf('The AI connection "%s" failed: %s', $target->connectionCode, '' === $message ? $error::class : $message),
            // The chain is dropped when a message of it holds the key.
            self::chainHoldsKey($error, $apiKey) ? null : $error,
        );
    }

    public static function redact(string $message, ?string $apiKey): string
    {
        if (null !== $apiKey && '' !== $apiKey) {
            $message = str_replace($apiKey, '[hidden]', $message);
        }

        $message = (string) preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/=-]{8,}/i', '$1[hidden]', $message);

        return mb_strimwidth($message, 0, self::MESSAGE_LIMIT, '...');
    }

    private static function isRetryable(\Throwable $error): bool
    {
        if ($error instanceof RetryableExceptionInterface) {
            return true;
        }

        // The refusal of the anti-SSRF client is a setting to change, not a failure of the network.
        return $error instanceof TransportExceptionInterface && !($error instanceof TransportException && str_contains($error->getMessage(), 'is blocked'));
    }

    private static function chainHoldsKey(\Throwable $error, ?string $apiKey): bool
    {
        if (null === $apiKey || '' === $apiKey) {
            return false;
        }

        for ($link = $error; null !== $link; $link = $link->getPrevious()) {
            if (str_contains($link->getMessage(), $apiKey)) {
                return true;
            }
        }

        return false;
    }
}

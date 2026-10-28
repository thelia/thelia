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

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Refuses to replay a request.
 *
 * The reader of the streams of the platform (EventSourceHttpClient) follows the rules of server-sent events:
 * when a connection drops it sends the same request again with a `Last-Event-ID` header and carries on. A model
 * provider keeps no memory of an event: it would start a second answer to the same prompt, bill it, and the
 * caller would get the two glued together. With this client a cut stream is an error of the call, which the
 * gateway logs as aborted.
 *
 * @internal
 */
final readonly class AiNoReplayHttpClient implements HttpClientInterface
{
    private const string REPLAY_HEADER = 'last-event-id';

    public function __construct(private HttpClientInterface $client)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        // Headers are a map (`name => value`) or a list of `Name: value` lines.
        foreach ((array) ($options['headers'] ?? []) as $name => $line) {
            $name = \is_int($name) ? strstr((string) $line, ':', true) : $name;

            if (\is_string($name) && self::REPLAY_HEADER === strtolower(trim($name))) {
                throw new TransportException('The connection to the AI provider dropped: the request is not replayed.');
            }
        }

        return $this->client->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new self($this->client->withOptions($options));
    }
}

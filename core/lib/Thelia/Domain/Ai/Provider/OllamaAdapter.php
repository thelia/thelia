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

use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;

/**
 * A self-hosted Ollama server. Its connection usually allows the private network (the server is on the LAN or on
 * the same machine); it has no key unless a reverse proxy asks for one.
 *
 * @internal
 */
final class OllamaAdapter extends AbstractPlatformAdapter
{
    public function supports(AiProvider $provider): bool
    {
        return AiProvider::Ollama === $provider;
    }

    protected function platform(AiTarget $target, #[\SensitiveParameter] ?string $apiKey, string $baseUrl, HttpClientInterface $httpClient): Platform
    {
        return Factory::createPlatform($baseUrl, $apiKey, $httpClient);
    }

    protected function model(AiTarget $target, array $capabilities): Model
    {
        return new Ollama($target->model->name, $capabilities);
    }

    protected function outputLimit(int $tokens): array
    {
        return ['num_predict' => $tokens];
    }

    /**
     * The bridge reads the body of an answer without looking at its status: an error of the server
     * (`{"error": "model 'x' not found"}`) would come out as "response does not contain message".
     */
    protected function assertSuccessful(DeferredResult $deferred, AiTarget $target): void
    {
        $raw = $deferred->getRawResult();

        if (!$raw instanceof RawHttpResult) {
            return;
        }

        $status = $raw->getObject()->getStatusCode();

        if ($status < 400) {
            return;
        }

        $body = json_decode($raw->getObject()->getContent(false), true);
        $reason = AiProviderErrorTranslator::redact(\is_array($body) && \is_string($body['error'] ?? null) ? $body['error'] : '', $target->apiKey());

        throw new AiProviderUnavailableException($target->connectionCode, $status >= 500 || 429 === $status, \sprintf('The AI connection "%s" failed: the server answered HTTP %d%s', $target->connectionCode, $status, '' === $reason ? '.' : ': '.$reason));
    }
}

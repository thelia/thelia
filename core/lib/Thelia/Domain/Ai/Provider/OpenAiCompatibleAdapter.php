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

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Ai\AiProvider;

/**
 * Any server that speaks the chat completions API of OpenAI: Scaleway, OVHcloud AI Endpoints, Albert, the
 * gateway of Thelia, or an address the merchant gives. The base URL ends with `/v1`; the path is
 * `/chat/completions`.
 *
 * @internal
 */
final class OpenAiCompatibleAdapter extends AbstractPlatformAdapter
{
    public const string COMPLETIONS_PATH = '/chat/completions';

    public function supports(AiProvider $provider): bool
    {
        return \in_array($provider, [AiProvider::OpenAiCompatible, AiProvider::Scaleway, AiProvider::OvhCloud, AiProvider::Albert, AiProvider::TheliaAi], true);
    }

    protected function platform(AiTarget $target, #[\SensitiveParameter] ?string $apiKey, string $baseUrl, HttpClientInterface $httpClient): Platform
    {
        return Factory::createPlatform(
            $baseUrl,
            $apiKey,
            $httpClient,
            supportsEmbeddings: false,
            completionsPath: self::COMPLETIONS_PATH,
        );
    }

    protected function model(AiTarget $target, array $capabilities): Model
    {
        return new CompletionsModel($target->model->name, $capabilities);
    }

    /**
     * Some gateways (OpenRouter) put what the call cost in `usage.cost`; that is the price to log, not the catalogue one.
     */
    protected function announcedCost(DeferredResult $deferred): ?string
    {
        $data = $deferred->getRawResult()->getData();
        $cost = \is_array($data['usage'] ?? null) ? ($data['usage']['cost'] ?? null) : null;

        if (!\is_int($cost) && !\is_float($cost) && !(\is_string($cost) && is_numeric($cost))) {
            return null;
        }

        return (float) $cost > 0 ? \sprintf('%.8F', (float) $cost) : null;
    }
}

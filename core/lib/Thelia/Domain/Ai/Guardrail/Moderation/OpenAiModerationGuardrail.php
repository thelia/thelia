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

namespace Thelia\Domain\Ai\Guardrail\Moderation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;

/**
 * The moderation of OpenAI (`POST /v1/moderations`, model `omni-moderation-latest`), with the key of an active
 * OpenAI connection. It is free and detects harmful content, not prompt injection.
 *
 * OpenAI processes the data outside the EU unless the connection points at its European endpoint
 * (`https://eu.api.openai.com`): with the option "EU only" on, only such a connection is used, and the check
 * says `jurisdiction` when there is none.
 *
 * @internal
 */
final class OpenAiModerationGuardrail extends AbstractModerationGuardrail
{
    public const string NAME = 'openai_moderation';

    public function __construct(
        AiModerationConnections $connections,
        AiHttpClientFactory $httpClients,
        AiBaseUrlValidator $baseUrls,
        #[Autowire(param: 'thelia.ai.moderation.openai_model')]
        private readonly string $model,
    ) {
        parent::__construct($connections, $httpClients, $baseUrls);
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function provider(): AiProvider
    {
        return AiProvider::OpenAi;
    }

    protected function path(): string
    {
        return '/v1/moderations';
    }

    protected function body(array $texts): array
    {
        return ['model' => $this->model, 'input' => $texts];
    }

    protected function flaggedCategories(array $response): ?array
    {
        $results = $response['results'] ?? null;

        if (!\is_array($results) || [] === $results) {
            return null;
        }

        $flagged = [];

        foreach ($results as $result) {
            if (!\is_array($result) || !\is_bool($result['flagged'] ?? null)) {
                return null;
            }

            if (!$result['flagged']) {
                continue;
            }

            $names = [];

            foreach (\is_array($result['categories'] ?? null) ? $result['categories'] : [] as $name => $value) {
                if (true === $value) {
                    $names[] = (string) $name;
                }
            }

            // Flagged without a named category is still flagged.
            array_push($flagged, ...([] === $names ? ['flagged'] : $names));
        }

        return $flagged;
    }
}

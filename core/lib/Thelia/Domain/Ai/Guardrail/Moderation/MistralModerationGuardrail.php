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
 * The moderation of Mistral (`POST /v1/moderations`, model "Mistral Moderation 2", 26.03), with the key of an
 * active Mistral connection. The service runs in the EU and is free: the call has no cost line.
 *
 * The answer holds one result per text, each with a `categories` map. The names of the categories of the model
 * are not fixed by the documentation (the list grew with each version), so none is wired in: a text is flagged
 * as soon as the provider marks any category, as a boolean or as an object with `violated`.
 *
 * @internal
 */
final class MistralModerationGuardrail extends AbstractModerationGuardrail
{
    public const string NAME = 'mistral_moderation';

    public function __construct(
        AiModerationConnections $connections,
        AiHttpClientFactory $httpClients,
        AiBaseUrlValidator $baseUrls,
        #[Autowire(param: 'thelia.ai.moderation.mistral_model')]
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
        return AiProvider::Mistral;
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
            $categories = \is_array($result) ? ($result['categories'] ?? null) : null;

            if (!\is_array($categories) || [] === $categories) {
                return null;
            }

            foreach ($categories as $name => $value) {
                if (true === $value || (\is_array($value) && true === ($value['violated'] ?? null))) {
                    $flagged[] = (string) $name;
                }
            }
        }

        return $flagged;
    }
}

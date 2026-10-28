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
 * The answer holds one result per text, each with a `categories` map of booleans (measured on the real answer of
 * mistral-moderation-2603: eleven categories, `category_scores` apart). Only the categories of safety stop a
 * call (`thelia.ai.moderation.mistral_blocking_categories`); the categories of advice (`health`, `financial`,
 * `law`, `pii`) and any category a later version of the model adds are noted in the verdict, never blocking: a
 * customer who gives an address in the chat of a shop is not an offender.
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
        /** @var list<string> */
        #[Autowire(param: 'thelia.ai.moderation.mistral_blocking_categories')]
        private readonly array $blockingCategories,
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

    protected function blocking(array $flagged): array
    {
        return array_values(array_filter($flagged, fn (string $name): bool => \in_array($name, $this->blockingCategories, true)));
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
                // A category that is not a boolean is a shape this code was not written for: no guess, unavailable.
                if (!\is_bool($value)) {
                    return null;
                }

                if ($value) {
                    $flagged[] = (string) $name;
                }
            }
        }

        return $flagged;
    }
}

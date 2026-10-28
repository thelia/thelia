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

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Guardrail\AiGuardrailAvailabilityInterface;
use Thelia\Domain\Ai\Guardrail\AiGuardrailInterface;
use Thelia\Domain\Ai\Guardrail\AiGuardrailTexts;
use Thelia\Domain\Ai\Guardrail\GuardrailAvailability;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;

/**
 * A moderation classifier of a provider: it sends the text a person typed (at the input stage) or what the model
 * produced (at the output stage) to the moderation endpoint of the provider, with the key of one of the connections
 * of the merchant, and fails the check when the provider flags the text.
 *
 * What it never does: send the actor (the request it receives carries a hash only, and only the text leaves), keep
 * the text, or put it in a reason. A call that cannot be made or read (no connection, a refused key, a timeout, an
 * answer of another shape) is `unavailable`: the mode of the usage decides what that costs (the runner blocks in
 * `block` mode and only notes it in `annotate` mode). A classifier is a heuristic, never a guarantee.
 *
 * @internal
 */
abstract class AbstractModerationGuardrail implements AiGuardrailInterface, AiGuardrailAvailabilityInterface
{
    /** Seconds the moderation of a message may take: it runs before the model, in front of a visitor. */
    private const float TIMEOUT = 10.0;

    public function __construct(
        private readonly AiModerationConnections $connections,
        private readonly AiHttpClientFactory $httpClients,
        private readonly AiBaseUrlValidator $baseUrls,
    ) {
    }

    abstract protected function provider(): AiProvider;

    /**
     * @return non-empty-string the path after the base URL of the connection, like `/v1/moderations`
     */
    abstract protected function path(): string;

    /**
     * @param non-empty-list<string> $texts
     *
     * @return array<string, mixed>
     */
    abstract protected function body(array $texts): array;

    /**
     * @param array<mixed> $response the decoded answer of the provider
     *
     * @return list<string>|null the categories the provider flagged (empty: none); null when the answer has not the expected shape
     */
    abstract protected function flaggedCategories(array $response): ?array;

    public function supports(GuardrailStage $stage): bool
    {
        return true;
    }

    public function availability(): GuardrailAvailability
    {
        return $this->connections->select($this->provider())->availability();
    }

    public function inspect(GuardrailStage $stage, AiRequest $request, ?string $output): GuardrailVerdict
    {
        $texts = GuardrailStage::Input === $stage ? AiGuardrailTexts::typedBy($request) : (null === $output ? [] : [$output]);
        $texts = array_values(array_filter($texts, static fn (string $text): bool => '' !== trim($text)));

        if ([] === $texts) {
            return GuardrailVerdict::pass($this->name(), $stage);
        }

        $selection = $this->connections->select($this->provider());

        if (null === $selection->target) {
            return GuardrailVerdict::fail($this->name(), $stage, (string) $selection->reason);
        }

        $categories = $this->moderate($selection->target, $texts);

        if (null === $categories) {
            return GuardrailVerdict::fail($this->name(), $stage, GuardrailAvailability::UNAVAILABLE);
        }

        $blocking = $this->blocking($categories);

        if ([] !== $blocking) {
            return GuardrailVerdict::fail($this->name(), $stage, mb_substr('flagged: '.implode(',', $blocking), 0, 200));
        }

        // What the provider flagged without it being a reason to stop the call is kept in the log, as a note.
        return [] === $categories
            ? GuardrailVerdict::pass($this->name(), $stage)
            : new GuardrailVerdict($this->name(), $stage, true, GuardrailMode::Block, mb_substr('noted: '.implode(',', $categories), 0, 200));
    }

    /**
     * The flagged categories that stop the call: all of them, unless the provider also flags categories of advice.
     *
     * @param list<string> $flagged
     *
     * @return list<string>
     */
    protected function blocking(array $flagged): array
    {
        return $flagged;
    }

    /**
     * @param non-empty-list<string> $texts
     *
     * @return list<string>|null null when the call failed or the answer cannot be read
     */
    private function moderate(ModerationTarget $target, array $texts): ?array
    {
        try {
            $this->baseUrls->validate($target->baseUrl, $target->allowPrivateNetwork);

            $response = $this->httpClients->create($target->allowPrivateNetwork)->request('POST', $this->url($target), [
                'auth_bearer' => $target->apiKey(),
                'json' => $this->body($texts),
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT * 2,
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $decoded = json_decode($response->getContent(), true, 16);
        } catch (HttpExceptionInterface|InvalidAiArgumentException) {
            return null;
        }

        if (!\is_array($decoded)) {
            return null;
        }

        $categories = $this->flaggedCategories($decoded);

        return null === $categories ? null : array_values(array_unique(array_map(self::safeName(...), $categories)));
    }

    private function url(ModerationTarget $target): string
    {
        // A base URL that already ends with the version (`https://host/v1`) is not versioned twice.
        $base = rtrim($target->baseUrl, '/');
        $path = $this->path();

        if (str_ends_with($base, '/v1') && str_starts_with($path, '/v1/')) {
            $base = substr($base, 0, -3);
        }

        return $base.$path;
    }

    /**
     * The name of a category comes from the provider, and from there into the log: only its characters are kept.
     */
    private static function safeName(string $category): string
    {
        return substr(preg_replace('/[^a-z0-9_\/-]/', '', strtolower($category)) ?? '', 0, 48);
    }
}

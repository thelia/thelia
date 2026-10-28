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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta as LibraryTextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiToolDefinition;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Stream\StreamEvent;
use Thelia\Domain\Ai\Stream\TextDelta;
use Thelia\Domain\Ai\Stream\ToolCallReceived;

/**
 * What the adapters share: the request of the contract becomes the input and the options of the library, the
 * answer comes back as an AiProviderResult, and every failure is an AiProviderUnavailableException.
 *
 * A subclass names the platform and the model class of its family and adjusts the options that differ.
 *
 * @internal
 */
abstract class AbstractPlatformAdapter implements AiProviderAdapterInterface
{
    /** Output tokens asked when neither the request nor the model says: a long answer, below what a non-streamed call tolerates. */
    public const int DEFAULT_OUTPUT_TOKENS = 16000;

    /** Options of a native set that the adapter owns: a module cannot redirect the call or break the framing. */
    private const array RESERVED_OPTIONS = ['model', 'messages', 'input', 'instructions', 'system', 'stream', 'stream_options'];

    public function __construct(
        private readonly AiHttpClientFactory $httpClients,
        private readonly AiBaseUrlValidator $baseUrls,
        private readonly AiProviderPresets $presets,
        private readonly AiMessageBagFactory $messages,
        private readonly AiResultReader $results,
        private readonly AiProviderErrorTranslator $errors,
    ) {
    }

    final public function complete(AiRequest $request, AiTarget $target): AiProviderResult
    {
        try {
            $deferred = $this->invoke($request, $target, false);
            $this->assertSuccessful($deferred, $target);

            return $this->results->fromDeferred($deferred, $target, null !== $request->outputSchema, $this->announcedCost($deferred));
        } catch (\Throwable $error) {
            throw $this->errors->translate($error, $target) ?? $error;
        }
    }

    final public function stream(AiRequest $request, AiTarget $target): AiProviderStream
    {
        $progress = new AiStreamProgress($target, null !== $request->outputSchema, $this->results);

        return new AiProviderStream(function (AiStreamProgress $progress) use ($request, $target): \Generator {
            try {
                yield from $this->read($request, $target, $progress);
            } catch (\Throwable $error) {
                throw $this->errors->translate($error, $target) ?? $error;
            }
        }, $progress);
    }

    /**
     * Builds the platform of the family over this client.
     */
    abstract protected function platform(AiTarget $target, #[\SensitiveParameter] ?string $apiKey, string $baseUrl, HttpClientInterface $httpClient): Platform;

    /**
     * @param list<Capability> $capabilities
     */
    abstract protected function model(AiTarget $target, array $capabilities): Model;

    /**
     * The option that caps the output, in the words of the family.
     *
     * @return array<string, mixed>
     */
    protected function outputLimit(int $tokens): array
    {
        return [PlatformOptions::MAX_OUTPUT_TOKENS => $tokens];
    }

    /**
     * The native options of the module for this provider, adjusted when its family names them differently.
     *
     * @param array<string, mixed> $native
     *
     * @return array<string, mixed>
     */
    protected function nativeOptions(array $native): array
    {
        return $native;
    }

    /**
     * Called before the answer is read, for a family whose library does not look at the HTTP status: it throws
     * what the status means (AiProviderUnavailableException).
     */
    protected function assertSuccessful(DeferredResult $deferred, AiTarget $target): void
    {
    }

    /**
     * What the provider announces the call cost, when it does.
     */
    protected function announcedCost(DeferredResult $deferred): ?string
    {
        return null;
    }

    /**
     * @return \Generator<int, StreamEvent>
     */
    private function read(AiRequest $request, AiTarget $target, AiStreamProgress $progress): \Generator
    {
        $deferred = $this->invoke($request, $target, true);
        $this->assertSuccessful($deferred, $target);
        $result = $deferred->getResult();

        if (!$result instanceof StreamResult) {
            throw new AiProviderUnavailableException($target->connectionCode, false, \sprintf('The AI connection "%s" did not answer with a stream.', $target->connectionCode));
        }

        $progress->stream = $result;

        foreach ($deferred->asStream() as $delta) {
            if ($delta instanceof LibraryTextDelta) {
                if ('' === $delta->getText()) {
                    continue;
                }

                $progress->text .= $delta->getText();

                yield new TextDelta($delta->getText());
            } elseif ($delta instanceof ToolCallComplete) {
                foreach ($delta->getToolCalls() as $toolCall) {
                    $call = AiResultReader::toolCall($toolCall, $target->connectionCode);
                    $progress->toolCalls[] = $call;

                    yield new ToolCallReceived($call);
                }
            }
        }

        $progress->complete = true;
    }

    private function invoke(AiRequest $request, AiTarget $target, bool $stream): DeferredResult
    {
        $apiKey = $target->apiKey();

        if (null === $apiKey && !$this->presets->keyIsOptional($target->provider)) {
            throw new AiProviderUnavailableException($target->connectionCode, false, \sprintf('The AI connection "%s" has no API key.', $target->connectionCode));
        }

        $baseUrl = $this->baseUrl($target);

        try {
            $this->baseUrls->validate($baseUrl, $target->allowPrivateNetwork);
        } catch (InvalidAiArgumentException $refused) {
            throw new AiProviderUnavailableException($target->connectionCode, false, \sprintf('The AI connection "%s" has a base URL that is refused. %s', $target->connectionCode, $refused->getMessage()), $refused);
        }

        $platform = $this->platform($target, $apiKey, $baseUrl, $this->httpClients->create($target->allowPrivateNetwork));

        return $platform->invoke(
            $this->model($target, $this->capabilities($target)),
            $this->messages->create($request, $target->provider),
            $this->options($request, $target, $stream),
        );
    }

    private function baseUrl(AiTarget $target): string
    {
        $baseUrl = null !== $target->baseUrl && '' !== trim($target->baseUrl) ? trim($target->baseUrl) : $this->presets->defaultBaseUrl($target->provider);

        if (null === $baseUrl) {
            throw new AiProviderUnavailableException($target->connectionCode, false, \sprintf('The AI connection "%s" has no base URL.', $target->connectionCode));
        }

        return $baseUrl;
    }

    /**
     * @return list<Capability>
     */
    private function capabilities(AiTarget $target): array
    {
        $capabilities = [Capability::INPUT_MESSAGES, Capability::INPUT_TEXT, Capability::OUTPUT_TEXT];

        if ($target->model->supportsStreaming) {
            $capabilities[] = Capability::OUTPUT_STREAMING;
        }

        if ($target->model->supportsStructuredOutput) {
            $capabilities[] = Capability::OUTPUT_STRUCTURED;
        }

        if ($target->model->supportsTools) {
            $capabilities[] = Capability::TOOL_CALLING;
        }

        if ($target->model->supportsVision) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        return $capabilities;
    }

    /**
     * The native options of the module first, then what the layer decides: the framing of the call cannot be
     * overridden from `providerOptions`.
     *
     * @return array<string, mixed>
     */
    private function options(AiRequest $request, AiTarget $target, bool $stream): array
    {
        $native = array_diff_key($this->nativeOptions($request->optionsFor($target->provider)), array_flip(self::RESERVED_OPTIONS));

        $options = $this->outputLimit($this->outputTokens($request, $target));

        if ($stream) {
            $options['stream'] = true;
        }

        if (null !== $request->outputSchema) {
            $options['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->outputSchema->name,
                    // OpenAI refuses a strict schema that does not close its objects.
                    'strict' => false === ($request->outputSchema->schema['additionalProperties'] ?? null),
                    'schema' => $request->outputSchema->schema,
                ],
            ];
        }

        if ([] !== $request->tools) {
            // The module runs its tools: the reference is only there because the library wants one.
            $options['tools'] = array_map(
                static fn (AiToolDefinition $tool): Tool => new Tool(new ExecutionReference(\stdClass::class), $tool->name, $tool->description, $tool->inputSchema),
                $request->tools,
            );
        }

        return array_replace($native, $options);
    }

    private function outputTokens(AiRequest $request, AiTarget $target): int
    {
        $modelLimit = $target->model->maxOutputTokens;

        if (null !== $request->maxOutputTokens) {
            return null === $modelLimit ? $request->maxOutputTokens : min($request->maxOutputTokens, $modelLimit);
        }

        return min($modelLimit ?? self::DEFAULT_OUTPUT_TOKENS, self::DEFAULT_OUTPUT_TOKENS);
    }
}

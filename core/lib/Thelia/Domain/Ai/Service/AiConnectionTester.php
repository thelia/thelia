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

namespace Thelia\Domain\Ai\Service;

use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Provider\AiProviderAdapters;
use Thelia\Domain\Ai\Provider\AiProviderErrorTranslator;
use Thelia\Domain\Ai\Provider\AiProviderPresets;
use Thelia\Domain\Ai\Provider\AiTarget;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;

/**
 * Checks that a connection answers: one minimal call through the adapter of its provider, to a model this connection
 * is known to serve. The model is, in this order: one a range or a usage already runs on this connection; else
 * the probe model the preset of the provider declares (a cheap model every account of the provider has, a dated
 * one for OpenAI); else none, and the test says so instead of calling a model that may not exist (an Ollama server
 * only has the models it pulled). The result (time and error) is stored on the connection; the error is a short
 * sentence that never holds the key, and names the model that was called.
 *
 * The call is not a usage of a module: it does not go through the gateway, so it is neither budgeted nor
 * logged, and costs a few tokens at most.
 *
 * @internal
 */
final readonly class AiConnectionTester
{
    private const int ERROR_LIMIT = 255;

    public function __construct(
        private AiProviderAdapters $adapters,
        private AiModelCatalog $catalog,
        private AiKeyVault $vault,
        private AiProviderPresets $presets,
        private AiRoutingRepository $routing,
    ) {
    }

    /**
     * @return string|null null when the connection answered, the error otherwise
     */
    public function test(AiConnection $connection): ?string
    {
        $apiKey = null;
        $model = null;

        try {
            $error = $this->probe($connection, $apiKey, $model);
        } catch (AiExceptionInterface $failure) {
            $error = AiProviderErrorTranslator::redact($failure->getMessage(), $apiKey);
        }

        if (null !== $error && null !== $model) {
            // The model comes last in the sentence and is never cut: it tells which model to fix.
            $suffix = \sprintf(' (model: %s)', $model);
            $error = mb_strimwidth($error, 0, self::ERROR_LIMIT - mb_strlen($suffix), '...').$suffix;
        }

        $connection
            ->setCheckedAt(new \DateTimeImmutable())
            ->setCheckError(null === $error ? null : mb_strimwidth($error, 0, self::ERROR_LIMIT, '...'))
            ->save();

        return null === $error ? null : $connection->getCheckError();
    }

    private function probe(AiConnection $connection, ?string &$apiKey, ?string &$model): ?string
    {
        $provider = AiProvider::tryFrom((string) $connection->getProvider());

        if (null === $provider) {
            return AiFailureExplainer::CHECK_PROVIDER_UNSUPPORTED;
        }

        $apiKey = $this->vault->resolve($connection);
        $baseUrl = '' === trim((string) $connection->getBaseUrl()) ? null : trim((string) $connection->getBaseUrl());
        $specification = $this->modelToCall($connection, $provider);

        if (null === $specification) {
            return AiFailureExplainer::CHECK_NO_MODEL;
        }

        $model = $specification->name;

        $target = new AiTarget(
            $connection->getId(),
            (string) $connection->getCode(),
            $provider,
            $baseUrl,
            $apiKey,
            (bool) $connection->getAllowPrivateNetwork(),
            AiJurisdiction::tryFrom((string) $connection->getJurisdiction()) ?? AiJurisdiction::OutsideEuropeanUnion,
            $specification,
        );

        $this->adapters->for($provider)->complete(
            new AiRequest('core.connection_test', [AiMessage::user('Reply with the word ok.')], maxOutputTokens: 16),
            $target,
        );

        return null;
    }

    private function modelToCall(AiConnection $connection, AiProvider $provider): ?AiModelSpecification
    {
        $known = $this->catalog->models($provider->value, $connection->getId());

        foreach ($this->routing->modelNamesServedBy($connection) as $name) {
            if (isset($known[$name])) {
                return $known[$name];
            }
        }

        $probe = $this->presets->probeModel($provider);

        return null === $probe ? null : ($known[$probe] ?? null);
    }
}

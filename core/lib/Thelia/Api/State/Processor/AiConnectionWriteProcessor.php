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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\AiConnection as AiConnectionResource;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiTierAssignmentQuery;

/**
 * Creates, updates and deletes a connection. The key goes through the vault and is never returned; the
 * address is checked by the rule the gateway uses; the jurisdiction comes from the provider unless the
 * provider cannot tell (openai_compatible, ollama), where the client declares it. A refusal is a 422.
 *
 * An update writes what the resource holds after the body was applied to it: a field the client left out keeps
 * the value it had, and an empty `apiKey` keeps the stored key.
 */
final readonly class AiConnectionWriteProcessor implements ProcessorInterface
{
    private const string CODE_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(
        private ApiResourcePropelTransformerService $transformer,
        private AiKeyVault $vault,
        private AiBaseUrlValidator $baseUrlValidator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $connection = $operation instanceof Post
            ? new AiConnection()
            : (AiConnectionQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('AI connection not found.'));

        if ($operation instanceof DeleteOperationInterface) {
            if (AiTierAssignmentQuery::create()->filterByConnectionId($connection->getId())->count() > 0) {
                throw new ConflictHttpException('This connection is assigned to a tier: assign another one first.');
            }

            $connection->delete();

            return null;
        }

        if (!$data instanceof AiConnectionResource) {
            throw new UnprocessableEntityHttpException('An AI connection is expected.');
        }

        $this->apply($connection, $data);
        $connection->save();

        return $this->transformer->modelToResource(
            resourceClass: AiConnectionResource::class,
            propelModel: $connection,
            context: $context,
        );
    }

    private function apply(AiConnection $connection, AiConnectionResource $data): void
    {
        $code = (string) $data->getCode();

        if (1 !== preg_match(self::CODE_PATTERN, $code)) {
            throw new UnprocessableEntityHttpException('The code is lower case letters, digits, "-" and "_", 64 characters at most.');
        }

        $taken = AiConnectionQuery::create()->filterByCode($code);

        if (null !== $connection->getId()) {
            $taken->filterById($connection->getId(), Criteria::NOT_EQUAL);
        }

        if ($taken->count() > 0) {
            throw new UnprocessableEntityHttpException('Another connection already has this code.');
        }

        if ('' === trim((string) $data->getTitle())) {
            throw new UnprocessableEntityHttpException('The title is required.');
        }

        $provider = AiProvider::tryFrom((string) $data->getProvider())
            ?? throw new UnprocessableEntityHttpException(\sprintf('The provider must be one of: %s.', implode(', ', array_column(AiProvider::cases(), 'value'))));

        $baseUrl = '' === trim((string) $data->getBaseUrl()) ? null : trim((string) $data->getBaseUrl());
        $allowPrivateNetwork = (bool) $data->getAllowPrivateNetwork();

        if (null !== $baseUrl) {
            try {
                $this->baseUrlValidator->validate($baseUrl, $allowPrivateNetwork);
            } catch (AiExceptionInterface $refusal) {
                throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
            }
        }

        $jurisdiction = $provider->defaultJurisdiction($baseUrl)
            ?? AiJurisdiction::tryFrom((string) $data->getJurisdiction())
            ?? throw new UnprocessableEntityHttpException('The jurisdiction ("eu" or "non_eu") is required for this provider.');

        $environmentVariable = '' === trim((string) $data->getApiKeyEnv()) ? null : trim((string) $data->getApiKeyEnv());

        if (null !== $environmentVariable && 1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $environmentVariable)) {
            throw new UnprocessableEntityHttpException('The environment variable name is letters, digits and underscores.');
        }

        $connection
            ->setCode($code)
            ->setTitle(trim((string) $data->getTitle()))
            ->setProvider($provider->value)
            ->setJurisdiction($jurisdiction->value)
            ->setBaseUrl($baseUrl)
            ->setApiKeyEnv($environmentVariable)
            ->setAllowPrivateNetwork((int) $allowPrivateNetwork)
            ->setActive((int) ($data->getActive() ?? true));

        if (null !== $data->getApiKey() && '' !== trim($data->getApiKey())) {
            $connection->setApiKeyEncrypted($this->vault->encrypt(trim($data->getApiKey())));
        }
    }
}

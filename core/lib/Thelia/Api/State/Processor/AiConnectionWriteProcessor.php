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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\AiConnection as AiConnectionResource;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Exception\AiResourceInUseException;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;

/**
 * Creates, updates and deletes a connection; the rules (key in the vault and never returned, address, jurisdiction)
 * are those of {@see AiSettingsWriter}. A refusal is a 422.
 *
 * An update writes what the resource holds after the body was applied to it: a field the client left out keeps
 * the value it had, and an empty `apiKey` keeps the stored key.
 */
final readonly class AiConnectionWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $transformer,
        private AiSettingsWriter $writer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $connection = $operation instanceof Post
            ? new AiConnection()
            : (AiConnectionQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('AI connection not found.'));

        if ($operation instanceof DeleteOperationInterface) {
            try {
                $this->writer->deleteConnection($connection);
            } catch (AiResourceInUseException $inUse) {
                throw new ConflictHttpException($inUse->getMessage(), $inUse);
            }

            return null;
        }

        if (!$data instanceof AiConnectionResource) {
            throw new UnprocessableEntityHttpException('An AI connection is expected.');
        }

        try {
            $connection = $this->writer->saveConnection(
                $connection,
                (string) $data->getCode(),
                (string) $data->getTitle(),
                (string) $data->getProvider(),
                $data->getJurisdiction(),
                $data->getBaseUrl(),
                $data->getApiKey(),
                $data->getApiKeyEnv(),
                (bool) $data->getAllowPrivateNetwork(),
                (bool) ($data->getActive() ?? true),
            );
        } catch (AiExceptionInterface $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }

        return $this->transformer->modelToResource(
            resourceClass: AiConnectionResource::class,
            propelModel: $connection,
            context: $context,
        );
    }
}

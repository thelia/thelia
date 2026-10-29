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
use Thelia\Api\Resource\AiModel as AiModelResource;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Exception\AiResourceInUseException;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Model\AiModel;
use Thelia\Model\AiModelQuery;

/**
 * Creates, updates and deletes a model row by the rules of {@see AiSettingsWriter}. A refusal is a 422, and a
 * model still assigned to a tier is a 409 on deletion.
 */
final readonly class AiModelWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $transformer,
        private AiSettingsWriter $writer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $model = $operation instanceof Post
            ? new AiModel()
            : (AiModelQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('AI model not found.'));

        if ($operation instanceof DeleteOperationInterface) {
            try {
                $this->writer->deleteModel($model);
            } catch (AiResourceInUseException $inUse) {
                throw new ConflictHttpException($inUse->getMessage(), $inUse);
            }

            return null;
        }

        if (!$data instanceof AiModelResource) {
            throw new UnprocessableEntityHttpException('An AI model is expected.');
        }

        try {
            $model = $this->writer->saveModel(
                $model,
                (string) $data->getProvider(),
                (string) $data->getName(),
                $data->getConnectionId(),
                $data->getTitle(),
                $data->getInputPrice(),
                $data->getOutputPrice(),
                $data->getCacheReadPrice(),
                $data->getCacheWritePrice(),
                $data->getCurrency(),
                $data->getContextWindow(),
                $data->getMaxOutputTokens(),
                $data->getSupportsStructuredOutput() ?? false,
                $data->getSupportsTools() ?? false,
                $data->getSupportsVision() ?? false,
                $data->getSupportsStreaming() ?? true,
                $data->getActive() ?? true,
            );
        } catch (AiExceptionInterface $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }

        return $this->transformer->modelToResource(
            resourceClass: AiModelResource::class,
            propelModel: $model,
            context: $context,
        );
    }
}

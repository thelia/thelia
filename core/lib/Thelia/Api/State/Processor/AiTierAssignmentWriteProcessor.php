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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\AiTierAssignment as AiTierAssignmentResource;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\AiTierAssignmentQuery;

/**
 * Assigns a connection and a model to a tier by the rules of {@see AiSettingsWriter}: a tier has one assignment at
 * most. A refusal is a 422.
 */
final readonly class AiTierAssignmentWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $transformer,
        private AiSettingsWriter $writer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $assignment = $operation instanceof Post
            ? new AiTierAssignment()
            : (AiTierAssignmentQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('AI tier assignment not found.'));

        if ($operation instanceof DeleteOperationInterface) {
            $this->writer->deleteTierAssignment($assignment);

            return null;
        }

        if (!$data instanceof AiTierAssignmentResource) {
            throw new UnprocessableEntityHttpException('An AI tier assignment is expected.');
        }

        try {
            $assignment = $this->writer->assignTier(
                $assignment,
                (string) $data->getTier(),
                (int) $data->getConnectionId(),
                (int) $data->getModelId(),
            );
        } catch (AiExceptionInterface $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }

        return $this->transformer->modelToResource(
            resourceClass: AiTierAssignmentResource::class,
            propelModel: $assignment,
            context: $context,
        );
    }
}

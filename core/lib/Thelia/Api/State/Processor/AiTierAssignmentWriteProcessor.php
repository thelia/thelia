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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\AiTierAssignment as AiTierAssignmentResource;
use Thelia\Domain\Ai\AiTier;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModelQuery;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\AiTierAssignmentQuery;

/**
 * Assigns a connection and a model to a tier. The model must belong to the provider of the connection, and to
 * the connection itself when it is bound to one; a tier has one assignment at most. A refusal is a 422.
 */
final readonly class AiTierAssignmentWriteProcessor implements ProcessorInterface
{
    public function __construct(private ApiResourcePropelTransformerService $transformer)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $assignment = $operation instanceof Post
            ? new AiTierAssignment()
            : (AiTierAssignmentQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('AI tier assignment not found.'));

        if ($operation instanceof DeleteOperationInterface) {
            $assignment->delete();

            return null;
        }

        if (!$data instanceof AiTierAssignmentResource) {
            throw new UnprocessableEntityHttpException('An AI tier assignment is expected.');
        }

        $tier = AiTier::tryFrom((string) $data->getTier())
            ?? throw new UnprocessableEntityHttpException(\sprintf('The tier must be one of: %s.', implode(', ', array_column(AiTier::cases(), 'value'))));
        $connection = AiConnectionQuery::create()->findPk((int) $data->getConnectionId())
            ?? throw new UnprocessableEntityHttpException('The connection does not exist.');
        $model = AiModelQuery::create()->findPk((int) $data->getModelId())
            ?? throw new UnprocessableEntityHttpException('The model does not exist.');

        if ($model->getProvider() !== $connection->getProvider() || (null !== $model->getConnectionId() && $model->getConnectionId() !== $connection->getId())) {
            throw new UnprocessableEntityHttpException('The model is not served by this connection.');
        }

        $taken = AiTierAssignmentQuery::create()->filterByTier($tier->value);

        if (null !== $assignment->getId()) {
            $taken->filterById($assignment->getId(), Criteria::NOT_EQUAL);
        }

        if ($taken->count() > 0) {
            throw new UnprocessableEntityHttpException('This tier already has an assignment: update it instead.');
        }

        $assignment->setTier($tier->value)->setConnectionId($connection->getId())->setModelId($model->getId())->save();

        return $this->transformer->modelToResource(
            resourceClass: AiTierAssignmentResource::class,
            propelModel: $assignment,
            context: $context,
        );
    }
}

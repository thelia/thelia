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
use Thelia\Api\Resource\AiModel as AiModelResource;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Internal\AiAssert;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModel;
use Thelia\Model\AiModelQuery;
use Thelia\Model\AiTierAssignmentQuery;

/**
 * Creates, updates and deletes a model row. The unicity of (provider, name, connection) is checked here, not
 * left to the index: MySQL does not compare NULLs, so two rows of the catalogue of a provider (no connection)
 * would pass it. A row written through the API is `manual`. A refusal is a 422, a model still assigned to a
 * tier is a 409 on deletion.
 */
final readonly class AiModelWriteProcessor implements ProcessorInterface
{
    private const string NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:\/@+-]{0,127}$/';

    private const array PRICES = ['inputPrice' => 'The input price', 'outputPrice' => 'The output price', 'cacheReadPrice' => 'The cache read price', 'cacheWritePrice' => 'The cache write price'];

    public function __construct(private ApiResourcePropelTransformerService $transformer)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $model = $operation instanceof Post
            ? new AiModel()
            : (AiModelQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('AI model not found.'));

        if ($operation instanceof DeleteOperationInterface) {
            if (AiTierAssignmentQuery::create()->filterByModelId($model->getId())->count() > 0) {
                throw new ConflictHttpException('This model is assigned to a tier: assign another one first.');
            }

            $model->delete();

            return null;
        }

        if (!$data instanceof AiModelResource) {
            throw new UnprocessableEntityHttpException('An AI model is expected.');
        }

        try {
            $this->apply($model, $data);
        } catch (AiExceptionInterface $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }

        $model->save();

        return $this->transformer->modelToResource(
            resourceClass: AiModelResource::class,
            propelModel: $model,
            context: $context,
        );
    }

    private function apply(AiModel $model, AiModelResource $data): void
    {
        $provider = AiProvider::tryFrom((string) $data->getProvider())
            ?? throw new UnprocessableEntityHttpException(\sprintf('The provider must be one of: %s.', implode(', ', array_column(AiProvider::cases(), 'value'))));
        $name = trim((string) $data->getName());

        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw new UnprocessableEntityHttpException('The model name is the identifier the provider gives it: letters, digits and . _ : / @ + -, 128 characters at most.');
        }

        $connectionId = $data->getConnectionId();

        if (null !== $connectionId) {
            $connection = AiConnectionQuery::create()->findPk($connectionId)
                ?? throw new UnprocessableEntityHttpException('The connection does not exist.');

            if ($connection->getProvider() !== $provider->value) {
                throw new UnprocessableEntityHttpException('The provider of the model is not the one of its connection.');
            }
        }

        $taken = AiModelQuery::create()->filterByProvider($provider->value)->filterByName($name);
        null === $connectionId ? $taken->filterByConnectionId(null, Criteria::ISNULL) : $taken->filterByConnectionId($connectionId);

        if (null !== $model->getId()) {
            $taken->filterById($model->getId(), Criteria::NOT_EQUAL);
        }

        if ($taken->count() > 0) {
            throw new UnprocessableEntityHttpException('This model already exists for this provider and connection.');
        }

        $currency = strtoupper(trim($data->getCurrency() ?? 'USD'));
        AiAssert::currency($currency);

        $prices = [];

        foreach (self::PRICES as $property => $label) {
            $value = $data->{'get'.ucfirst($property)}();
            $value = null === $value || '' === trim($value) ? null : trim($value);

            if (null !== $value) {
                AiAssert::decimal($value, $label);
            }

            $prices[$property] = $value;
        }

        $model
            ->setProvider($provider->value)
            ->setName($name)
            ->setConnectionId($connectionId)
            ->setTitle(null === $data->getTitle() || '' === trim($data->getTitle()) ? null : trim($data->getTitle()))
            ->setInputPrice($prices['inputPrice'] ?? '0')
            ->setOutputPrice($prices['outputPrice'] ?? '0')
            ->setCacheReadPrice($prices['cacheReadPrice'])
            ->setCacheWritePrice($prices['cacheWritePrice'])
            ->setCurrency($currency)
            ->setContextWindow($this->positive($data->getContextWindow(), 'The context window'))
            ->setMaxOutputTokens($this->positive($data->getMaxOutputTokens(), 'The output limit'))
            ->setSupportsStructuredOutput((int) ($data->getSupportsStructuredOutput() ?? false))
            ->setSupportsTools((int) ($data->getSupportsTools() ?? false))
            ->setSupportsVision((int) ($data->getSupportsVision() ?? false))
            ->setSupportsStreaming((int) ($data->getSupportsStreaming() ?? true))
            ->setSource(AiModelSpecification::SOURCE_MANUAL)
            ->setActive((int) ($data->getActive() ?? true));
    }

    private function positive(?int $value, string $label): ?int
    {
        if (null !== $value && $value < 1) {
            throw new UnprocessableEntityHttpException($label.' must be a positive number.');
        }

        return $value;
    }
}

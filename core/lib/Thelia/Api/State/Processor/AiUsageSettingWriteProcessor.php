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
use Thelia\Api\Resource\AiUsageSetting as AiUsageSettingResource;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Guardrail\CoreGuardrail;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Internal\AiAssert;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModelQuery;
use Thelia\Model\AiUsageSetting;
use Thelia\Model\AiUsageSettingQuery;

/**
 * Writes the settings of a usage a module declared. The usage must exist (an identifier nobody declared is a
 * 422), a usage has one row, amounts are decimal strings, the pinned connection and model must exist and
 * belong together, and the guardrail modes are `block` or `annotate`. Deleting the row gives the usage back
 * its defaults.
 */
final readonly class AiUsageSettingWriteProcessor implements ProcessorInterface
{
    private const array LIMITS = [
        'requestsPerMinute' => 'The limit per minute',
        'requestsPerDay' => 'The limit per day',
        'maxOutputTokens' => 'The output limit',
        'maxInputCharacters' => 'The input limit',
        'promptRetentionDays' => 'The prompt retention',
    ];

    public function __construct(
        private ApiResourcePropelTransformerService $transformer,
        private AiUsageRegistryInterface $usages,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $setting = $operation instanceof Post
            ? new AiUsageSetting()
            : (AiUsageSettingQuery::create()->findPk((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('AI usage setting not found.'));

        if ($operation instanceof DeleteOperationInterface) {
            $setting->delete();

            return null;
        }

        if (!$data instanceof AiUsageSettingResource) {
            throw new UnprocessableEntityHttpException('AI usage settings are expected.');
        }

        try {
            $this->apply($setting, $data);
        } catch (AiExceptionInterface $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }

        $setting->save();

        return $this->transformer->modelToResource(
            resourceClass: AiUsageSettingResource::class,
            propelModel: $setting,
            context: $context,
        );
    }

    private function apply(AiUsageSetting $setting, AiUsageSettingResource $data): void
    {
        $usageId = (string) $data->getUsageId();
        $this->usages->get($usageId);

        $taken = AiUsageSettingQuery::create()->filterByUsageId($usageId);

        if (null !== $setting->getId()) {
            $taken->filterById($setting->getId(), Criteria::NOT_EQUAL);
        }

        if ($taken->count() > 0) {
            throw new UnprocessableEntityHttpException('This usage already has settings: update them instead.');
        }

        $budget = null === $data->getMonthlyBudget() || '' === trim($data->getMonthlyBudget()) ? null : trim($data->getMonthlyBudget());

        if (null !== $budget) {
            AiAssert::decimal($budget, 'The monthly budget');
        }

        $currency = strtoupper(trim($data->getCurrency() ?? 'USD'));
        AiAssert::currency($currency);

        $pinnedConnectionId = $data->getPinnedConnectionId();
        $pinnedModelId = $data->getPinnedModelId();
        $connection = null === $pinnedConnectionId ? null : (AiConnectionQuery::create()->findPk($pinnedConnectionId) ?? throw new UnprocessableEntityHttpException('The pinned connection does not exist.'));
        $model = null === $pinnedModelId ? null : (AiModelQuery::create()->findPk($pinnedModelId) ?? throw new UnprocessableEntityHttpException('The pinned model does not exist.'));

        if (null !== $connection && null !== $model && ($model->getProvider() !== $connection->getProvider() || (null !== $model->getConnectionId() && $model->getConnectionId() !== $connection->getId()))) {
            throw new UnprocessableEntityHttpException('The pinned model is not served by the pinned connection.');
        }

        $guardrails = [];

        foreach ($data->getGuardrails() ?? [] as $name => $mode) {
            AiAssert::token((string) $name, 'A guardrail name');

            if (CoreGuardrail::isReserved((string) $name)) {
                throw new UnprocessableEntityHttpException(\sprintf('"%s" is not the name of an optional guardrail.', $name));
            }

            if (!\is_string($mode) || null === GuardrailMode::tryFrom($mode)) {
                throw new UnprocessableEntityHttpException(\sprintf('The mode of the guardrail "%s" is "block" or "annotate".', $name));
            }

            $guardrails[(string) $name] = $mode;
        }

        $setting
            ->setUsageId($usageId)
            ->setEnabled((int) ($data->getEnabled() ?? true))
            ->setMonthlyBudget($budget)
            ->setCurrency($currency)
            ->setPinnedConnectionId($pinnedConnectionId)
            ->setPinnedModelId($pinnedModelId)
            ->setGuardrailMap($guardrails)
            ->setTransparencyNotice((int) ($data->getTransparencyNotice() ?? true))
            ->setProvenanceMark((int) ($data->getProvenanceMark() ?? true))
            ->setStorePrompt((int) ($data->getStorePrompt() ?? false));

        foreach (self::LIMITS as $property => $label) {
            $value = $data->{'get'.ucfirst($property)}();

            if (null !== $value && $value < 1) {
                throw new UnprocessableEntityHttpException($label.' must be a positive number.');
            }

            $setting->{'set'.ucfirst($property)}($value);
        }
    }
}

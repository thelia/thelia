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
use Thelia\Api\Resource\AiUsageSetting as AiUsageSettingResource;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Model\AiUsageSetting;
use Thelia\Model\AiUsageSettingQuery;

/**
 * Writes the settings of a usage a module declared by the rules of {@see AiSettingsWriter}: the usage must exist
 * (an identifier nobody declared is a 422), a usage has one row, the range is `fast`, `standard` or `advanced`.
 * Deleting the row gives the usage back its defaults.
 */
final readonly class AiUsageSettingWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $transformer,
        private AiSettingsWriter $writer,
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
            $setting = $this->writer->saveUsageSetting(
                $setting,
                (string) $data->getUsageId(),
                $data->getEnabled() ?? true,
                $data->getTier(),
                $data->getMonthlyBudget(),
                $data->getCurrency() ?? 'USD',
                $data->getRequestsPerMinute(),
                $data->getRequestsPerDay(),
                $data->getMaxOutputTokens(),
                $data->getMaxInputCharacters(),
                $data->getPinnedConnectionId(),
                $data->getPinnedModelId(),
                $data->getGuardrails() ?? [],
                $data->getTransparencyNotice() ?? true,
                $data->getProvenanceMark() ?? true,
                $data->getStorePrompt() ?? false,
                $data->getPromptRetentionDays(),
            );
        } catch (AiExceptionInterface $refusal) {
            throw new UnprocessableEntityHttpException($refusal->getMessage(), $refusal);
        }

        return $this->transformer->modelToResource(
            resourceClass: AiUsageSettingResource::class,
            propelModel: $setting,
            context: $context,
        );
    }
}

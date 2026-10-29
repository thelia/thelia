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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Resource\AiConsumption;
use Thelia\Domain\Ai\Log\AiConsumptionReport;

/**
 * Sums the AI log by month and by usage, newest month first. The filters are bound parameters; a month that
 * is not written YYYY-MM is a 422.
 */
final readonly class AiConsumptionProvider implements ProviderInterface
{
    public function __construct(private AiConsumptionReport $report)
    {
    }

    /**
     * @return list<AiConsumption>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        foreach (['month', 'from', 'to'] as $name) {
            if (isset($filters[$name]) && (!\is_string($filters[$name]) || 1 !== preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $filters[$name]))) {
                throw new UnprocessableEntityHttpException(\sprintf('"%s" is a month written YYYY-MM.', $name));
            }
        }

        $text = static fn (string $name): ?string => isset($filters[$name]) && \is_string($filters[$name]) && '' !== $filters[$name] ? $filters[$name] : null;

        $rows = [];

        foreach ($this->report->byMonthAndUsage($text('month'), $text('from'), $text('to'), $text('usageId'), $text('module')) as $row) {
            $consumption = new AiConsumption();
            $consumption->id = $row['month'].'/'.$row['usage_id'].'/'.$row['currency'];
            $consumption->month = $row['month'];
            $consumption->usageId = $row['usage_id'];
            $consumption->module = $row['module'];
            $consumption->calls = $row['calls'];
            $consumption->failures = $row['failures'];
            $consumption->externalCalls = $row['external_calls'];
            $consumption->inputTokens = $row['input_tokens'];
            $consumption->outputTokens = $row['output_tokens'];
            $consumption->cost = $row['cost'];
            $consumption->currency = $row['currency'];
            $rows[] = $consumption;
        }

        return $rows;
    }
}

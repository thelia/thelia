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
use Propel\Runtime\Propel;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Resource\AiConsumption;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\Log\AiLogKind;
use Thelia\Model\Map\AiUsageLogTableMap;

/**
 * Sums the AI log by month and by usage, newest month first. The filters are bound parameters; a month that
 * is not written YYYY-MM is a 422.
 */
final readonly class AiConsumptionProvider implements ProviderInterface
{
    /**
     * @return list<AiConsumption>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        $where = [];
        $parameters = [];

        foreach (['month' => ['=', '='], 'from' => ['>=', null], 'to' => ['<=', null]] as $name => $unused) {
            if (!isset($filters[$name])) {
                continue;
            }

            if (!\is_string($filters[$name]) || 1 !== preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $filters[$name])) {
                throw new UnprocessableEntityHttpException(\sprintf('"%s" is a month written YYYY-MM.', $name));
            }
        }

        if (isset($filters['month'])) {
            $where[] = "DATE_FORMAT(`created_at`, '%Y-%m') = :month";
            $parameters[':month'] = $filters['month'];
        }

        if (isset($filters['from'])) {
            $where[] = "DATE_FORMAT(`created_at`, '%Y-%m') >= :from";
            $parameters[':from'] = $filters['from'];
        }

        if (isset($filters['to'])) {
            $where[] = "DATE_FORMAT(`created_at`, '%Y-%m') <= :to";
            $parameters[':to'] = $filters['to'];
        }

        foreach (['usageId' => 'usage_id', 'module' => 'module'] as $name => $column) {
            if (isset($filters[$name]) && \is_string($filters[$name]) && '' !== $filters[$name]) {
                $where[] = "`{$column}` = :{$name}";
                $parameters[':'.$name] = $filters[$name];
            }
        }

        $sql = <<<'SQL'
            SELECT DATE_FORMAT(`created_at`, '%Y-%m') AS month, `usage_id`, `module`,
                SUM(`kind` <> :external_calls) AS calls,
                SUM(`kind` <> :external_failures AND `status` <> :succeeded) AS failures,
                SUM(`kind` = :external_kind) AS external_calls,
                SUM(`input_tokens`) AS input_tokens, SUM(`output_tokens`) AS output_tokens, SUM(`cost`) AS cost
            FROM `ai_usage_log`
            SQL
            .([] === $where ? '' : ' WHERE '.implode(' AND ', $where))
            .' GROUP BY month, `usage_id`, `module` ORDER BY month DESC, `usage_id` ASC';

        $statement = Propel::getConnection(AiUsageLogTableMap::DATABASE_NAME)->prepare($sql);
        $statement->execute([':external_calls' => AiLogKind::External->value, ':external_failures' => AiLogKind::External->value, ':external_kind' => AiLogKind::External->value, ':succeeded' => AiUsageLogStatus::Succeeded->value] + $parameters);

        $rows = [];

        while (false !== ($row = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $consumption = new AiConsumption();
            $consumption->id = $row['month'].'/'.$row['usage_id'];
            $consumption->month = $row['month'];
            $consumption->usageId = $row['usage_id'];
            $consumption->module = $row['module'];
            $consumption->calls = (int) $row['calls'];
            $consumption->failures = (int) $row['failures'];
            $consumption->externalCalls = (int) $row['external_calls'];
            $consumption->inputTokens = (int) $row['input_tokens'];
            $consumption->outputTokens = (int) $row['output_tokens'];
            $consumption->cost = number_format((float) $row['cost'], 8, '.', '');
            $rows[] = $consumption;
        }

        return $rows;
    }
}

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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Thelia\Api\Resource\AiUsage;
use Thelia\Domain\Ai\Service\AiUsageRegister;

/**
 * Serves the register of the AI usages as resources, from the rows the console export uses.
 */
final readonly class AiUsageRegisterProvider implements ProviderInterface
{
    public function __construct(private AiUsageRegister $register)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|AiUsage|null
    {
        $locale = $context['filters']['locale'] ?? null;
        $rows = $this->register->rows(\is_string($locale) && 1 === preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale) ? $locale : null);

        if (!$operation instanceof CollectionOperationInterface) {
            foreach ($rows as $row) {
                if ($row['id'] === ($uriVariables['id'] ?? null)) {
                    return $this->resource($row);
                }
            }

            return null;
        }

        return array_map($this->resource(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resource(array $row): AiUsage
    {
        $usage = new AiUsage();
        $usage->id = $row['id'];
        $usage->module = $row['module'];
        $usage->purpose = $row['purpose'];
        $usage->audience = $row['audience'];
        $usage->tier = $row['tier'];
        $usage->declaredTier = $row['declared_tier'];
        $usage->transparency = $row['transparency'];
        $usage->active = $row['active'];
        $usage->noticeEnabled = $row['notice_enabled'];
        $usage->provenanceMarkEnabled = $row['provenance_mark_enabled'];
        $usage->compliance = $row['compliance'];
        $usage->complianceDetails = $row['compliance_details'];
        $usage->jurisdiction = $row['jurisdiction'];

        return $usage;
    }
}

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

namespace Thelia\Domain\Ai;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * The mark of a generated content (AI Act, article 50(2)). Store toArray() with the content the module
 * publishes, and expose it as machine-readable metadata wherever the content is shown.
 */
#[Exclude]
final readonly class AiProvenance
{
    public function __construct(
        public string $usageId,
        public string $model,
        public \DateTimeImmutable $generatedAt,
        public int $logId,
    ) {
        AiAssert::usageId($usageId);
        AiAssert::notBlank($model, 'The model of a provenance');

        if ($logId < 1) {
            throw new Exception\InvalidAiArgumentException('A provenance points at a row of the usage log.');
        }
    }

    /**
     * @return array{ai_generated: true, usage: string, model: string, generated_at: string, log_id: int}
     */
    public function toArray(): array
    {
        return [
            'ai_generated' => true,
            'usage' => $this->usageId,
            'model' => $this->model,
            'generated_at' => $this->generatedAt->format(\DATE_ATOM),
            'log_id' => $this->logId,
        ];
    }
}

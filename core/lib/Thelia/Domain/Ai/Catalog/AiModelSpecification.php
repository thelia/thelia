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

namespace Thelia\Domain\Ai\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What the layer knows about one model: its prices (per million tokens, decimal strings), its limits and
 * what it can do. Comes from the embedded price list or from a row the merchant added or edited.
 *
 * `thinkingOff` is the native `thinking` setting that keeps the model from thinking before it answers, when the
 * embedded list knows it (Anthropic: `disabled`, `between_tools`, or `adaptive` for a model that has no off switch).
 *
 * @internal
 */
#[Exclude]
final readonly class AiModelSpecification
{
    public const string SOURCE_CATALOG = 'catalog';

    public const string SOURCE_MANUAL = 'manual';

    public function __construct(
        public string $provider,
        public string $name,
        public ?string $title,
        public string $inputPrice,
        public string $outputPrice,
        public ?string $cacheReadPrice,
        public ?string $cacheWritePrice,
        public string $currency,
        public ?int $contextWindow,
        public ?int $maxOutputTokens,
        public bool $supportsStructuredOutput,
        public bool $supportsTools,
        public bool $supportsVision,
        public bool $supportsStreaming,
        public string $source,
        public ?int $connectionId = null,
        public ?string $thinkingOff = null,
    ) {
    }
}

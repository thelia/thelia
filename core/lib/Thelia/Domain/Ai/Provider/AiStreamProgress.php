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

namespace Thelia\Domain\Ai\Provider;

use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiToolCall;

/**
 * What a stream has delivered so far, kept by the adapter while the caller iterates.
 *
 * @internal
 */
#[Exclude]
final class AiStreamProgress
{
    public string $text = '';

    /** @var list<AiToolCall> */
    public array $toolCalls = [];

    public ?StreamResult $stream = null;

    public bool $complete = false;

    /** The provider cut the answer at the limit of output tokens: what came before is kept, the reason is MaxTokens. */
    public bool $truncated = false;

    public function __construct(
        public readonly AiTarget $target,
        public readonly bool $expectsData,
        private readonly AiResultReader $reader,
    ) {
    }

    public function result(): AiProviderResult
    {
        return $this->reader->fromStream($this);
    }
}

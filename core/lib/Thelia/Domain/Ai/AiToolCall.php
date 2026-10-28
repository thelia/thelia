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
 * A call of a tool the model asks for. The module runs the tool, then answers with
 * AiMessage::toolResult($call->id, ...).
 */
#[Exclude]
final readonly class AiToolCall
{
    /**
     * @param array<string, mixed> $arguments decoded from the JSON the model wrote; validate them against
     *                                        the input schema of the tool before using them
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {
        AiAssert::notBlank($id, 'The identifier of a tool call');
        AiAssert::name($name, 'A tool name');
    }
}

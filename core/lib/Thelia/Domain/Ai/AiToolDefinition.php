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
 * A tool the module offers to the model. The gateway only describes it: when the model calls it, the
 * response carries an AiToolCall, the module runs the tool and sends its result back in the next request.
 */
#[Exclude]
final readonly class AiToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema JSON schema of the arguments, `"type": "object"`
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
    ) {
        AiAssert::name($name, 'A tool name');
        AiAssert::notBlank($description, \sprintf('The description of the tool "%s"', $name));
        AiAssert::objectSchema($inputSchema, \sprintf('The input schema of the tool "%s"', $name));
    }
}

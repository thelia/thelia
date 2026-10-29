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
 * The JSON schema the answer must follow. The gateway decodes the answer into AiResponse::$data and
 * checks it against the schema (guardrail `output_schema`) before returning it.
 */
#[Exclude]
final readonly class AiOutputSchema
{
    /**
     * @param array<string, mixed> $schema JSON schema, `"type": "object"` at the root. It is sent to the provider with
     *                                     `strict: true` only when every object of it, nested ones included, has
     *                                     `additionalProperties: false` and lists all its properties in `required`
     *                                     (the rules of OpenAI Structured Outputs); otherwise with `strict: false`
     */
    public function __construct(
        public string $name,
        public array $schema,
    ) {
        AiAssert::name($name, 'A schema name');
        AiAssert::objectSchema($schema, \sprintf('The output schema "%s"', $name));
    }
}

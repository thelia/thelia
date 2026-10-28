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

namespace Thelia\Domain\Ai\Tool\Service;

use Thelia\Domain\Ai\Guardrail\AiSchemaValidator;

/**
 * Checks the arguments of a call against the input schema of the tool.
 *
 * It answers with the place and the keyword that failed (`/limit: maximum`), never with the value: what the
 * model wrote does not come back to it, nor to the log.
 *
 * @internal
 */
final readonly class AiToolArgumentValidator
{
    public function __construct(private AiSchemaValidator $validator)
    {
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $arguments
     *
     * @return list<string> empty when the arguments fit
     */
    public function validate(array $schema, array $arguments): array
    {
        return $this->validator->validate($schema, $arguments);
    }
}

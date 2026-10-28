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

namespace Thelia\Domain\Ai\Tool;

/**
 * A tool that reads. Declare it with #[AsAiTool]. It never changes the shop.
 */
interface AiReadToolInterface
{
    /**
     * The JSON schema of the arguments: `"type": "object"` with `"additionalProperties": false`. The registry
     * validates every call against it before execute() runs, and an empty `properties` goes out as an object.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * @param array<string, mixed> $arguments valid against inputSchema()
     *
     * @return array<string, mixed> an associative array (an empty one is sent as `{}`), at most `maxResultBytes` once encoded
     *
     * @throws Exception\AiToolFailedException when the tool cannot answer: the message goes back to the model as it is
     */
    public function execute(array $arguments, AiToolContext $context): array;
}

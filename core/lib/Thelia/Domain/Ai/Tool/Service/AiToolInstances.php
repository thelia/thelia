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

use Psr\Container\ContainerInterface;
use Thelia\Domain\Ai\Tool\AiReadToolInterface;
use Thelia\Domain\Ai\Tool\AiWriteToolInterface;

/**
 * The services of the declared tools, by name (the locator is set by RegisterAiToolPass).
 *
 * @internal
 */
final readonly class AiToolInstances
{
    public function __construct(private ContainerInterface $locator)
    {
    }

    public function get(string $toolName): AiReadToolInterface|AiWriteToolInterface
    {
        $tool = $this->locator->get($toolName);

        \assert($tool instanceof AiReadToolInterface || $tool instanceof AiWriteToolInterface);

        return $tool;
    }
}

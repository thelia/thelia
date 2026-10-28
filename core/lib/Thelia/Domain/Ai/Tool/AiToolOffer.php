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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiToolDefinition;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * The tools the registry offers to an actor for a usage.
 */
#[Exclude]
final readonly class AiToolOffer
{
    /**
     * @param list<AiToolDefinition> $definitions    for AiRequest::$tools
     * @param AiToolContext          $context        the context to give call() and answer(): the list of the tools offered is stamped on it
     * @param bool                   $lethalTrifecta true when the tools offered together bring private data, untrusted content and a way out of the shop: signalled, never blocked
     */
    public function __construct(
        public array $definitions,
        public AiToolContext $context,
        public bool $lethalTrifecta,
    ) {
        AiAssert::listOf($definitions, AiToolDefinition::class, 'The definitions of a tool offer');
    }
}

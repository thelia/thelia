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

/**
 * A declared tool as the back office and the documentation list it.
 */
#[Exclude]
final readonly class AiToolDescriptor
{
    /**
     * @param string           $module    the code of the module that declares the tool (`Thelia` for the core)
     * @param string|null      $resource  the AdminResources constant that guards the tool
     * @param string|null      $aclModule the module whose right guards the tool
     * @param list<string>     $audiences values of AiAudience
     * @param list<AiToolRisk> $risks
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $module,
        public AiToolEffect $effect,
        public ?string $resource,
        public ?string $aclModule,
        public string $access,
        public array $audiences,
        public array $risks,
        public bool $mcp,
    ) {
    }
}

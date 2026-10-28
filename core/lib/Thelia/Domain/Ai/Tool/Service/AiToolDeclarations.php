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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Core\DependencyInjection\Compiler\RegisterAiToolPass;
use Thelia\Domain\Ai\Tool\Internal\AiToolDeclaration;

/**
 * The declarations of the tools found at build time, read back from the attribute of their class.
 *
 * @internal
 */
final class AiToolDeclarations
{
    /** @var array<string, AiToolDeclaration>|null */
    private ?array $declarations = null;

    /**
     * @param array<string, class-string> $toolClasses tool name => class that declares it
     */
    public function __construct(
        #[Autowire(param: RegisterAiToolPass::PARAMETER)]
        private readonly array $toolClasses,
    ) {
    }

    public function find(string $name): ?AiToolDeclaration
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return array<string, AiToolDeclaration>
     */
    public function all(): array
    {
        if (null === $this->declarations) {
            $this->declarations = [];

            foreach ($this->toolClasses as $name => $class) {
                $this->declarations[$name] = AiToolDeclaration::fromClass($class);
            }
        }

        return $this->declarations;
    }
}

<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaTwig\Extension;

use TheliaTwig\Service\HookService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HookExtension extends AbstractExtension
{
    public function __construct(
        private readonly HookService $hookService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hook', [$this, 'hook'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws \Exception
     */
    public function hook(string $hookName, array $parameters = []): string
    {
        return $this->hookService->processHookFunction($hookName, $parameters);
    }
}

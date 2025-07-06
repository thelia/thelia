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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\Template\Parser\ParserFallback;
use Thelia\Core\Template\Parser\ParserHelperFallback;

/**
 * Class FallbackParserPass.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class FallbackParserPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @api
     */
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('thelia.parser')) {
            return;
        }

        $container->addDefinitions(
            [
                'thelia.parser' => new Definition(ParserFallback::class),
                'thelia.parser.helper' => new Definition(ParserHelperFallback::class),
                'thelia.parser.asset.resolver' => new Definition(ParserHelperFallback::class),
            ]
        );
    }
}

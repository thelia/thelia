<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class FallbackParserPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author manuel raynaud <manu@raynaud.io>
 */
class FallbackParserPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->has('thelia.parser')) {
            return;
        }

        $container->addDefinitions(
            [
                'thelia.parser' => new Definition("Thelia\\Core\\Template\\Parser\\ParserFallback"),
                'thelia.parser.helper' => new Definition("Thelia\\Core\\Template\\Parser\\ParserHelperFallback"),
                'thelia.parser.asset.resolver' => new Definition("Thelia\\Core\\Template\\Parser\\ParserHelperFallback")
            ]
        );
    }
}

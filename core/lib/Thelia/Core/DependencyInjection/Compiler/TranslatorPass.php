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
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class TranslatorPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TranslatorPass implements CompilerPassInterface
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
        if (!$container->hasDefinition('thelia.translator')) {
            return;
        }

        $translator = $container->getDefinition('thelia.translator');

        foreach ($container->findTaggedServiceIds('translation.loader') as $id => $attributes) {
            $translator->addMethodCall('addLoader', array($attributes[0]['alias'], new Reference($id)));
            if (isset($attributes[0]['legacy-alias'])) {
                $translator->addMethodCall('addLoader', array($attributes[0]['legacy-alias'], new Reference($id)));
            }
        }
    }
}

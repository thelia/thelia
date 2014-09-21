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
 * Class RegisterArchiveBuilderPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class RegisterArchiveBuilderPass implements CompilerPassInterface
{
    const MANAGER_DEFINITION = "thelia.manager.archive_builder_manager";

    const SERVICE_TAG = "thelia.archive_builder";

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container Container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(static::MANAGER_DEFINITION)) {
            return;
        }

        $manager = $container->getDefinition(static::MANAGER_DEFINITION);
        $services = $container->findTaggedServiceIds(static::SERVICE_TAG);

        foreach ($services as $id => $condition) {
            $manager->addMethodCall(
                'add',
                array(
                    new Reference($id)
                )
            );
        }
    }
}

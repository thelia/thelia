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
use Thelia\Core\Template\Assets\AsseticAssetManager;

/**
 * Class RegisterAssetFilterPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class RegisterAssetFilterPass implements CompilerPassInterface
{
    const SERVICE_TAG = "thelia.asset.filter";

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container Container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(AsseticAssetManager::class)) {
            return;
        }

        $manager = $container->getDefinition(AsseticAssetManager::class);
        $services = $container->findTaggedServiceIds(static::SERVICE_TAG);

        foreach ($services as $id => $attributes) {
            if (! isset($attributes[0]['key']) || empty($attributes[0]['key'])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Service "%s" must define the "key" attribute on "thelia.asset.filter" tag.',
                        $id
                    )
                );
            }

            $class = $container->getDefinition($id)->getClass();

            if (! is_subclass_of($class, '\Assetic\Contracts\Filter\FilterInterface')) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Service "%s" should implement the \Assetic\Contracts\Filter\FilterInterface interface',
                        $id
                    )
                );
            }

            $manager->addMethodCall(
                'registerAssetFilter',
                [ $attributes[0]['key'], new Reference($id) ]
            );
        }
    }
}

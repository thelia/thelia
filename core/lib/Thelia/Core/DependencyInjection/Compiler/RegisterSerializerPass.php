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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Thelia\Core\Serializer\SerializerManager;

/**
 * Class RegisterSerializerPass
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class RegisterSerializerPass implements CompilerPassInterface
{
    /**
     * @var string Serializer manager service ID
     */
    public const MANAGER_SERVICE_ID = SerializerManager::class;

    /**
     * @var string Serializer tag name
     */
    public const SERIALIZER_SERVICE_TAG = 'thelia.serializer';

    public function process(ContainerBuilder $container)
    {
        try {
            $manager = $container->getDefinition(self::MANAGER_SERVICE_ID);
        } catch (InvalidArgumentException $e) {
            return;
        }

        foreach (array_keys($container->findTaggedServiceIds(self::SERIALIZER_SERVICE_TAG)) as $serviceId) {
            $manager->addMethodCall(
                'add',
                [
                    new Reference($serviceId)
                ]
            );
        }
    }
}

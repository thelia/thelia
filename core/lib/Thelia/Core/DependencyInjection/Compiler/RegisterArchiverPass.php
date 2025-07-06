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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Thelia\Core\Archiver\ArchiverManager;

/**
 * Class RegisterArchiverPass$container.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class RegisterArchiverPass implements CompilerPassInterface
{
    /**
     * @var string Archiver manager service ID
     */
    public const MANAGER_SERVICE_ID = ArchiverManager::class;

    /**
     * @var string Archiver tag name
     */
    public const ARCHIVER_SERVICE_TAG = 'thelia.archiver';

    public function process(ContainerBuilder $container): void
    {
        try {
            $manager = $container->getDefinition(self::MANAGER_SERVICE_ID);
        } catch (InvalidArgumentException) {
            return;
        }

        foreach (array_keys($container->findTaggedServiceIds(self::ARCHIVER_SERVICE_TAG)) as $serviceId) {
            $manager->addMethodCall(
                'add',
                [
                    new Reference($serviceId),
                ]
            );
        }
    }
}

<?php

namespace Thelia\Core\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Thelia\Tools\DIGenerator;

/**
 * First Bundle use in Thelia
 * It initialize dependency injection container.
 *
 * @TODO load configuration from thelia plugin
 * @TODO register database configuration.
 *
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class ModelBundle extends Bundle
{
    /**
     *
     * Construct the depency injection builder
     * 
     * Reference all Model in the Container here
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */

    public function build(ContainerBuilder $container)
    {   
        foreach(DIGenerator::genDiModel(realpath(THELIA_ROOT . "core/lib/Thelia/Model"), array('Base')) as $name => $class)
        {
            $container->register('model.'.$name, $class)
                    ->addArgument(new Reference("database"));
        }
    }
}

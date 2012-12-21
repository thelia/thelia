<?php

namespace Thelia\Core\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

class NotORMBundle extends Bundle
{
    /**
     *
     * Construct the depency injection builder
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */

    public function build(ContainerBuilder $container)
    {
        $config = array();
        
        $kernel = $container->get('kernel');

        $pdo = new \PDO(THELIA_DB_DSN,THELIA_DB_USER, THELIA_DB_PASSWORD, $config);
        
        $pdo->exec("SET NAMES UTF8");
        
        $container->register('database','\Thelia\Database\NotORM')
                ->addArgument($pdo);
        
        if(defined('THELIA_DB_CACHE') && !$kernel->isDebug())
        {
            switch(THELIA_DB_CACHE)
            {
                case 'file':
                    $container->register('database_cache','\NotORM_Cache_File')
                        ->addArgument($kernel->getCacheDir().'/database.php');
                    break;
                case 'include':
                    $container->register('database_cache','\NotORM_Cache_Include')
                        ->addArgument($kernel->getCacheDir().'/database_include.php');
                    break;
                case 'apc':
                    if (extension_loaded('apc'))
                    {
                        $container->register('database_cache','\NotORM_Cache_APC');
                    }
                    break;
                case 'session':
                    $container->register('database_cache','\NotORM_Cache_Session');
                    break;
                case 'memcache':
                    if(class_exists('Memcache'))
                    {
                        $container->register('database_cache','\NotORM_Cache_Memcache')
                                ->addArgument(new \Memcache());
                    }
                    break;
                    
            }
            
            if($container->hasDefinition('database_cache'))
            {
                $container->getDefinition('database')
                        ->addMethodCall('setCache', array(new Reference('database_cache')));
            }
        }
        
        if($kernel->isDebug())
        {
            $debug = function ($query, $parameters)
            {
                echo $query."<br />";
            };
            
            $container->getDefinition('database')
                        ->addMethodCall('setDebug', array($debug));
        }
        
        
    }
}

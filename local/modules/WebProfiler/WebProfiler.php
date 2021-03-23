<?php

namespace WebProfiler;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Module\BaseModule;
use WebProfiler\DataCollector\SmartyDataCollector;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class WebProfiler extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'webprofiler';

    /*
     * You may now override BaseModuleInterface methods, such as:
     * install, destroy, preActivation, postActivation, preDeactivation, postDeactivation
     *
     * Have fun !
     */

    /**
     * Defines how services are loaded in your modules
     *
     * @param ServicesConfigurator $servicesConfigurator
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);


        $servicesConfigurator->set('data_collector.smarty', SmartyDataCollector::class)
            ->args([
                service('thelia.parser')->ignoreOnInvalid()
            ])
            ->tag(
                'data_collector',
                [
                    'template' => "@WebProfilerModule/debug/dataCollector/smarty.html.twig",
                    'id' => 'smarty',
                    'priority' => 42
                ]
            );
    }
}

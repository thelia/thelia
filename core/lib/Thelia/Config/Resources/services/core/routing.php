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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Controller\Front\DefaultController;
use Thelia\Core\EventListener\ViewListener;
use Thelia\Core\Routing\ModuleAnnotationLoader;
use Thelia\Core\Routing\ModuleAttributeLoader;
use Thelia\Core\Routing\ModuleXmlLoader;
use Thelia\Core\Routing\RewritingRouter;
use Thelia\Core\Routing\TemplateAttributeLoader;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ViewListener::class)
        ->args([
            service('thelia.parser.resolver'),
            service('thelia.template_helper'),
            service('request_stack'),
            service('event_dispatcher'),
            service('router.chainRequest'),
        ])
        ->tag('kernel.event_subscriber');

    // Alias for ViewListener
    $services->alias('thelia.listener.view', ViewListener::class);

    // Default controller
    $services->set('controller.default', DefaultController::class)
        ->public();

    // Base admin controller
    $services->set('thelia.admin.base_controller', BaseAdminController::class)
        ->args([
            service('thelia.parser.resolver'),
        ]);

    // Request context
    $services->set('request.context', (string) param('router.request_context.class'))
        ->public();

    // Router file locator
    $services->set('router.fileLocator', FileLocator::class)
        ->args([
            param('thelia.core_dir').'/Config/Resources/routing',
        ])
        ->public();

    // Router XML loader
    $services->set('router.xmlLoader', XmlFileLoader::class)
        ->args([
            service('router.fileLocator'),
        ]);

    // Module file locator
    $services->set('router.module.fileLocator', FileLocator::class)
        ->args([
            param('thelia.module_dir'),
        ]);

    // Module XML loader
    $services->set('router.module.xmlLoader', XmlFileLoader::class)
        ->args([
            service('router.module.fileLocator'),
        ]);

    // Admin router
    $services->set('router.admin', (string) param('router.class'))
        ->args([
            service('router.xmlLoader'),
            'admin.xml',
            [
                'cache_dir' => param('kernel.cache_dir'),
                'debug' => param('kernel.debug'),
            ],
            service('request.context'),
        ])
        ->tag('router.register', ['priority' => 0])
        ->public();

    // Rewriting router
    $services->set('router.rewrite', RewritingRouter::class);

    // Template attribute loader
    $services->set('thelia.loader.template_attributes', TemplateAttributeLoader::class)->public()
        ->tag('routing.loader', ['priority' => 254]);

    // Module attribute loader
    $services->set('thelia.loader.module_attributes', ModuleAttributeLoader::class)->public()
        ->tag('routing.loader', ['priority' => 254]);

    // Module annotation loader
    $services->set('thelia.loader.module_annotations', ModuleAnnotationLoader::class)->public()
        ->tag('routing.loader', ['priority' => 253]);

    // Module XML loader
    $services->set('thelia.loader.module_xml', ModuleXmlLoader::class)
        ->args([
            env('APP_ENV'),
        ])->public()
        ->tag('routing.loader', ['priority' => 252]);

    // Chain request router
    $services->set('router.chainRequest', (string) param('router.chainRouter.class'))
        ->call('setContext', [
            service('request.context'),
        ]);

    // Router listener
    $services->set('listener.router', RouterListener::class)
        ->args([
            service('router.chainRequest'),
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber');
};

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
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Routing\Loader\XmlFileLoader;
use Thelia\Core\Routing\ModuleAttributeLoader;
use Thelia\Core\Routing\ModuleXmlLoader;
use Thelia\Core\Routing\RewritingRouter;
use Thelia\Core\Routing\TemplateAttributeLoader;
use Thelia\Domain\Localization\Service\LangService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Base admin controller
    $services->set('thelia.admin.base_controller', BaseAdminController::class)
        ->args([
            service('thelia.parser.resolver'),
        ]);

    // Request context
    $services->set('request.context', (string) param('router.request_context.class'))
        ->public();

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

    // Rewriting router
    $services->set('router.rewrite', RewritingRouter::class)->args(
        [
            service(LangService::class),
        ]
    );

    // Template attribute loader
    $services->set('thelia.loader.template_attributes', TemplateAttributeLoader::class)->public()
        ->tag('routing.loader', ['priority' => 254]);

    // Module attribute loader
    $services->set('thelia.loader.module_attributes', ModuleAttributeLoader::class)->public()
        ->tag('routing.loader', ['priority' => 254]);

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

    // Router listener.
    //
    // FrameworkBundle registers a RouterListener of its own, on the same
    // events, and its copy answers kernel.request at the same priority 32. At
    // equal priority the order comes down to the order the two were
    // registered in, and whichever runs second returns at once because
    // _controller is already set - so which of the two actually routed the
    // request was left to chance, and only this one has the rewriting router
    // in its chain. One point of priority puts it strictly in front.
    //
    // The events are declared here rather than taken from the class, so the
    // one priority that is deliberately not the class's is visible. The test
    // RouterListenerPriorityTest keeps the list in step with the class.
    $services->set('listener.router', RouterListener::class)
        ->args([
            service('router.chainRequest'),
            service('request_stack'),
        ])
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => 33])
        ->tag('kernel.event_listener', ['event' => KernelEvents::FINISH_REQUEST, 'method' => 'onKernelFinishRequest', 'priority' => 0])
        ->tag('kernel.event_listener', ['event' => KernelEvents::EXCEPTION, 'method' => 'onKernelException', 'priority' => -64]);
};

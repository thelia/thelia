<?php

declare(strict_types=1);

namespace BackOfficeDefaultBundle;

use BackOfficeDefaultBundle\DependencyInjection\Compiler\RegisterAdminFormsPass;
use BackOfficeDefaultBundle\Routing\BackOfficeDefaultAttributeLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\Loader\XmlFileLoader;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class BackOfficeDefaultBundle extends AbstractBundle
{
    public const ACTIVE_TEMPLATE_NAME = 'default';

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services
            ->set('bo_default.routing.attribute_loader', BackOfficeDefaultAttributeLoader::class)
            ->public()
            ->tag('routing.loader', ['priority' => 254]);

        $routingPath = $this->getRoutingPath();

        $services
            ->set('router.fileLocator', FileLocator::class)
            ->args([$routingPath])
            ->public();

        $services
            ->set('router.xmlLoader', XmlFileLoader::class)
            ->args([service('router.fileLocator')]);

        $services
            ->set('router.admin', (string) $builder->getParameter('router.class'))
            ->args([
                service('router.xmlLoader'),
                'admin.xml',
                [
                    'cache_dir' => '%kernel.cache_dir%',
                    'debug' => '%kernel.debug%',
                ],
                service('request.context'),
            ])
            ->tag('router.register', ['priority' => 0])
            ->public();

    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterAdminFormsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    private function getRoutingPath(): string
    {
        return __DIR__.'/Config/Resources/routing';
    }
}

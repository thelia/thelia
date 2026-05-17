<?php

declare(strict_types=1);

namespace BackOfficeDefaultBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class BackOfficeDefaultBundle extends AbstractBundle
{
    public const ACTIVE_TEMPLATE_NAME = 'default';

    private const ADMIN_TEMPLATE_PARAMETER = 'thelia_admin_template';

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->isActive($builder)) {
            return;
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->isActive($builder)) {
            return;
        }
    }

    private function isActive(ContainerBuilder $builder): bool
    {
        if (!$builder->hasParameter(self::ADMIN_TEMPLATE_PARAMETER)) {
            return false;
        }

        return self::ACTIVE_TEMPLATE_NAME === $builder->getParameter(self::ADMIN_TEMPLATE_PARAMETER);
    }
}

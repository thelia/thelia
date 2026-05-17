<?php

declare(strict_types=1);

namespace BackOfficeDefaultBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterAdminFormsPass implements CompilerPassInterface
{
    private const ADMIN_TEMPLATE_PARAMETER = 'thelia_admin_template';

    private const ACTIVE_TEMPLATE_NAME = 'default';

    private const FORMS_PARAMETER = 'Thelia.parser.forms';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::ADMIN_TEMPLATE_PARAMETER)) {
            return;
        }

        if (self::ACTIVE_TEMPLATE_NAME !== $container->getParameter(self::ADMIN_TEMPLATE_PARAMETER)) {
            return;
        }

        $existingForms = $container->hasParameter(self::FORMS_PARAMETER)
            ? (array) $container->getParameter(self::FORMS_PARAMETER)
            : [];

        $adminForms = require \dirname(__DIR__, 2).'/Config/Resources/parameters/forms_admin.php';

        $container->setParameter(self::FORMS_PARAMETER, array_merge($existingForms, $adminForms));
    }
}

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

namespace BackOfficeDefaultTwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class BackOfficeTwigOnlyCompilerPass implements CompilerPassInterface
{
    public const ADMIN_TEMPLATE_PARAMETER = 'thelia_admin_template';

    public const ADMIN_ROUTER_SERVICE = 'router.admin';

    public const PARSER_TAG = 'thelia.parser.template';

    public const TWIG_PARSER_CLASS = 'TwigEngine\\Template\\TwigParser';

    public const TWIG_PARSER_PRIORITY = 100;

    public function __construct(
        private string $activeTemplateName,
        private bool $strictRoutingOverride = false,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$this->isActive($container)) {
            return;
        }

        $this->prioritizeTwigParser($container);

        if ($this->strictRoutingOverride && $container->hasDefinition(self::ADMIN_ROUTER_SERVICE)) {
            $container->removeDefinition(self::ADMIN_ROUTER_SERVICE);
        }
    }

    private function isActive(ContainerBuilder $container): bool
    {
        if (!$container->hasParameter(self::ADMIN_TEMPLATE_PARAMETER)) {
            return false;
        }

        return $this->activeTemplateName === $container->getParameter(self::ADMIN_TEMPLATE_PARAMETER);
    }

    private function prioritizeTwigParser(ContainerBuilder $container): void
    {
        // SmartyParser::supportTemplateRender() returns true even when no file exists
        // (it only guards against path traversal), so Twig must be ordered first when
        // the Twig back-office is active. Otherwise Smarty shadows our .html.twig files.
        if (!$container->hasDefinition(self::TWIG_PARSER_CLASS)) {
            return;
        }

        $definition = $container->getDefinition(self::TWIG_PARSER_CLASS);
        $definition->clearTag(self::PARSER_TAG);
        $definition->addTag(self::PARSER_TAG, ['priority' => self::TWIG_PARSER_PRIORITY]);
    }
}

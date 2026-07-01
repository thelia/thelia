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

use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the Symfony translation catalogs shipped by the email and PDF template packages
 * with the framework translator.
 *
 * Each template package can carry its own translations in
 * templates/{email,pdf}/<name>/translations/<domain>.<locale>.php, so the templates can use
 * the native |trans filter with their own domain (email, pdf) without the catalogs living in
 * the application skeleton. The catalogs travel with the template package.
 */
final class RegisterTemplateTranslationsPass implements CompilerPassInterface
{
    private const TYPES = ['email', 'pdf'];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('translator.default')) {
            return;
        }

        $translator = $container->getDefinition('translator.default');
        $templatesDir = $container->getParameter('kernel.project_dir').'/templates';

        foreach (self::TYPES as $type) {
            $pattern = $templatesDir.'/'.$type.'/*/translations';

            // Track added or removed catalogs so the container is rebuilt when they change.
            $container->addResource(new GlobResource($templatesDir.'/'.$type, '/*/translations/*.php', true));

            foreach (glob($pattern.'/*.php') ?: [] as $file) {
                // File name is "<domain>.<locale>.php", e.g. email.fr_FR.php.
                $parts = explode('.', basename($file, '.php'));

                if (\count($parts) < 2) {
                    continue;
                }

                $locale = array_pop($parts);
                $domain = implode('.', $parts);

                $translator->addMethodCall('addResource', ['php', $file, $locale, $domain]);
            }
        }
    }
}

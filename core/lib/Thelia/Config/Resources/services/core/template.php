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

use Thelia\Core\Template\TheliaTemplateHelper;
use Thelia\Service\Composer\ComposerHelper;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\Parser\ParserFallback;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set('thelia.template_helper', TheliaTemplateHelper::class)
        ->public()
        ->args([
            param('kernel.cache_dir'),
            service(ComposerHelper::class),
        ]);

    $services->alias('thelia.parser.context', ParserContext::class)
        ->public();

    $services->alias(ParserInterface::class, ParserFallback::class)
        ->public();
};

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

namespace Thelia\Core\Template\Assets;

use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;

/**
 * No-op fallback used when no template parser bundle is registered.
 *
 * Every method throws because resolving an asset path requires an active parser.
 * Allows {@see \Thelia\Core\Hook\BaseHook} to declare a typed `$assetsResolver`
 * property without losing the legacy "fallback when no parser" behavior.
 */
class AssetResolverFallback implements AssetResolverInterface
{
    public function resolveAssetURL(string $source, string $file, string $type, ParserInterface $parserInterface, array $filters = [], bool $debug = false, ?string $declaredAssetsDirectory = null, mixed $sourceTemplateName = false): never
    {
        throw new \RuntimeException('if you want to resolve an asset, please register a template parser');
    }

    public function resolveAssetSourcePath(string $source, string $templateName, string $fileName, ParserInterface $parserInterface): never
    {
        throw new \RuntimeException('if you want to resolve an asset, please register a template parser');
    }

    public function resolveAssetSourcePathAndTemplate(string $source, string $templateName, string $fileName, ParserInterface $parserInterface, TemplateDefinition &$templateDefinition): never
    {
        throw new \RuntimeException('if you want to resolve an asset, please register a template parser');
    }

    public function supportParser(ParserInterface $parser): bool
    {
        return false;
    }
}

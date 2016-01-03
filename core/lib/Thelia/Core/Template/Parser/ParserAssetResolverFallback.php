<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Template\Parser;

use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Template\ParserInterface;

/**
 * Class ParserAssetResolverFallback
 * @package Thelia\Core\Template\Parser
 * @author manuel raynaud <manu@raynaud.io>
 */
class ParserAssetResolverFallback implements AssetResolverInterface
{
    /**
     * Generate an asset URL
     *
     * @param string $source a module code, or ParserInterface::TEMPLATE_ASSETS_KEY
     * @param string $file the file path, relative to a template base directory (e.g. assets/css/style.css)
     * @param string $type the asset type, either 'css' or '
     * @param ParserInterface $parserInterface the current template parser
     * @param array $filters the filters to pass to the asset manager
     * @param bool $debug the debug mode
     * @param string $declaredAssetsDirectory if not null, this is the assets directory declared in the {declare_assets} function of a template.
     * @param mixed $sourceTemplateName A template name, of false. If provided, the assets will be searched in this template directory instead of the current one.
     * @return mixed
     */
    public function resolveAssetURL(
        $source,
        $file,
        $type,
        ParserInterface $parserInterface,
        $filters = [],
        $debug = false,
        $declaredAssetsDirectory = null,
        $sourceTemplateName = false
    ) {
        throw new \RuntimeException('if you want to use a parser, please register one');
    }

    /**
     * Return an asset source file path.
     *
     * A system of fallback enables file overriding. It will look for the template :
     *      - in the current template in directory /modules/{module code}/
     *      - in the module in the current template if it exists
     *      - in the module in the default template
     *
     * @param  string $source a module code, or or ParserInterface::TEMPLATE_ASSETS_KEY
     * @param  string $templateName a template name, or false to use the current template
     * @param  string $fileName the filename
     * @param  ParserInterface $parserInterface the current template parser
     *
     * @return mixed the path to directory containing the file, or null if the file doesn't exists.
     */
    public function resolveAssetSourcePath($source, $templateName, $fileName, ParserInterface $parserInterface)
    {
        throw new \RuntimeException('if you want to use a parser, please register one');
    }
}

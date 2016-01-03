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

namespace Thelia\Core\Template;

/**
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 */

interface ParserInterface
{
    // A key to identify assets defined in a template. This will be the name of the directory in which the template
    // assets will be copied and generated in the web cache.
    const TEMPLATE_ASSETS_KEY = 'template-assets';

    public function render($realTemplateName, array $parameters = array(), $compressOutput = true);

    public function renderString($templateText, array $parameters = array(), $compressOutput = true);

    public function getStatus();

    public function setStatus($status);

    /**
     * Setup the parser with a template definition, which provides a template description.
     *
     * @param TemplateDefinition $templateDefinition
     */
    public function setTemplateDefinition(TemplateDefinition $templateDefinition);

    /**
     * Get template definition
     *
     * @param bool $webAssetTemplate Allow to load asset from another template
     *                               If the name of the template if provided
     *
     * @return TemplateDefinition
     */
    public function getTemplateDefinition($webAssetTemplate = false);

    /**
     * Add a template directory to the current template list
     *
     * @param int $templateType the template type (
     *
     * @param string $templateName      the template name
     * @param string $templateDirectory path to the template dirtectory
     * @param string $key               ???
     * @param bool   $unshift           ??? Etienne ?
     */
    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false);

    /**
     * Return the registeted template directories for a givent template type
     *
     * @param  int                       $templateType
     * @throws \InvalidArgumentException if the templateType is not defined
     * @return array:                    an array of defined templates directories for the given template type
     */
    public function getTemplateDirectories($templateType);

    /**
     * Create a variable that will be available in the templates
     *
     * @param string $variable the variable name
     * @param mixed  $value    the value of the variable
     */
    public function assign($variable, $value);

    /**
     * @return \Thelia\Core\Template\TemplateHelperInterface the parser template helper instance
     */
    public function getTemplateHelper();
}

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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Exception\ResourceNotFoundException;

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

    /**
     * Return a rendered template file
     *
     * @param  string                    $realTemplateName the template name (from the template directory)
     * @param  array                     $parameters       an associative array of names / value pairs
     * @return string                    the rendered template text
     * @param  bool                      $compressOutput   if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     * @throws ResourceNotFoundException if the template cannot be found
     * @throws \Exception
     * @throws \SmartyException
     */
    public function render($realTemplateName, array $parameters = [], $compressOutput = true);

    /**
     * Return a rendered template text
     *
     * @param  string $templateText   the template text
     * @param  array  $parameters     an associative array of names / value pairs
     * @param  bool   $compressOutput if true, te output is compressed using trimWhitespaces. If false, no compression occurs
     * @return string the rendered template text
     * @throws \Exception
     * @throws \SmartyException
     */
    public function renderString($templateText, array $parameters = [], $compressOutput = true);

    /**
     * @return int the HTTP status of the response
     */
    public function getStatus();

    /**
     * Sets the HTTP status of the response
     *
     * status An HTTP status (200, 404, ...)
     *
     * @param int $status
     */
    public function setStatus($status);

    /**
     * Returns the request used by the parser
     *
     * @return Request
     */
    public function getRequest();

    /**
     * Set a new template definition, and save the current one
     *
     * @param bool $fallbackToDefaultTemplate if true, resources will be also searched in the "default" template
     * @throws \SmartyException
     */
    public function pushTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false);

    /**
     * Restore the previous stored template definition, if one exists.
     *
     * @throws \SmartyException
     */
    public function popTemplateDefinition();

    /**
     * Setup the parser with a template definition, which provides a template description.
     *
     * @param  bool $fallbackToDefaultTemplate if true, also search files in hte "default" template.
     */
    public function setTemplateDefinition(TemplateDefinition $templateDefinition, $fallbackToDefaultTemplate = false);

    /**
     * Get template definition
     *
     * @param bool|string $webAssetTemplateName false to use the current template path, or a template name to
     *     load assets from this template instead of the current one.
     *
     * @return TemplateDefinition
     */
    public function getTemplateDefinition($webAssetTemplateName = false);

    /**
     * Check if template definition is not null
     *
     * @return boolean
     */
    public function hasTemplateDefinition();

    /**
     * Get the current status of the fallback to "default" feature
     *
     * @return bool
     */
    public function getFallbackToDefaultTemplate();

    /**
     * Add a template directory to the current template list
     *
     * @param int $templateType the template type (
     *
     * @param string $templateName      the template name
     * @param string $templateDirectory path to the template dirtectory
     * @param string $key               the template directory identifier
     * @param bool   $unshift           if true, add template at the top of the list
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
    /**
     * assigns a parser variable. If $variable is an array, it is supposed to contains (variable_name => variable_value) pairs
     *
     * @param array|string $variable the template variable name(s)
     * @param mixed        $value   the value to assign
     */
    public function assign($variable, $value = null);

    /**
     * @return \Thelia\Core\Template\TemplateHelperInterface the parser template helper instance
     */
    public function getTemplateHelper();
}

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

use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;

/**
 * Class ParserFallback
 * @author manuel raynaud <manu@raynaud.io>
 */
class ParserFallback implements ParserInterface
{
    public function render($realTemplateName, array $parameters = array(), $compressOutput = true)
    {
        $this->throwException();
    }

    public function renderString($templateText, array $parameters = array(), $compressOutput = true)
    {
        $this->throwException();
    }

    public function getStatus()
    {
        $this->throwException();
    }

    public function setStatus($status)
    {
        $this->throwException();
    }

    /**
     * Setup the parser with a template definition, which provides a template description.
     *
     * @param TemplateDefinition $templateDefinition
     */
    public function setTemplateDefinition(TemplateDefinition $templateDefinition)
    {
        $this->throwException();
    }

    /**
     * Get template definition
     *
     * @param bool $webAssetTemplate Allow to load asset from another template
     *                               If the name of the template if provided
     *
     * @return TemplateDefinition
     */
    public function getTemplateDefinition($webAssetTemplate = false)
    {
        $this->throwException();
    }

    /**
     * Add a template directory to the current template list
     *
     * @param int $templateType the template type (
     *
     * @param string $templateName the template name
     * @param string $templateDirectory path to the template dirtectory
     * @param string $key ???
     * @param bool $unshift ??? Etienne ?
     */
    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false)
    {
        $this->throwException();
    }

    /**
     * Return the registeted template directories for a givent template type
     *
     * @param  int $templateType
     * @throws \InvalidArgumentException if the templateType is not defined
     * @return array:                    an array of defined templates directories for the given template type
     */
    public function getTemplateDirectories($templateType)
    {
        $this->throwException();
    }

    /**
     * Create a variable that will be available in the templates
     *
     * @param string $variable the variable name
     * @param mixed $value the value of the variable
     */
    public function assign($variable, $value)
    {
        $this->throwException();
    }

    /**
     * @return \Thelia\Core\Template\TemplateHelperInterface the parser template helper instance
     */
    public function getTemplateHelper()
    {
        $this->throwException();
    }

    private function throwException()
    {
        throw new \RuntimeException('if you want to use a parser, please register one');
    }
}

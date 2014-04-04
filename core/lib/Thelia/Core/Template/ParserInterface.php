<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\Template;

/**
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 *
 */

interface ParserInterface
{
    public function render($realTemplateName, array $parameters = array());

    public function setContent($content);

    public function getStatus();

    public function setStatus($status);

    /**
     * Add a template directory to the current template list
     *
     * @param unknown $templateType the template type (
     *
     * @param string  $templateName      the template name
     * @param string  $templateDirectory path to the template dirtectory
     * @param unknown $key               ???
     * @param string  $unshift           ??? Etienne ?
     */
    public function addTemplateDirectory($templateType, $templateName, $templateDirectory, $key, $unshift = false);

    /**
     * Return the registeted template directories for a givent template type
     *
     * @param  unknown                  $templateType
     * @throws InvalidArgumentException if the tempmateType is not defined
     * @return array:                   an array of defined templates directories for the given template type
     */
    public function getTemplateDirectories($templateType);

    /**
     * Create a variable that will be available in the templates
     *
     * @param string $variable the variable name
     * @param mixed $value the value of the variable
     */
    public function assign($variable, $value);
}

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

namespace Thelia\Controller\Api;

use Thelia\Controller\a;
use Thelia\Controller\BaseController;
use Thelia\Controller\the;


/**
 * Class BaseApiController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class BaseApiController extends BaseController
{

    /**
     * @return a ParserInterface instance parser
     */
    protected function getParser($template = null)
    {
        // TODO: Implement getParser() method.
    }

    /**
     * Render the given template, and returns the result as an Http Response.
     *
     * @param $templateName the complete template name, with extension
     * @param  array $args the template arguments
     * @param  int $status http code status
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function render($templateName, $args = array(), $status = 200)
    {
        // TODO: Implement render() method.
    }

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param $templateName the complete template name, with extension
     * @param array $args the template arguments
     * @param null $templateDir
     *
     * @return string
     */
    protected function renderRaw($templateName, $args = array(), $templateDir = null)
    {
        // TODO: Implement renderRaw() method.
    }
}
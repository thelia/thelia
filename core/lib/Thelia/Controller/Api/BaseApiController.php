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

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Thelia\Controller\a;
use Thelia\Controller\BaseController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\Api;

/**
 * Class BaseApiController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class BaseApiController extends BaseController
{
    protected $apiUser;
    protected $currentRouter = "router.api";

    protected function checkAuth($resources, $modules, $accesses)
    {
        $resources = is_array($resources) ? $resources : array($resources);
        $modules = is_array($modules) ? $modules : array($modules);
        $accesses = is_array($accesses) ? $accesses : array($accesses);

        if (true !== $this->getSecurityContext()->isUserGranted(array("API"), $resources, $modules, $accesses, $this->getApiUser())) {
            throw new AccessDeniedHttpException();
        }
    }

    public function setApiUser(Api $apiUser)
    {
        $this->apiUser = $apiUser;
    }

    public function getApiUser()
    {
        return $this->apiUser;
    }

    /**
     * @return a ParserInterface instance parser
     */
    protected function getParser($template = null)
    {
        throw new \RuntimeException("The parser is not available here");
    }

    /**
     * Render the given template, and returns the result as an Http Response.
     *
     * @param $content
     * @param  array                                $args   the template arguments
     * @param  int                                  $status http code status
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function render($content, $args = array(), $status = 200, $headers = array())
    {
        return Response::create($this->renderRaw($content), $status, $headers);
    }

    /**
     * Render the given template, and returns the result as a string.
     *
     * @param $content
     * @param array $args        the template arguments
     * @param null  $templateDir
     *
     * @return string
     */
    protected function renderRaw($content, $args = array(), $templateDir = null)
    {
        if (is_array($content)) {
            $content = json_encode($content);
        }

        return $content;
    }
}

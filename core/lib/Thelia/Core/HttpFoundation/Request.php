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

namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\Request as BaseRequest;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Controller\Api\BaseApiController;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Model\ConfigQuery;

/**
 * extends Symfony\Component\HttpFoundation\Request for adding some helpers
 *
 * Class Request
 * @package Thelia\Core\HttpFoundation
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Request extends BaseRequest
{
    /** @var string Path info without trailing slash */
    private $resolvedPathInfo;

    /** @var string */
    protected $controllerType = null;

    /**
     * @
     * {@inheritdoc} Including Thelia request properties
     */
    public function initialize(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ) {
        parent::initialize($query, $request, $attributes, $cookies, $files, $server, $content);

        $this->resolvedPathInfo = null;
    }

    /**
     * Filter PathInfo to allow slash ending uri
     *
     * example:
     * /admin will be the same as /admin/
     */
    public function getPathInfo()
    {
        $pathInfo = parent::getPathInfo();
        $pathLength = strlen($pathInfo);

        if ($pathInfo !== '/' && $pathInfo[$pathLength - 1] === '/'
            && (bool) ConfigQuery::read('allow_slash_ended_uri', false)
        ) {
            if (null === $this->resolvedPathInfo) {
                $this->resolvedPathInfo = substr($pathInfo, 0, $pathLength - 1); // Remove the slash
            }

            $pathInfo = $this->resolvedPathInfo;
        }

        return $pathInfo;
    }

    public function getRealPathInfo()
    {
        return parent::getPathInfo();
    }

    public function getProductId()
    {
        return $this->get("product_id");
    }

    public function getUriAddingParameters(array $parameters = null)
    {
        $uri = $this->getUri();

        $additionalQs = '';

        foreach ($parameters as $key => $value) {
            $additionalQs .= sprintf("&%s=%s", $key, $value);
        }

        if ('' == $this->getQueryString()) {
            $additionalQs = '?'. ltrim($additionalQs, '&');
        }

        return $uri . $additionalQs;
    }

    public function toString($withContent = true)
    {
        $string =
            sprintf('%s %s %s', $this->getMethod(), $this->getRequestUri(), $this->server->get('SERVER_PROTOCOL'))
            . "\r\n" . $this->headers . "\r\n"
        ;

        if (true === $withContent) {
            $string .= $this->getContent();
        }

        return $string;
    }

    /**
     * @param string $controllerType
     */
    public function setControllerType($controllerType)
    {
        $this->controllerType = $controllerType;
    }

    /**
     * Detects where does the request
     *
     * <code>
     * // Detect if the request comes from the api
     * if ($request->fromControllerType(BaseApiController::CONTROLLER_TYPE)) {...}
     * </code>
     *
     * @param $controllerType
     * @return bool
     */
    public function fromControllerType($controllerType)
    {
        return $this->controllerType === $controllerType;
    }

    /**
     * Detect if the request comes from the api
     *
     * @return bool
     */
    public function fromApi()
    {
        return $this->controllerType === BaseApiController::CONTROLLER_TYPE;
    }

    /**
     * Detect if the request comes from the admin
     *
     * @return bool
     */
    public function fromAdmin()
    {
        return $this->controllerType === BaseAdminController::CONTROLLER_TYPE;
    }

    /**
     * Detect if the request comes from the front
     *
     * @return bool
     */
    public function fromFront()
    {
        return $this->controllerType === BaseFrontController::CONTROLLER_TYPE;
    }

    /**
     * From a Thelia request, we always return a Thelia Session object.
     *
     * @return null|\Thelia\Core\HttpFoundation\Session\Session
     */
    public function getSession()
    {
        return parent::getSession();
    }
}

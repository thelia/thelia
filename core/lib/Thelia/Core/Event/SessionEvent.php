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

namespace Thelia\Core\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class SessionEvent
 * @package Thelia\Core\Event
 * @author manuel raynaud <manu@raynaud.io>
 * @author gilles bourgeat <gbourgeat@openstudio.fr>
 */
class SessionEvent extends ActionEvent
{
    /** @var string */
    protected $cacheDir;

    /** @var string */
    protected $env;

    /** @var bool */
    protected $debug;

    /** @var Session */
    protected $session;

    /** @var Request */
    protected $request;

    /**
     * @param string $cacheDir the cache directory for the current request
     * @param boolean $debug debug for the current request
     * @param string $env environment for the current request
     */
    public function __construct($cacheDir, $debug, $env, Request $request = null)
    {
        $this->cacheDir = $cacheDir;
        $this->debug = $debug;
        $this->env = $env;
        $this->request = $request;
    }

    /**
     * @return string the current environment
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * @return bool the current debug mode
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * @param mixed $session
     */
    public function setSession(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @return mixed
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * @param Request $request
     * @since 2.4
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return Request
     * @since 2.4
     */
    public function getRequest()
    {
        return $this->request;
    }
}

<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class SessionEvent.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class SessionEvent extends ActionEvent
{
    protected $cacheDir;

    protected $env;

    protected $debug;

    protected $session;

    /**
     * @param string $cacheDir the cache directory for the current request
     * @param bool   $debug    debug for the current request
     * @param string $env      environment for the current request
     */
    public function __construct($cacheDir, $debug, $env)
    {
        $this->cacheDir = $cacheDir;
        $this->debug = $debug;
        $this->env = $env;
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

    public function getSession()
    {
        return $this->session;
    }
}

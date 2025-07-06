<?php

declare(strict_types=1);

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
    protected $session;

    /**
     * @param string $cacheDir the cache directory for the current request
     * @param bool   $debug    debug for the current request
     * @param string $env      environment for the current request
     */
    public function __construct(protected $cacheDir, protected $debug, protected $env)
    {
    }

    /**
     * @return string the current environment
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    /**
     * @return bool the current debug mode
     */
    public function getDebug(): bool
    {
        return $this->debug;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * @param mixed $session
     */
    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    public function getSession()
    {
        return $this->session;
    }
}

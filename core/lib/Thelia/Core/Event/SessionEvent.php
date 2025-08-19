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

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;

/**
 * Class SessionEvent.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class SessionEvent extends ActionEvent
{
    protected ?Session $session = null;

    public function __construct(
        protected string $cacheDir,
        protected bool $debug,
        protected string $env,
        protected Request $request,
    ) {
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
    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}

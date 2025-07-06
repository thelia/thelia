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

use Thelia\Core\HttpFoundation\Request;

class IsAdminEnvEvent extends ActionEvent
{
    private bool $isAdminEnv = false;

    public function __construct(protected Request $request)
    {
        if (preg_match('#/admin/?.*#', $this->request->getPathInfo())) {
            $this->isAdminEnv = true;
        }
    }

    public function setIsAdminEnv(bool $isAdminEnv): void
    {
        $this->isAdminEnv = $isAdminEnv;
    }

    public function isAdminEnv(): bool
    {
        return $this->isAdminEnv;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}

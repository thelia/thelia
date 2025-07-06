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
    /**
     * @var bool
     */
    private $isAdminEnv = false;

    public function __construct(protected Request $request)
    {
        if (preg_match('#/admin/?.*#', $this->request->getPathInfo())) {
            $this->isAdminEnv = true;
        }
    }

    /**
     * @param bool $isAdminEnv
     */
    public function setIsAdminEnv($isAdminEnv): void
    {
        $this->isAdminEnv = $isAdminEnv;
    }

    /**
     * @return bool
     */
    public function isAdminEnv()
    {
        return $this->isAdminEnv;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}

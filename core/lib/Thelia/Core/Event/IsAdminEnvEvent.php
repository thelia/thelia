<?php

namespace Thelia\Core\Event;

use Thelia\Core\HttpFoundation\Request;

class IsAdminEnvEvent extends ActionEvent
{
    /**
     * @var bool
     */
    private $isAdminEnv = false;

    /** @var Request */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;

        if (preg_match('#/admin/?.*#', $request->getPathInfo())) {
            $this->isAdminEnv = true;
        }
    }

    /**
     * @param bool $isAdminEnv
     */
    public function setIsAdminEnv($isAdminEnv)
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

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}
<?php

namespace Thelia\Model;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Base\Address as BaseAddress;

class Address extends BaseAddress {

    protected $dispatcher;

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

}

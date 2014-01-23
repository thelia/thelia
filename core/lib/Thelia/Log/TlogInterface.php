<?php

namespace Thelia\Log;

Interface TlogInterface
{

    public function trace();
    public function debug();
    public function info();
    public function warning();
    public function error();
    public function fatal();

}

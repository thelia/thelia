<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 18/06/13
 * Time: 22:41
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Core\Template\Smarty;


class RegisterSmartyPlugin {
    public $type;
    public $name;
    public $class;
    public $method;

    public function __construct($type, $name, $class, $method)
    {
        $this->type = $type;
        $this->name = $name;
        $this->class = $class;
        $this->method = $method;
    }
}
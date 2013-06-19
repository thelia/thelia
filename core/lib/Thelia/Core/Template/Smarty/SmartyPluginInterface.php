<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 18/06/13
 * Time: 22:38
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Core\Template\Smarty;


interface SmartyPluginInterface {
    /**
     * @return mixed
     */
    public function registerPlugins();

}
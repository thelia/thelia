<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 15/03/13
 * Time: 09:23
 * To change this template use File | Settings | File Templates.
 */

namespace Test\Loop;

use Thelia\Tpex\Element\Loop\BaseLoop;
use Thelia\Tpex\Tools;
use Thelia\Model\ProductQuery;

class Doobitch extends BaseLoop {

    public function exec($text, $args)
    {

        $param1 = Tools::extractValueParam("param1", $args);

        $res = "";
        if($param1 == 2 || $param1 == 3) {
            for($i = 0; $i < 4; $i++) {
                $tmp = str_replace("#ALFRED", "foo".$i, $text);
                if($i%2){
                    $tmp = str_replace("#CHAPO", "bar".$i, $tmp);
                } else {
                    $tmp = str_replace("#CHAPO", "", $tmp);
                }


                $res .= $tmp;
            }
        }

        return $res;
    }
}
<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 14/03/13
 * Time: 15:16
 * To change this template use File | Settings | File Templates.
 */

namespace Test\Loop;

use Thelia\Tpex\Element\Loop\BaseLoop;

class Foo extends BaseLoop {

    public function exec($text, $args)
    {
        $res = "";
        for($i = 0; $i < 4; $i++) {
            $tmp = str_replace("#TOTO", "toto".$i, $text);
            $tmp = str_replace("#TUTU", "tutu".$i, $tmp);

            $res .= $tmp;
        }

        return $res;
    }
}
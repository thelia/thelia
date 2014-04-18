<?php

namespace Cbatos\Loop;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Translation\Translator;

class CheckRightsLoop extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection();
    }

    public function buildArray()
    {
        $ret = array();
        $dir = __DIR__."/../Config/";
        if (!is_readable($dir)) {
            $ret[] = array("ERRMES"=>Translator::getInstance()->trans("Can't read Config directory"), "ERRFILE"=>"");
        }
        if (!is_writable($dir)) {
            $ret[] = array("ERRMES"=>Translator::getInstance()->trans("Can't write Config directory"), "ERRFILE"=>"");
        }
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (strlen($file) > 5 && substr($file, -5) === ".json") {
                    if (!is_readable($dir.$file)) {
                        $ret[] = array("ERRMES"=>Translator::getInstance()->trans("Can't read file"), "ERRFILE"=>"Cbatos/Config/".$file);
                    }
                    if (!is_writable($dir.$file)) {
                        $ret[] = array("ERRMES"=>Translator::getInstance()->trans("Can't write file"), "ERRFILE"=>"Cbatos/Config/".$file);
                    }
                }
            }
        }

        return $ret;
    }
    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $arr) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ERRMES", $arr["ERRMES"])
                ->set("ERRFILE", $arr["ERRFILE"]);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

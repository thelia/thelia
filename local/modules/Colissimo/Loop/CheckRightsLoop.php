<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/
namespace Colissimo\Loop;

use Colissimo\Colissimo;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Translation\Translator;

/**
 * Class CheckRightsLoop
 * @package Colissimo\Looop
 * @author Thelia <info@thelia.net>
 */
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

            $ret[] = array(
                "ERRMES"=>Translator::getInstance()->trans(
                    "Can't read Config directory",
                    [],
                    Colissimo::DOMAIN_NAME
                ),
                "ERRFILE"=>""
            );
        }
        if (!is_writable($dir)) {
            $ret[] = array(
                "ERRMES"=>Translator::getInstance()->trans(
                    "Can't write Config directory",
                    [],
                    Colissimo::DOMAIN_NAME
                ),
                "ERRFILE"=>""
            );

        }
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (strlen($file) > 5 && substr($file, -5) === ".json") {
                    if (!is_readable($dir.$file)) {

                        $ret[] = array(
                            "ERRMES"=>Translator::getInstance()->trans(
                                "Can't read file",
                                [],
                                Colissimo::DOMAIN_NAME
                            ),
                            "ERRFILE"=>"Colissimo/Config/".$file
                        );
                    }
                    if (!is_writable($dir.$file)) {
                        $ret[] = array(
                            "ERRMES"=>Translator::getInstance()->trans(
                                "Can't write file",
                                [],
                                Colissimo::DOMAIN_NAME
                            ),
                                "ERRFILE"=>"Colissimo/Config/".$file
                        );

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

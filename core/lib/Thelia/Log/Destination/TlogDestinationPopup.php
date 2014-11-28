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

namespace Thelia\Log\Destination;

use Thelia\Log\AbstractTlogDestination;
use Thelia\Log\TlogDestinationConfig;

class TlogDestinationPopup extends AbstractTlogDestination
{
    // Nom des variables de configuration
    // ----------------------------------
    const VAR_POPUP_WIDTH = "tlog_destinationpopup_width";
    const VALEUR_POPUP_WIDTH_DEFAUT = "600";

    const VAR_POPUP_HEIGHT = "tlog_destinationpopup_height";
    const VALEUR_POPUP_HEIGHT_DEFAUT = "600";

    const VAR_POPUP_TPL = "tlog_destinationpopup_template";
    // Ce fichier doit se trouver dans le même répertoire que TlogDestinationPopup.class.php
    const VALEUR_POPUP_TPL_DEFAUT = "TlogDestinationPopup.tpl";

    public function getTitle()
    {
        return "Javascript popup window";
    }

    public function getDescription()
    {
        return "Display logs in a popup window, separate from the main window .";
    }

    public function getConfigs()
    {
        return array(
            new TlogDestinationConfig(
                self::VAR_POPUP_TPL,
                "Popup windows template",
                "Put #LOGTEXT in the template text where you want to display logs..",
                file_get_contents(__DIR__.DS. self::VALEUR_POPUP_TPL_DEFAUT),
                TlogDestinationConfig::TYPE_TEXTAREA
            ),
            new TlogDestinationConfig(
                self::VAR_POPUP_HEIGHT,
                "Height of the popup window",
                "In pixels",
                self::VALEUR_POPUP_HEIGHT_DEFAUT,
                TlogDestinationConfig::TYPE_TEXTFIELD
            ),
            new TlogDestinationConfig(
                self::VAR_POPUP_WIDTH,
                "Width of the popup window",
                "In pixels",
                self::VALEUR_POPUP_WIDTH_DEFAUT,
                TlogDestinationConfig::TYPE_TEXTFIELD
            )
        );
    }

    public function write(&$res)
    {
        $content = "";
        $count = 1;

        foreach ($this->logs as $line) {
            $content .= "<div class=\"".($count++ % 2 ? "paire" : "impaire")."\">".htmlspecialchars($line)."</div>";
        }

        $tpl = $this->getConfig(self::VAR_POPUP_TPL);

        $tpl = str_replace('#LOGTEXT', $content, $tpl);
        $tpl = str_replace(array("\r\n", "\r", "\n"), '\\n', $tpl);

        $wop = sprintf(
            '<script>
                _thelia_console = window.open("","thelia_console","width=%s,height=%s,resizable,scrollbars=yes");
                if (_thelia_console == null) {
                   alert("The log popup window could not be opened. Please disable your popup blocker for this site.");
                } else {
                    _thelia_console.document.write("%s");
                    _thelia_console.document.close();
                }
            </script>',
            $this->getConfig(self::VAR_POPUP_WIDTH),
            $this->getConfig(self::VAR_POPUP_HEIGHT),
            str_replace('"', '\\"', $tpl)
        );

        if (preg_match("|</body>|i", $res)) {
            $res = preg_replace("|</body>|i", "$wop\n</body>", $res);
        } else {
            $res .= $wop;
        }
    }
}

<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                            		 */
/*                                                                                   */
/*      Copyright (c) OpenStudio		                                             */
/*		email : thelia@openstudio.fr		        	                          	 */
/*      web : http://www.openstudio.fr						   						 */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program.  If not, see <http://www.gnu.org/licenses/>.		 */
/*                                                                                   */
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
        $content = ""; $count = 1;

        foreach ($this->_logs as $line) {
            $content .= "<div class=\"".($count++ % 2 ? "paire" : "impaire")."\">".htmlspecialchars($line)."</div>";
        }

        $tpl = $this->getConfig(self::VAR_POPUP_TPL);

        $tpl = str_replace('#LOGTEXT', $content, $tpl);
        $tpl = str_replace(array("\r\n", "\r", "\n"), '\\n', $tpl);

        $wop = sprintf('
                <script>
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

        if (preg_match("|</body>|i", $res))
            $res = preg_replace("|</body>|i", "$wop\n</body>", $res);
        else
            $res .= $wop;
   }
}

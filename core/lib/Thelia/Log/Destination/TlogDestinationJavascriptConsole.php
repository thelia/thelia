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

class TlogDestinationJavascriptConsole extends AbstractTlogDestination
{
    public function getTitle()
    {
        return "Browser's Javascript console";
    }

    public function getDescription()
    {
        return "The Thelia logs are displayed in your browser's Javascript console.";
    }

    public function write(&$res)
    {
        $content = '<script>try {'."\n";

        foreach ($this->_logs as $line) {
            $content .= "console.log('".str_replace("'", "\\'", str_replace(array("\r\n", "\r", "\n"), '\\n', $line))."');\n";
        }

        $content .= '} catch (ex) { alert("Les logs Thelia ne peuvent être affichés dans la console javascript:" + ex); }</script>'."\n";

        if (preg_match("|</body>|i", $res))
            $res = preg_replace("|</body>|i", "$content</html>", $res);
   }
}

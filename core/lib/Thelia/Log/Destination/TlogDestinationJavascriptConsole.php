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

        foreach ($this->logs as $line) {
            $content .= "console.log('".str_replace("'", "\\'", str_replace(array("\r\n", "\r", "\n"), '\\n', $line))."');\n";
        }

        $content .= '} catch (ex) { alert("Les logs Thelia ne peuvent être affichés dans la console javascript:" + ex); }</script>'."\n";

        if (preg_match("|</body>|i", $res)) {
            $res = preg_replace("|</body>|i", "$content</html>", $res);
        }
    }
}

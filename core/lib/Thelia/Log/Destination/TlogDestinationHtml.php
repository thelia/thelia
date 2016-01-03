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

class TlogDestinationHtml extends AbstractTlogDestination
{
    // Nom des variables de configuration
    // ----------------------------------
    const VAR_STYLE = "tlog_destinationhtml_style";
    const VALEUR_STYLE_DEFAUT = "text-align: left; font-size: 12px; font-weight: normal; line-height: 14px; float: none; display:block; color: #000; background-color: #fff; font-family: Courier New, courier,fixed;";

    private $style;

    public function __construct()
    {
        parent::__construct();
    }

    public function configure()
    {
        $this->style = $this->getConfig(self::VAR_STYLE);
    }

    public function getTitle()
    {
        return "Direct HTML display";
    }

    public function getDescription()
    {
        return "Display logs in HTML format, on top of generated pages.";
    }

    public function getConfigs()
    {
        return array(
            new TlogDestinationConfig(
                self::VAR_STYLE,
                "CSS of each log line",
                "You may also leave this field empty, and define a \"tlog-trace\" style in your CSS.",
                self::VALEUR_STYLE_DEFAUT,
                TlogDestinationConfig::TYPE_TEXTAREA
            )
        );
    }

    public function write(&$res)
    {
        $block = sprintf('<pre class="tlog-trace" style="%s">%s</pre>', $this->style, htmlspecialchars(implode("\n", $this->logs)));

        $this->insertAfterBody($res, $block);
    }
}

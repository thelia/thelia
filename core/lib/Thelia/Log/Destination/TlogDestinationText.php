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

class TlogDestinationText extends AbstractTlogDestination
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getTitle()
    {
        return "Direct text display";
    }

    public function getDescription()
    {
        return "Display logs in raw text format, on top of generated pages.";
    }

    public function add($texte)
    {
        echo trim($texte)."\n";
    }

    public function write(&$res)
    {
        // Rien
    }
}

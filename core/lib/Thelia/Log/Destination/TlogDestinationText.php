<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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

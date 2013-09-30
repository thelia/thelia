<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Feature;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Feature;

class FeatureEvent extends ActionEvent
{
    protected $feature = null;

    public function __construct(Feature $feature = null)
    {
        $this->feature = $feature;
    }

    public function hasFeature()
    {
        return ! is_null($this->feature);
    }

    public function getFeature()
    {
        return $this->feature;
    }

    public function setFeature($feature)
    {
        $this->feature = $feature;

        return $this;
    }
}

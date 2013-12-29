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
use Thelia\Model\FeatureAv;

class FeatureAvEvent extends ActionEvent
{
    protected $featureAv = null;

    public function __construct(FeatureAv $featureAv = null)
    {
        $this->featureAv = $featureAv;
    }

    public function hasFeatureAv()
    {
        return ! is_null($this->featureAv);
    }

    public function getFeatureAv()
    {
        return $this->featureAv;
    }

    public function setFeatureAv($featureAv)
    {
        $this->featureAv = $featureAv;

        return $this;
    }
}

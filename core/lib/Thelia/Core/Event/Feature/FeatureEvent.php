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

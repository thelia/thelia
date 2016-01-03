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

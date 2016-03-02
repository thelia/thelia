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

class FeatureAvDeleteEvent extends FeatureAvEvent
{
    /** @var int */
    protected $featureAv_id;

    /**
     * @param int $featureAv_id
     */
    public function __construct($featureAv_id)
    {
        $this->setFeatureAvId($featureAv_id);
    }

    public function getFeatureAvId()
    {
        return $this->featureAv_id;
    }

    public function setFeatureAvId($featureAv_id)
    {
        $this->featureAv_id = $featureAv_id;

        return $this;
    }
}

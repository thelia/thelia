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

class FeatureDeleteEvent extends FeatureEvent
{
    /** @var int */
    protected $feature_id;

    /**
     * @param int $feature_id
     */
    public function __construct($feature_id)
    {
        $this->setFeatureId($feature_id);
    }

    public function getFeatureId()
    {
        return $this->feature_id;
    }

    public function setFeatureId($feature_id)
    {
        $this->feature_id = $feature_id;

        return $this;
    }
}

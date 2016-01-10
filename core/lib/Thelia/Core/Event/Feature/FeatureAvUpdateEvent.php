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

class FeatureAvUpdateEvent extends FeatureAvCreateEvent
{
    /** @var int */
    protected $featureAv_id;

    protected $description;
    protected $chapo;
    protected $postscriptum;

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

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }
}

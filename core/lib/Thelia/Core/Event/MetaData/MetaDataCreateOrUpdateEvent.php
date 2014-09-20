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

namespace Thelia\Core\Event\MetaData;

/**
 * Class MetaDataCreateOrUpdateEvent
 * @package Thelia\Core\Event\MetaData
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaDataCreateOrUpdateEvent extends MetaDataDeleteEvent
{
    protected $value = null;

    public function __construct($metaKey = null, $elementKey = null, $elementId = null, $value = null)
    {
        parent::__construct($metaKey, $elementKey, $elementId);

        $this->value      = $value;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}

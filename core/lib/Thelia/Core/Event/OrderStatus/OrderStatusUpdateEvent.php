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

namespace Thelia\Core\Event\OrderStatus;

/**
 * Class OrderStatusUpdateEvent
 * @package Thelia\Core\Event\OrderStatus
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class OrderStatusUpdateEvent extends OrderStatusEvent
{
    /** @var int */
    protected $id;

    /**
     * OrderStatusUpdateEvent constructor.
     * @param int $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return OrderStatusUpdateEvent
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
}

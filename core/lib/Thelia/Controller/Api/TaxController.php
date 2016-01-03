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

namespace Thelia\Controller\Api;

use Symfony\Component\EventDispatcher\Event;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Tax;

/**
 * Class TaxController
 * @package Thelia\Controller\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TaxController extends AbstractCrudApiController
{
    public function __construct()
    {
        parent::__construct(
            "tax",
            AdminResources::TAX,
            [],
            [],
            []
        );
    }

    /**
     * @return \Thelia\Core\Template\Element\BaseLoop
     *
     * Get the entity loop instance
     */
    protected function getLoop()
    {
        return new Tax($this->container);
    }

    /**
     * @TODO: implement Create - Update - Delete
     */

    /**
     * @param array $data
     * @return \Thelia\Form\BaseForm
     *
     * Gives the form used for entities creation.
     */
    protected function getCreationForm(array $data = array())
    {
    }

    /**
     * @param array $data
     * @return \Thelia\Form\BaseForm
     *
     * Gives the form used for entities update
     */
    protected function getUpdateForm(array $data = array())
    {
    }

    /**
     * @param Event $event
     * @return null|mixed
     *
     * Get the object from the event
     *
     * if return null or false, the action will throw a 404
     */
    protected function extractObjectFromEvent(Event $event)
    {
    }

    /**
     * @param array $data
     * @return \Symfony\Component\EventDispatcher\Event
     *
     * Hydrates an event object to dispatch on creation.
     */
    protected function getCreationEvent(array &$data)
    {
    }

    /**
     * @param array $data
     * @return \Symfony\Component\EventDispatcher\Event
     *
     * Hydrates an event object to dispatch on update.
     */
    protected function getUpdateEvent(array &$data)
    {
    }

    /**
     * @param mixed $entityId
     * @return \Symfony\Component\EventDispatcher\Event
     *
     * Hydrates an event object to dispatch on entity deletion.
     */
    protected function getDeleteEvent($entityId)
    {
    }
}

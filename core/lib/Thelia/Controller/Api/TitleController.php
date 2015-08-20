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
use Symfony\Component\Form\FormEvent;
use Thelia\Core\Event\CustomerTitle\CustomerTitleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Title;
use Thelia\Model\CustomerTitleI18nQuery;
use Thelia\Model\CustomerTitleQuery;

/**
 * Class TitleController
 * @package Thelia\Controller\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TitleController extends AbstractCrudApiController
{
    public function __construct()
    {
        parent::__construct(
            "customer title",
            AdminResources::TITLE,
            [
                TheliaEvents::CUSTOMER_TITLE_BEFORE_CREATE,
                TheliaEvents::CUSTOMER_TITLE_CREATE,
                TheliaEvents::CUSTOMER_TITLE_AFTER_CREATE,
            ],
            [
                TheliaEvents::CUSTOMER_TITLE_BEFORE_UPDATE,
                TheliaEvents::CUSTOMER_TITLE_UPDATE,
                TheliaEvents::CUSTOMER_TITLE_AFTER_UPDATE,
            ],
            TheliaEvents::CUSTOMER_TITLE_DELETE
        );
    }


    /**
     * @return \Thelia\Core\Template\Element\BaseLoop
     *
     * Get the entity loop instance
     */
    protected function getLoop()
    {
        return new Title($this->container);
    }

    /**
     * @param array $data
     * @return \Thelia\Form\BaseForm
     */
    protected function getCreationForm(array $data = array())
    {
        return $this->createForm(null, "customer_title", $data);
    }

    /**
     * @param array $data
     * @return \Thelia\Form\BaseForm
     */
    protected function getUpdateForm(array $data = array())
    {
        return $this->createForm(null, "customer_title", $data, array(
            "validation_groups" => ["Default", "update"],
            "method" => "PUT",
        ));
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
        return $event->getCustomerTitle();
    }

    /**
     * @param array $data
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getCreationEvent(array &$data)
    {
        return $this->createEvent($data);
    }

    /**
     * @param array $data
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getUpdateEvent(array &$data)
    {
        return $this->createEvent($data);
    }

    /**
     * @param mixed $entityId
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getDeleteEvent($entityId)
    {
        $data = ["title_id" => $entityId];

        return $this->createEvent($data);
    }

    /**
     * @param array $data
     * @return CustomerTitleEvent
     *
     * Handler to create the customer title event
     */
    protected function createEvent(array &$data)
    {
        $event = new CustomerTitleEvent();

        if (isset($data["default"])) {
            $event->setDefault($data["default"]);
        }

        if (isset($data["title_id"])) {
            $event->setCustomerTitle(CustomerTitleQuery::create()->findPk($data["title_id"]));
        }

        if (isset($data["i18n"]) && !empty($data["i18n"])) {
            $row = array_shift($data["i18n"]);

            $this->hydrateEvent($row, $event);
        }

        return $event;
    }

    /**
     * @param array $i18nRow
     * @param CustomerTitleEvent $event
     *
     * Handler to hydrate the event with i18n data
     */
    protected function hydrateEvent(array $i18nRow, CustomerTitleEvent $event)
    {
        $event
            ->setShort($i18nRow["short"])
            ->setLong($i18nRow["long"])
            ->setLocale($i18nRow["locale"])
        ;
    }

    protected function afterCreateEvents(Event $event, array &$data)
    {
        $dispatcher = $this->getDispatcher();

        while (null !== $i18nRow = array_shift($data["i18n"])) {
            $this->hydrateEvent($i18nRow, $event);

            foreach ($this->updateEvents as $eventName) {
                $dispatcher->dispatch($eventName, $event);
            }
        }
    }

    protected function afterUpdateEvents(Event $event, array &$data)
    {
        $this->afterCreateEvents($event, $data);
    }

    /**
     * @param FormEvent $event
     *
     * This methods loads current title data into the update form.
     * It uses an event to load only needed ids.
     */
    public function hydrateUpdateForm(FormEvent $event)
    {
        $data = $event->getData();

        $title = CustomerTitleQuery::create()->findPk($data["title_id"]);

        if (null === $title) {
            $this->entityNotFound($data["title_id"]);
        }

        $data["default"] |= (bool) $title->getByDefault();

        $titleI18ns = CustomerTitleI18nQuery::create()
            ->filterById($data["title_id"])
            ->find()
            ->toKeyIndex('Locale')
        ;

        $i18n = &$data["i18n"];

        foreach ($data["i18n"] as $key => $value) {
            $i18n[$value["locale"]] = $value;

            unset($i18n[$key]);
        }


        /** @var \Thelia\Model\CustomerTitleI18n $titleI18n */
        foreach ($titleI18ns as $titleI18n) {
            $row = array();

            $row["locale"] = $locale = $titleI18n->getLocale();
            $row["short"] = $titleI18n->getShort();
            $row["long"] = $titleI18n->getLong();

            if (!isset($i18n[$locale])) {
                $i18n[$locale] = array();
            }

            $i18n[$locale] = array_merge($row, $i18n[$locale]);
        }

        $event->setData($data);
    }
}

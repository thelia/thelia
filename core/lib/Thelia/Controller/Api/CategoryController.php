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
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Form\Definition\ApiForm;

/**
 * Class CategoryController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CategoryController extends AbstractCrudApiController
{
    public function __construct()
    {
        parent::__construct(
            "category",
            AdminResources::CATEGORY,
            TheliaEvents::CATEGORY_CREATE,
            TheliaEvents::CATEGORY_UPDATE,
            TheliaEvents::CATEGORY_DELETE
        );
    }

    /**
     * @return \Thelia\Core\Template\Element\BaseLoop
     *
     * Get the entity loop instance
     */
    protected function getLoop()
    {
        return new Category($this->container);
    }

    /**
     * @param array $data
     * @return \Thelia\Form\BaseForm
     */
    protected function getCreationForm(array $data = array())
    {
        return $this->createForm(ApiForm::CATEGORY_CREATION, "form", $data);
    }

    /**
     * @param array $data
     * @return \Thelia\Form\BaseForm
     */
    protected function getUpdateForm(array $data = array())
    {
        return $this->createForm(ApiForm::CATEGORY_MODIFICATION, "form", $data, [
            'method' => 'PUT',
        ]);
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
        return $event->getCategory();
    }


    /**
     * @param array $data
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getCreationEvent(array &$data)
    {
        $event = new CategoryCreateEvent();

        $event
            ->setLocale($data['locale'])
            ->setTitle($data['title'])
            ->setVisible($data['visible'])
            ->setParent($data['parent'])
        ;

        $this->setLocaleIntoQuery($data["locale"]);

        return $event;
    }

    /**
     * @param array $data
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getUpdateEvent(array &$data)
    {
        $event = new CategoryUpdateEvent($data["id"]);

        $event
            ->setLocale($data['locale'])
            ->setParent($data['parent'])
            ->setTitle($data['title'])
            ->setVisible($data['visible'])
            ->setChapo($data['chapo'])
            ->setDescription($data['description'])
            ->setPostscriptum($data['postscriptum'])
            ->setDefaultTemplateId($data['default_template_id'])
        ;

        $this->setLocaleIntoQuery($data["locale"]);

        return $event;
    }

    /**
     * @param mixed $entityId
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getDeleteEvent($entityId)
    {
        $event = new CategoryDeleteEvent($entityId);
        $event->setCategory(CategoryQuery::create()->findPk($entityId));

        return $event;
    }

    public function hydrateUpdateForm(FormEvent $event)
    {
        $id = $event->getData()["id"];

        if (null === CategoryQuery::create()->findPk($id)) {
            $this->entityNotFound($id);
        }
    }
}

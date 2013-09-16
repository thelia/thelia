<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Thelia\Model\TemplateQuery;
use Thelia\Model\Template as TemplateModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\TemplateUpdateEvent;
use Thelia\Core\Event\TemplateCreateEvent;
use Thelia\Core\Event\TemplateDeleteEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Model\TemplateAv;
use Thelia\Model\TemplateAvQuery;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\CategoryEvent;
use Thelia\Core\Event\TemplateEvent;
use Thelia\Model\TemplateTemplate;
use Thelia\Model\TemplateTemplateQuery;
use Thelia\Model\ProductQuery;

class Template extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new template entry
     *
     * @param TemplateCreateEvent $event
     */
    public function create(TemplateCreateEvent $event)
    {
        $template = new TemplateModel();

        $template
            ->setDispatcher($this->getDispatcher())

            ->setLocale($event->getLocale())
            ->setName($event->getTemplateName())

            ->save()
        ;

        $event->setTemplate($template);
    }

    /**
     * Change a product template
     *
     * @param TemplateUpdateEvent $event
     */
    public function update(TemplateUpdateEvent $event)
    {
        $search = TemplateQuery::create();

        if (null !== $template = TemplateQuery::create()->findPk($event->getTemplateId())) {

            $template
                ->setDispatcher($this->getDispatcher())

                 ->setLocale($event->getLocale())
                 ->setName($event->getTemplateName())
                 ->save();

            $event->setTemplate($template);
        }
    }

    /**
     * Delete a product template entry
     *
     * @param TemplateDeleteEvent $event
     */
    public function delete(TemplateDeleteEvent $event)
    {
        if (null !== ($template = TemplateQuery::create()->findPk($event->getTemplateId()))) {

            // Check if template is used by a product
            $product_count = ProductQuery::create()->findByTemplateId($template->getId())->count();

            if ($product_count <= 0) {
                $template
                    ->setDispatcher($this->getDispatcher())
                    ->delete()
                ;
            }

            $event->setTemplate($template);

            $event->setProductCount($product_count);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::TEMPLATE_CREATE          => array("create", 128),
            TheliaEvents::TEMPLATE_UPDATE          => array("update", 128),
            TheliaEvents::TEMPLATE_DELETE          => array("delete", 128),
        );
    }
}
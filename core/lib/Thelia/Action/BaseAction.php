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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;

use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;

use Thelia\Exception\UrlRewritingException;
use Thelia\Form\Exception\FormValidationException;
use \Thelia\Model\Tools\UrlRewritingTrait;

class BaseAction
{
    /**
     * @var The container
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
     * Changes object position, selecting absolute ou relative change.
     *
     * @param ModelCriteria       $query
     * @param UpdatePositionEvent $event
     *
     * @return mixed
     */
    protected function genericUpdatePosition(ModelCriteria $query, UpdatePositionEvent $event)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {

            $object->setDispatcher($this->getDispatcher());

            $mode = $event->getMode();

            if ($mode == UpdatePositionEvent::POSITION_ABSOLUTE)
                return $object->changeAbsolutePosition($event->getPosition());
            else if ($mode == UpdatePositionEvent::POSITION_UP)
                return $object->movePositionUp();
            else if ($mode == UpdatePositionEvent::POSITION_DOWN)
                return $object->movePositionDown();
        }
    }

    /**
     * Changes SEO Fields for an object.
     *
     * @param ModelCriteria       $query
     * @param UpdateSeoEvent      $event
     *
     * @return mixed
     */
    protected function genericUpdateSeo(ModelCriteria $query, UpdateSeoEvent $event)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {

            $object
                ->setDispatcher($this->getDispatcher())

                ->setLocale($event->getLocale())
                ->setMetaTitle($event->getMetaTitle())
                ->setMetaDescription($event->getMetaDescription())
                ->setMetaKeywords($event->getMetaKeywords())

                ->save()
            ;

            // Update the rewritten URL, if required
            try {
                $object->setRewrittenUrl($event->getLocale(), $event->getUrl());
            } catch(UrlRewritingException $e) {
                throw new FormValidationException($e->getMessage(), $e->getCode());
            }

           $event->setObject($object);

           return $object;
        }
    }

}

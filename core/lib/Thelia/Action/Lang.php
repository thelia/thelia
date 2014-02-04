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
use Thelia\Core\Event\Lang\LangCreateEvent;
use Thelia\Core\Event\Lang\LangDefaultBehaviorEvent;
use Thelia\Core\Event\Lang\LangDeleteEvent;
use Thelia\Core\Event\Lang\LangToggleDefaultEvent;
use Thelia\Core\Event\Lang\LangUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Lang\LangUrlEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Lang as LangModel;

/**
 * Class Lang
 * @package Thelia\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Lang extends BaseAction implements EventSubscriberInterface
{

    public function update(LangUpdateEvent $event)
    {
        if (null !== $lang = LangQuery::create()->findPk($event->getId())) {
            $lang->setDispatcher($event->getDispatcher());

            $lang->setTitle($event->getTitle())
                ->setLocale($event->getLocale())
                ->setCode($event->getCode())
                ->setDateFormat($event->getDateFormat())
                ->setTimeFormat($event->getTimeFormat())
                ->save();

            $event->setLang($lang);
        }
    }

    public function toggleDefault(LangToggleDefaultEvent $event)
    {
        if (null !== $lang = LangQuery::create()->findPk($event->getLangId())) {
            $lang->setDispatcher($event->getDispatcher());

            $lang->toggleDefault();

            $event->setLang($lang);
        }
    }

    public function create(LangCreateEvent $event)
    {
        $lang = new LangModel();

        $lang
            ->setDispatcher($event->getDispatcher())
            ->setTitle($event->getTitle())
            ->setCode($event->getCode())
            ->setLocale($event->getLocale())
            ->setDateFormat($event->getDateFormat())
            ->setTimeFormat($event->getTimeFormat())
            ->save();

        $event->setLang($lang);
    }

    public function delete(LangDeleteEvent $event)
    {
        if (null !== $lang = LangQuery::create()->findPk($event->getLangId())) {
            $lang->setDispatcher($event->getDispatcher())
                ->delete();

            $event->setLang($lang);
        }
    }

    public function defaultBehavior(LangDefaultBehaviorEvent $event)
    {
        ConfigQuery::create()
            ->filterByName('default_lang_without_translation')
            ->update(array('Value' => $event->getDefaultBehavior()));
    }

    public function langUrl(LangUrlEvent $event)
    {
        foreach ($event->getUrl() as $id => $url) {
            LangQuery::create()
                ->filterById($id)
                ->update(array('Url' => $url));
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::LANG_UPDATE => array('update', 128),
            TheliaEvents::LANG_TOGGLEDEFAULT => array('toggleDefault', 128),
            TheliaEvents::LANG_CREATE => array('create', 128),
            TheliaEvents::LANG_DELETE => array('delete', 128),
            TheliaEvents::LANG_DEFAULTBEHAVIOR => array('defaultBehavior', 128),
            TheliaEvents::LANG_URL => array('langUrl', 128)
        );
    }
}

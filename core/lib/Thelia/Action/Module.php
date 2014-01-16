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
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Log\Tlog;

/**
 * Class Module
 * @package Thelia\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Module extends BaseAction implements EventSubscriberInterface
{

    public function toggleActivation(ModuleToggleActivationEvent $event)
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            $moduleClass = new \ReflectionClass($module->getFullNamespace());

            $moduleInstance = $moduleClass->newInstance();

            if ( method_exists($moduleInstance, 'setContainer')) {
                $moduleInstance->setContainer($this->container);
                if ($module->getActivate() == BaseModule::IS_ACTIVATED) {
                    $moduleInstance->deActivate($module);
                } else {
                    $moduleInstance->activate($module);
                }
            }

            $event->setModule($module);

            $this->cacheClear();
        }
    }

    public function delete(ModuleDeleteEvent $event)
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            $con = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                if (null === $module->getFullNamespace()) {
                    throw new \LogicException('can not instanciante this module: the namespace is null. Maybe the model is not loaded ?');
                }

                try {
                    $reflected = new \ReflectionClass($module->getFullNamespace());

                    $instance = $reflected->newInstance();
                    $instance->setContainer($this->container);

                    $path = dirname($reflected->getFileName());

                    $instance->destroy($con, $event->getDeleteData());

                    $fs = new Filesystem();
                    $fs->remove($path);
                }
                catch (\ReflectionException $ex) {
                    // Happens mostly because the module directory has been deleted.
                    // Log a warning, and delete the database entry.
                    Tlog::getInstance()->addWarning("Failed to create instance of module ", $module->getCode());
                }

                $module->delete($con);

                $con->commit();

                $event->setModule($module);
                $this->cacheClear();

            } catch (\Exception $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    /**
     * @param ModuleEvent $event
     */
    public function update(ModuleEvent $event)
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getId())) {

            $module
                ->setDispatcher($this->getDispatcher())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setChapo($event->getChapo())
                ->setDescription($event->getDescription())
                ->setPostscriptum($event->getPostscriptum())
            ;

            $module->save();

            $event->setModule($module);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        return $this->genericUpdatePosition(ModuleQuery::create(), $event);
    }

    protected function cacheClear()
    {
        $cacheEvent = new CacheEvent($this->container->getParameter('kernel.cache_dir'));

        $this->getDispatcher()->dispatch(TheliaEvents::CACHE_CLEAR, $cacheEvent);
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
            TheliaEvents::MODULE_TOGGLE_ACTIVATION => array('toggleActivation', 128),
            TheliaEvents::MODULE_UPDATE_POSITION => array('updatePosition', 128),
            TheliaEvents::MODULE_DELETE => array('delete', 128),
            TheliaEvents::MODULE_UPDATE => array('update', 128),
        );
    }
}

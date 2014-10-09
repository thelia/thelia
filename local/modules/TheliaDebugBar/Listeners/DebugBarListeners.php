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

namespace TheliaDebugBar\Listeners;

use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DebugBar;
use TheliaDebugBar\DataCollector\PropelCollector;
use DebugBar\DataCollector\TimeDataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Action\BaseAction;
use Thelia\Core\Event\TheliaEvents;


/**
 * Class DebugBarListeners
 * @package TheliaDebugBar\Listeners
 * @author Manuel Raynaud <manu@thelia.net>
 */
class DebugBarListeners extends BaseAction implements EventSubscriberInterface {

    protected $debugBar;
    protected $debugMode;

    public function __construct(DebugBar $debugbar, $debugMode)
    {
        $this->debugBar = $debugbar;
        $this->debugMode = $debugMode;
    }

    public function initDebugBar()
    {
        $alternativelogger = null;
        if($this->debugMode) {
            $alternativelogger = \Thelia\Log\Tlog::getInstance();
        }

        $this->debugBar->addCollector(new PhpInfoCollector());
        //$this->debugBar->addCollector(new MessagesCollector());
        //$this->debugBar->addCollector(new RequestDataCollector());
        $this->debugBar->addCollector(new TimeDataCollector());
        $this->debugBar->addCollector(new MemoryCollector());
        $this->debugBar->addCollector(new PropelCollector($alternativelogger));
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
            TheliaEvents::BOOT => array("initDebugBar", 128)
        );
    }
}
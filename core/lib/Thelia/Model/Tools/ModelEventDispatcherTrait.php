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

namespace Thelia\Model\Tools;

use Thelia\Core\Event\ActionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A trait to provide event dispatching mechanism to Model objects
 */
trait ModelEventDispatcherTrait
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher = null;

    /**
     * @param EventDispatcherInterface $dispatcher
     *
     * @return $this
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function clearDispatcher()
    {
        $this->dispatcher = null;
    }

    protected function dispatchEvent($eventName, ActionEvent $event)
    {
        if (!is_null($this->dispatcher)) {
            $this->dispatcher->dispatch($eventName, $event);
        }
    }

    public function __sleep()
    {
        $data = parent::__sleep();
        $key = array_search("dispatcher", $data);

        if (isset($data[$key])) {
            unset($data[$key]);
        }

        return $data;
    }
}

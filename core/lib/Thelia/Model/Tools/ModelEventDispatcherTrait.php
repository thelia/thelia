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

    protected function dispatchEvent($eventName, ActionEvent $event)
    {
        if (!is_null($this->dispatcher)) {
            $this->dispatcher->dispatch($eventName, $event);
        }
    }
}

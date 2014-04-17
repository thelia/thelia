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

namespace Thelia\Core\Factory;

use Symfony\Component\HttpFoundation\Request;

/**
 * *
 * try to instanciate the good action class
 *
 * Class ActionEventFactory
 * @package Thelia\Core\Factory
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ActionEventFactory
{

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $action;

    /**
     * array(
     *  "action.addCart" => "Thelia\Core\Event\CartAction"
     * )
     *
     * @var array key are action name and value the Event class to dispatch
     */
    protected $className;

    protected $defaultClassName = "Thelia\Core\Event\DefaultActionEvent";

    public function __construct(Request $request, $action, $className)
    {
        $this->request = $request;
        $this->action = $action;
        $this->className = $className;
    }

    public function createActionEvent()
    {
        if (array_key_exists($this->action, $this->className)) {
            $class = new \ReflectionClass($this->className[$this->action]);
            // return $class->newInstance($this->request, $this->action);
        } else {
            $class = new \ReflectionClass($this->defaultClassName);
        }

        if ($class->isSubclassOf("Thelia\Core\Event\ActionEvent") === false) {
            throw new \RuntimeException("%s must be a subclass of Thelia\Core\Event\ActionEvent", $class->getName());
        }

        return $class->newInstance($this->request, $this->action);
    }
}

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

namespace Thelia\Core\Event;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\Form;

/**
 *
 * Class thrown on Thelia.action event
 *
 * call setAction if action match yours
 *
 */
abstract class ActionEvent extends Event
{
    protected $parameters = array();

    public function __set($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->parameters)) {
            return $this->parameters[$name];
        }

        return null;
    }

    public function bindForm(Form $form)
    {
        $fields = $form->getIterator();

        /** @var \Symfony\Component\Form\Form $field */
        foreach ($fields as $field) {
            $functionName = sprintf("set%s", Container::camelize($field->getName()));
            if (method_exists($this, $functionName)) {
                $getFunctionName = sprintf("get%s", Container::camelize($field->getName()));
                if (method_exists($this, $getFunctionName)) {
                    if (null === $this->{$getFunctionName}()) {
                        $this->{$functionName}($field->getData());
                    }
                } else {
                    $this->{$functionName}($field->getData());
                }
            } else {
                $this->{$field->getName()} = $field->getData();
            }
        }
    }
}

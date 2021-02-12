<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Form;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class thrown on Thelia.action event.
 *
 * call setAction if action match yours
 */
abstract class ActionEvent extends Event
{
    protected $parameters = [];

    public function __set($name, $value): void
    {
        $this->parameters[$name] = $value;
    }

    public function __get($name)
    {
        if (\array_key_exists($name, $this->parameters)) {
            return $this->parameters[$name];
        }

        return null;
    }

    public function bindForm(Form $form): void
    {
        $fields = $form->getIterator();

        /** @var \Symfony\Component\Form\Form $field */
        foreach ($fields as $field) {
            $functionName = sprintf('set%s', Container::camelize($field->getName()));
            if (method_exists($this, $functionName)) {
                $getFunctionName = sprintf('get%s', Container::camelize($field->getName()));
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

<?php

declare(strict_types=1);

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

    public function __isset(string $name): bool
    {
        return isset($this->parameters[$name]);
    }

    public function __get($name)
    {
        return $this->parameters[$name] ?? null;
    }

    public function bindForm(Form $form): void
    {
        $fields = $form->getIterator();

        /** @var Form $field */
        foreach ($fields as $field) {
            $this->bindFormField($field->getName(), $field->getData());
        }
    }

    public function bindArray(array $data): self
    {
        foreach ($data as $fieldName => $fieldValue) {
            $this->bindField($fieldName, $fieldValue);
        }

        return $this;
    }

    public function resetStopPropagation(): void
    {
        $reflection = new \ReflectionClass(Event::class);
        $property = $reflection->getProperty('propagationStopped');
        $property->setAccessible(true);
        $property->setValue($this, false);
    }

    private function bindField(string $fieldName, mixed $fieldValue): void
    {
        $setterMethodName = \sprintf('set%s', Container::camelize($fieldName));

        if (!method_exists($this, $setterMethodName)) {
            $this->__set($fieldName, $fieldValue);

            return;
        }

        $this->callSetterIfAllowed($setterMethodName, $fieldName, $fieldValue);
    }

    private function bindFormField(string $fieldName, mixed $fieldValue): void
    {
        $setterMethodName = \sprintf('set%s', Container::camelize($fieldName));

        if (!method_exists($this, $setterMethodName)) {
            $this->__set($fieldName, $fieldValue);

            return;
        }

        $this->callSetterIfAllowed($setterMethodName, $fieldName, $fieldValue);
    }

    private function callSetterIfAllowed(string $setterMethodName, string $fieldName, mixed $fieldValue): void
    {
        $getterMethodName = \sprintf('get%s', Container::camelize($fieldName));

        if (method_exists($this, $getterMethodName) && $this->{$getterMethodName}() !== $fieldValue) {
            $this->{$setterMethodName}($fieldValue);

            return;
        }

        try {
            $this->{$setterMethodName}($fieldValue);
        } catch (\TypeError) {
            // Do nothing, just ignore the error
        }
    }
}

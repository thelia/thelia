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
namespace Thelia\Core\Hook;

use InvalidArgumentException;

/**
 * Class Fragment.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Fragment
{
    protected $data;

    public function __construct($data = [])
    {
        if (!\is_array($data)) {
            throw new InvalidArgumentException("'data' argument must be an array");
        }

        $this->data = $data;
    }

    public function set($key, $value): static
    {
        $this->data[$key] = $value ?? '';

        return $this;
    }

    public function get($key)
    {
        if (\array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return null;
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function getVarVal()
    {
        return $this->data;
    }

    public function getVars()
    {
        return array_keys($this->data);
    }

    public function filter(array $fields, $default = null): void
    {
        if ($fields === []) {
            return;
        }

        $data = [];

        foreach ($fields as $field) {
            $data[$field] = (\array_key_exists($field, $this->data)) ? $this->data[$field] : $default;
        }

        $this->data = $data;
    }
}

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

namespace Thelia\Core\Template\Element;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

class LoopResultRow
{
    protected $substitution = [];

    public $model;

    public function __construct($model = null)
    {
        if ($model instanceof ActiveRecordInterface) {
            $this->model = $model;
        }
    }

    public function set($key, $value): static
    {
        $this->substitution[$key] = $value ?? '';

        return $this;
    }

    public function get($key)
    {
        return $this->substitution[$key];
    }

    public function getVarVal()
    {
        return $this->substitution;
    }

    public function getVars()
    {
        return array_keys($this->substitution);
    }
}

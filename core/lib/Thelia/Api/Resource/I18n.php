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

namespace Thelia\Api\Resource;


class I18n
{
    public ?int $id;

    public function __construct($data = [])
    {
        foreach ($data as $field => $value) {
            $setter = 'set'.ucfirst($field);
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }
}

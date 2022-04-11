<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ProductCategory
{
    #[Groups(['product:read'])]
    private bool $isDefault;

    #[Groups(['product:read'])]
    private int $position;

    /**
     * @return mixed
     */
    public function getIsDefault()
    {
        return $this->isDefault;
    }

    /**
     * @param mixed $isDefault
     * @return ProductCategory
     */
    public function setIsDefault($isDefault)
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param mixed $position
     * @return ProductCategory
     */
    public function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }

}

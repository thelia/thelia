<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource]
class Template
{
    private int $id;

    #[Groups(['product:read'])]
    private string $title;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Template
     */
    public function setId(int $id): Template
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return Template
     */
    public function setTitle(string $title): Template
    {
        $this->title = $title;
        return $this;
    }
}

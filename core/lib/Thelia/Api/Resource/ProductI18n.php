<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ProductI18n extends I18n
{
    private int $id;

    #[Groups(['product:read', 'product:write'])]
    private string $locale;

    #[Groups(['product:read', 'product:write'])]
    private ?string $title;

    #[Groups(['product:read', 'product:write'])]
    private ?string $chapo;
}

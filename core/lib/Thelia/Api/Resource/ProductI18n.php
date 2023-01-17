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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Annotation\Groups;

class ProductI18n extends I18n
{
    #[Groups(['product:read', 'product:write'])]
    private string $locale;

    #[Groups(['product:read', 'product:write'])]
    private ?string $title;

    #[Groups(['product:read', 'product:write'])]
    private ?string $chapo;
}

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

use Symfony\Component\Serializer\Annotation\Groups;


class ProductI18n extends I18n
{
    #[Groups([Product::GROUP_READ, Product::GROUP_WRITE])]
    private ?string $title;

    #[Groups([Product::GROUP_READ, Product::GROUP_WRITE])]
    private ?string $chapo;

    #[Groups([Product::GROUP_READ, Product::GROUP_WRITE])]
    private ?string $description;
}

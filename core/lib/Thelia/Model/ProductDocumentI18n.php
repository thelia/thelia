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
namespace Thelia\Model;

use Thelia\Model\Base\ProductDocumentI18n as BaseProductDocumentI18n;
use Thelia\Model\Tools\I18nTimestampableTrait;

class ProductDocumentI18n extends BaseProductDocumentI18n
{
    use I18nTimestampableTrait;
}

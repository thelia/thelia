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

namespace Thelia\Core\Serializer;

use Thelia\Domain\DataTransfer\Export\AbstractExport;

/**
 * A serializer made for some exports only, and never for an import: it is offered and accepted
 * for the exports it supports, and its extension is not one the imports accept.
 */
interface ExportOnlySerializerInterface extends SerializerInterface
{
    public function supportsExport(AbstractExport $export): bool;
}

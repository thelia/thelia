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

namespace Thelia\Domain\DataTransfer\Import\Type;

use Thelia\Domain\DataTransfer\Import\AbstractImport;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionException;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionFacade;

/**
 * Puts products into destination restriction groups: one row per product and group,
 * both named by their codes. A row naming an unknown product or group is refused with
 * the reason, and the import carries on with the next one.
 */
class ProductDestinationRestrictionImport extends AbstractImport
{
    protected array $mandatoryColumns = [
        'ref',
        'restriction_code',
    ];

    private ?DestinationRestrictionFacade $facade = null;

    public function importData(array $data): ?string
    {
        // The import handler builds its handlers with new: the facade is built here.
        $this->facade ??= new DestinationRestrictionFacade(new DestinationRestrictionChecker());

        try {
            $this->facade->attachProductByReference(trim((string) $data['ref']), trim((string) $data['restriction_code']));
        } catch (DestinationRestrictionException $refusal) {
            return $refusal->getMessage();
        }

        ++$this->importedRows;

        return null;
    }
}

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

use Thelia\Core\Translation\Translator;
use Thelia\Domain\Catalog\Product\Identifier\InvalidGtinException;
use Thelia\Domain\Catalog\Product\Identifier\InvalidMpnException;
use Thelia\Domain\DataTransfer\Import\AbstractImport;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ControllerTestBase.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ProductStockImport extends AbstractImport
{
    protected array $mandatoryColumns = [
        'id',
        'stock',
    ];

    protected array $optionalColumns = [
        'ean',
        'mpn',
    ];

    public function importData(array $data): ?string
    {
        $pse = ProductSaleElementsQuery::create()->findPk($data['id']);

        if (null === $pse) {
            return Translator::getInstance()->trans(
                "The product sale element id %id doesn't exist",
                [
                    '%id' => $data['id'],
                ],
            );
        }

        // A spreadsheet gives every cell as text: the stock is read as a number, and
        // a cell that is not one refuses this row only.
        if (!self::isStorableNumber($data['stock'])) {
            return Translator::getInstance()->trans(
                'The value "%value" of the column %column is not a number (product sale element id %id)',
                ['%value' => self::cellText($data['stock']), '%column' => 'stock', '%id' => self::cellText($data['id'])],
            );
        }

        $pse->setQuantity((float) $data['stock']);

        if (isset($data['ean']) && !empty($data['ean'])) {
            $pse->setEanCode((string) $data['ean']);
        }

        if (isset($data['mpn']) && '' !== trim((string) $data['mpn'])) {
            $pse->setMpn((string) $data['mpn']);
        }

        // A code that is not a GTIN refuses this row only, with the reason, and the
        // import carries on with the next one. Checked before saving: refused by
        // save(), it would roll back a transaction nested in the import's, which
        // could then not be committed.
        try {
            $pse->assertIdentifiersAreValid();
        } catch (InvalidGtinException|InvalidMpnException $refusal) {
            // The refused values would otherwise stay on the pooled instance and come
            // back with a later row of the same combination.
            $pse->reload();

            return $refusal->getMessage();
        }

        $pse->save();

        ++$this->importedRows;

        return null;
    }
}

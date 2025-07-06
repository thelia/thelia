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

namespace Thelia\ImportExport\Import\Type;

use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Import\AbstractImport;
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

        $pse->setQuantity($data['stock']);

        if (isset($data['ean']) && !empty($data['ean'])) {
            $pse->setEanCode($data['ean']);
        }

        $pse->save();
        ++$this->importedRows;

        return null;
    }
}

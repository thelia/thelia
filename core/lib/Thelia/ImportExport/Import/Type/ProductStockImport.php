<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\ImportExport\Import\Type;

use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Import\AbstractImport;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ControllerTestBase
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ProductStockImport extends AbstractImport
{
    protected $mandatoryColumns = [
        'id',
        'stock'
    ];

    public function importData(array $data)
    {
        $pse = ProductSaleElementsQuery::create()->findPk($data['id']);

        if ($pse === null) {
            return Translator::getInstance()->trans(
                'The product sale element reference %id doesn\'t exist',
                [
                    '%id' => $data['id']
                ]
            );
        } else {
            $pse->setQuantity($data['stock']);

            if (isset($data['ean']) && !empty($data['ean'])) {
                $pse->setEanCode($data['ean']);
            }

            $pse->save();
            $this->importedRows++;
        }

        return null;
    }
}

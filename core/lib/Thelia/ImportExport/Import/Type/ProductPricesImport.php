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
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductPricesImport.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductPricesImport extends AbstractImport
{
    protected $mandatoryColumns = [
        'id',
        'price',
    ];

    public function importData(array $data): ?string
    {
        $pse = ProductSaleElementsQuery::create()->findPk($data['id']);

        if ($pse === null) {
            return Translator::getInstance()->trans(
                "The product sale element id %id doesn't exist",
                [
                    '%id' => $data['id'],
                ]
            );
        }

        $currency = null;
        if (isset($data['currency'])) {
            $currency = CurrencyQuery::create()->findOneByCode($data['currency']);
        }

        if ($currency === null) {
            $currency = Currency::getDefaultCurrency();
        }

        $price = ProductPriceQuery::create()
                ->filterByProductSaleElementsId($pse->getId())
                ->findOneByCurrencyId($currency->getId())
        ;

        if ($price === null) {
            $price = new ProductPrice();

            $price
                    ->setProductSaleElements($pse)
                    ->setCurrency($currency)
            ;
        }

        $price->setPrice($data['price']);

        if (isset($data['promo_price'])) {
            $price->setPromoPrice($data['promo_price']);
        }

        if (isset($data['promo'])) {
            $price
                    ->getProductSaleElements()
                    ->setPromo((int) $data['promo'])
                    ->save()
            ;
        }

        $price->save();
        ++$this->importedRows;

        return null;
    }
}

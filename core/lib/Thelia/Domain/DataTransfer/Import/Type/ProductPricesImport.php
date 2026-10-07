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
use Thelia\Domain\DataTransfer\Import\AbstractImport;
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
    protected array $mandatoryColumns = [
        'id',
        'price',
    ];

    protected array $optionalColumns = [
        'currency',
        'promo_price',
        'promo',
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

        // A price is stored as a decimal string; JSON and XML files give numbers. A
        // cell that is not a number refuses this row only, checked before anything is
        // attached to the combination: a new price left on it would be saved, at 0,
        // with the next row of the same combination.
        // A JSON file may give a null price, a list or an object: refused too, rather than
        // written as an empty price the database refuses for the whole import.
        foreach (['price', 'promo_price'] as $column) {
            $required = 'price' === $column;

            if ((!$required && !isset($data[$column])) || (\is_scalar($data[$column] ?? null) && is_numeric($data[$column]))) {
                continue;
            }

            return Translator::getInstance()->trans(
                'The value "%value" of the column %column is not a number (product sale element id %id)',
                ['%value' => \is_scalar($data[$column] ?? null) ? (string) $data[$column] : '', '%column' => $column, '%id' => \is_scalar($data['id'] ?? null) ? (string) $data['id'] : ''],
            );
        }

        $currency = null;

        if (isset($data['currency'])) {
            $currency = CurrencyQuery::create()->findOneByCode($data['currency']);
        }

        if (null === $currency) {
            $currency = Currency::getDefaultCurrency();
        }

        $price = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->findOneByCurrencyId($currency->getId());

        if (null === $price) {
            $price = new ProductPrice();

            $price
                ->setProductSaleElements($pse)
                ->setCurrency($currency);
        }

        $price->setPrice((string) $data['price']);

        if (isset($data['promo_price'])) {
            $price->setPromoPrice((string) $data['promo_price']);
        }

        if (isset($data['promo'])) {
            $price
                ->getProductSaleElements()
                ->setPromo((int) $data['promo'])
                ->save();
        }

        $price->save();
        ++$this->importedRows;

        return null;
    }
}

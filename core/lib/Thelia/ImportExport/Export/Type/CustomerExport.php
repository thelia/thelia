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

namespace Thelia\ImportExport\Export\Type;

use PDO;
use Propel\Runtime\Propel;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CountryI18nTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\CustomerTitleI18nTableMap;
use Thelia\Model\Map\NewsletterTableMap;
use Thelia\ImportExport\Export\JsonFileAbstractExport;

/**
 * Class CustomerExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class CustomerExport extends JsonFileAbstractExport
{
    const FILE_NAME = 'customer';

    protected $orderAndAliases = [
        CustomerTableMap::COL_REF => 'ref',
        CustomerTitleI18nTableMap::COL_LONG => 'title',
        CustomerTableMap::COL_LASTNAME => 'last_name',
        CustomerTableMap::COL_FIRSTNAME => 'first_name',
        CustomerTableMap::COL_EMAIL => 'email',
        CustomerTableMap::COL_DISCOUNT => 'discount',
        NewsletterTableMap::COL_ID => 'is_registered_to_newsletter',
        CustomerTableMap::COL_CREATED_AT => 'sign_up_date',
        AddressTableMap::COL_COMPANY => 'company',
        AddressTableMap::COL_ADDRESS1 => 'address1',
        AddressTableMap::COL_ADDRESS2 => 'address2',
        AddressTableMap::COL_ADDRESS3 => 'address3',
        AddressTableMap::COL_ZIPCODE => 'zipcode',
        AddressTableMap::COL_CITY => 'city',
        CountryI18nTableMap::COL_TITLE => 'country',
        AddressTableMap::COL_PHONE => 'phone',
        AddressTableMap::COL_CELLPHONE => 'cellphone'
    ];

    protected function getData()
    {
        $locale = $this->language->getLocale();

        $con = Propel::getConnection();
        $query = 'SELECT 
                        customer.ref as "customer.ref", 
                        customer_title_i18n.long as "customer_title_i18n.long", 
                        customer.firstname as "customer.firstname", 
                        customer.lastname as "customer.lastname", 
                        customer.email as "customer.email", 
                        customer.discount as "customer.discount",
                        address.company as "address.company",
                        address.address1 as "address.address1", 
                        address.address2 as "address.address2",
                        address.address3 as "address.address3",
                        address.zipcode as "address.zipcode",
                        address.city as "address.city",
                        country_i18n.title as "country_i18n.title",
                        newsletter.id as "newsletter.id",
                        address.phone as "address.phone",
                        address.cellphone as "address.cellphone",
                        customer.created_at as "customer.created_at"
                    FROM customer
                    LEFT JOIN customer_title_i18n ON customer.title_id = customer_title_i18n.id AND customer_title_i18n.locale = :locale
                    LEFT JOIN address ON address.customer_id = customer.id AND address.is_default = 1
                    LEFT JOIN country_i18n ON address.country_id = country_i18n.id AND country_i18n.locale = :locale
                    LEFT JOIN newsletter ON newsletter.email = customer.email
                    GROUP BY customer.id'
        ;
        $stmt = $con->prepare($query);
        $stmt->bindValue('locale', $locale);
        $res = $stmt->execute();

        $filename = THELIA_CACHE_DIR . '/export/' . 'customer.json';

        if(file_exists($filename)){
            unlink($filename);
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            file_put_contents($filename, json_encode($row) . "\r\n", FILE_APPEND);
        }

        return $filename;
    }
}

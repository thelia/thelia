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

namespace Thelia\ImportExport\Export\Type;

use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;

/**
 * Class CustomerExport.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class CustomerExport extends JsonFileAbstractExport
{
    public const FILE_NAME = 'customer';

    protected $orderAndAliases = [
        'customer_ref' => 'ref',
        'customer_title_i18n_long' => 'title',
        'customer_firstname' => 'last_name',
        'customer_lastname' => 'first_name',
        'customer_email' => 'email',
        'customer_discount' => 'discount',
        'newsletter_id' => 'is_registered_to_newsletter',
        'customer_created_at' => 'sign_up_date',
        'address_company' => 'company',
        'address_address1' => 'address1',
        'address_address2' => 'address2',
        'address_address3' => 'address3',
        'address_zipcode' => 'zipcode',
        'address_city' => 'city',
        'country_i18n_title' => 'country',
        'address_phone' => 'phone',
        'address_cellphone' => 'cellphone',
    ];

    protected function getData()
    {
        $locale = $this->language->getLocale();

        $con = Propel::getConnection();
        $query = 'SELECT
                        customer.ref as "customer_ref",
                        customer_title_i18n.long as "customer_title_i18n_long",
                        customer.firstname as "customer_firstname",
                        customer.lastname as "customer_lastname",
                        customer.email as "customer_email",
                        customer.discount as "customer_discount",
                        address.company as "address_company",
                        address.address1 as "address_address1",
                        address.address2 as "address_address2",
                        address.address3 as "address_address3",
                        address.zipcode as "address_zipcode",
                        address.city as "address_city",
                        country_i18n.title as "country_i18n_title",
                        newsletter.id as "newsletter_id",
                        address.phone as "address_phone",
                        address.cellphone as "address_cellphone",
                        customer.created_at as "customer_created_at"
                    FROM customer
                    LEFT JOIN customer_title_i18n ON customer.title_id = customer_title_i18n.id AND customer_title_i18n.locale = :locale
                    LEFT JOIN address ON address.customer_id = customer.id AND address.is_default = 1
                    LEFT JOIN country_i18n ON address.country_id = country_i18n.id AND country_i18n.locale = :locale
                    LEFT JOIN newsletter ON newsletter.email = customer.email
                    GROUP BY customer.id'
        ;
        $stmt = $con->prepare($query);
        $stmt->bindValue('locale', $locale);
        $stmt->execute();

        return $this->getDataJsonCache($stmt, 'customer');
    }
}

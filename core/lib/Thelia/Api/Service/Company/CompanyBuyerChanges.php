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

namespace Thelia\Api\Service\Company;

use Thelia\Api\Resource\CompanyCustomer as CompanyCustomerResource;
use Thelia\Domain\Company\Service\CompanyAccountWriter;
use Thelia\Model\CompanyCustomer;
use Thelia\Model\Customer;

/**
 * Applies to a buyer the role and the limits a request ended up with. The rules are those of the
 * domain ({@see CompanyAccountWriter::applyChanges()}); this only reads them from the resource.
 */
final readonly class CompanyBuyerChanges
{
    public function __construct(private CompanyAccountWriter $writer)
    {
    }

    /**
     * @throws \Thelia\Domain\Company\Exception\CompanyException
     */
    public function apply(CompanyCustomer $buyer, CompanyCustomerResource $wanted, ?Customer $issuer): CompanyCustomer
    {
        return $this->writer->applyChanges($buyer, $wanted->getRoleCode(), $wanted->getOrderLimit(), $wanted->getPeriodLimit(), $wanted->getPeriod(), $issuer);
    }
}

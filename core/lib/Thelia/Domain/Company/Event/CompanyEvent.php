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

namespace Thelia\Domain\Company\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Model\Company;

/**
 * A company was created, changed, suspended, reactivated or deleted, and the change is written.
 *
 * Sent after the transaction, once per operation; on a deletion the company no longer exists.
 */
final class CompanyEvent extends Event
{
    public function __construct(private readonly Company $company)
    {
    }

    public function getCompany(): Company
    {
        return $this->company;
    }
}

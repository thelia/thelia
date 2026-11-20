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

namespace Thelia\Domain\Company;

/**
 * The rights a company role can grant to the buyers who hold it.
 *
 * A role stores the codes below as a list; {@see Service\CompanyPermissionChecker} is the one
 * place that turns a buyer, their role and their company into a yes or a no. A module that
 * brings a right of its own declares it with {@see CompanyPermissionProviderInterface}.
 */
final class CompanyPermission
{
    /** Place an order for the company. */
    public const ORDER_PLACE = 'order.place';

    /** See the orders the buyer placed. */
    public const ORDER_VIEW_OWN = 'order.view_own';

    /** See the orders any buyer of the company placed. */
    public const ORDER_VIEW_COMPANY = 'order.view_company';

    /** Approve the orders other buyers placed. */
    public const ORDER_APPROVE = 'order.approve';

    /** Change and delete the addresses shared with the company, whoever created them. */
    public const ADDRESS_MANAGE_SHARED = 'address.manage_shared';

    /** Invite, detach and change the buyers of the company. */
    public const BUYER_MANAGE = 'buyer.manage';

    /** Edit the details of the company. */
    public const COMPANY_EDIT = 'company.edit';

    /**
     * What a customer who belongs to no company may do: an ordinary account places orders and
     * reads its own.
     *
     * @var list<string>
     */
    public const array WITHOUT_COMPANY = [self::ORDER_PLACE, self::ORDER_VIEW_OWN];

    /**
     * Every right the core defines, with the label shown where a role is edited.
     *
     * @return array<string, string> code => label
     */
    public static function labels(): array
    {
        return [
            self::ORDER_PLACE => 'Place orders',
            self::ORDER_VIEW_OWN => 'See own orders',
            self::ORDER_VIEW_COMPANY => 'See the orders of the company',
            self::ORDER_APPROVE => 'Approve orders',
            self::ADDRESS_MANAGE_SHARED => 'Manage the shared addresses',
            self::BUYER_MANAGE => 'Manage the buyers of the company',
            self::COMPANY_EDIT => 'Edit the company',
        ];
    }
}

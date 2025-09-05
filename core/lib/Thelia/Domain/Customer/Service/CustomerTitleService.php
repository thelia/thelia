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

namespace Thelia\Domain\Customer\Service;

use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleQuery;

class CustomerTitleService
{
    public function __construct(
        private LangService $langService,
    ) {
    }

    public function getTitleAsFormChoices(): array
    {
        $lang = $this->langService->getLang();
        $choices = [];
        $customerTitles = CustomerTitleQuery::create()
            ->orderByPosition()
            ->joinWithI18n($lang?->getLocale())
            ->find();

        foreach ($customerTitles as $customerTitle) {
            $choices[$customerTitle->getLong()] = (int) $customerTitle->getId();
        }

        return $choices;
    }

    public function getDefaultCustomerTitle(): ?CustomerTitle
    {
        return CustomerTitleQuery::create()
            ->filterByByDefault(1)
            ->findOne();
    }
}

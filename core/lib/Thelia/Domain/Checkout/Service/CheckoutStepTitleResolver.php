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

namespace Thelia\Domain\Checkout\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\CheckoutStep;
use Thelia\Model\CheckoutStepI18nQuery;
use Thelia\Model\Lang;
use Thelia\Tools\I18n;

/**
 * The wording a checkout step is named by, wherever it is named: the progress trail of
 * the theme, the back-office list, the snapshot frozen on an order.
 *
 * One chain and one only, so that the same step never reads differently depending on
 * which screen asks: the language asked for, the shop language next, then any language
 * the merchant did write in — and the code of the step last. What it never answers is
 * the "DEFAULT TITLE" placeholder I18n forges when it finds nothing, which means
 * nothing to a buyer and proves nothing to a merchant.
 */
final readonly class CheckoutStepTitleResolver
{
    /**
     * @param string|null $locale the language to read the wording in; the shop default
     *                            when null, so that a caller with no request behind it
     *                            still gets a title
     *
     * @throws PropelException
     */
    public function titleOf(CheckoutStep $step, ?string $locale = null): string
    {
        $locale ??= (string) Lang::getDefaultLanguage()->getLocale();

        $title = (string) I18n::forceI18nRetrieving($locale, 'CheckoutStep', $step->getId(), [])->getTitle();

        if ('' !== $title) {
            return $title;
        }

        $written = CheckoutStepI18nQuery::create()
            ->filterById($step->getId())
            ->filterByTitle(null, Criteria::ISNOTNULL)
            ->filterByTitle('', Criteria::NOT_EQUAL)
            ->orderByLocale()
            ->findOne();

        return (string) ($written?->getTitle() ?? $step->getCode());
    }
}

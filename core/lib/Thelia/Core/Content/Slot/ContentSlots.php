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

namespace Thelia\Core\Content\Slot;

/**
 * The slot codes the core answers for.
 *
 * A consent slot is the prefix followed by the code of the consent, for instance
 * 'consent.terms_and_conditions': it holds the page the consent box links to.
 */
final class ContentSlots
{
    public const HEADER = 'header_links';
    public const FOOTER = 'footer_links';
    public const CONSENT_PREFIX = 'consent.';
}

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

namespace Thelia\Core\Template\BackOffice;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Lets a module hide a section of the back-office navigation, 'folder' or 'catalog' for
 * instance, when it takes over what the section manages.
 *
 * Hiding a section only takes it out of the menu: its routes and their permissions stay
 * as they are. Implementations are collected through the "thelia.backoffice_navigation_voter"
 * tag (autoconfigured).
 */
#[AutoconfigureTag('thelia.backoffice_navigation_voter')]
interface NavigationSectionVoterInterface
{
    public function hidesSection(string $section): bool;
}

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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Whether the back-office theme shows a navigation section. A section is shown unless one
 * voter hides it; the permission to view it is checked apart, by the theme.
 */
final readonly class BackOfficeNavigation
{
    /**
     * @param iterable<NavigationSectionVoterInterface> $voters
     */
    public function __construct(
        #[AutowireIterator('thelia.backoffice_navigation_voter')]
        private iterable $voters,
    ) {
    }

    public function isSectionVisible(string $section): bool
    {
        foreach ($this->voters as $voter) {
            if ($voter->hidesSection($section)) {
                return false;
            }
        }

        return true;
    }
}

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

namespace Thelia\Tests\Unit\Core\Template\BackOffice;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\RegisterAutoconfigureAttributesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\Template\BackOffice\BackOfficeNavigation;
use Thelia\Core\Template\BackOffice\NavigationSectionVoterInterface;

final class BackOfficeNavigationTest extends TestCase
{
    public function testASectionIsVisibleWhenNoVoterHidesIt(): void
    {
        self::assertTrue((new BackOfficeNavigation([]))->isSectionVisible('folder'));
        self::assertTrue((new BackOfficeNavigation([new HidingVoter(['catalog'])]))->isSectionVisible('folder'));
    }

    public function testOneVoterHidingTheSectionIsEnough(): void
    {
        $navigation = new BackOfficeNavigation([
            new HidingVoter([]),
            new HidingVoter(['folder']),
            new HidingVoter([]),
        ]);

        self::assertFalse($navigation->isSectionVisible('folder'));
        self::assertTrue($navigation->isSectionVisible('catalog'));
    }

    /**
     * A module only implements the interface: the tag comes with it.
     */
    public function testAVoterIsCollectedByAutoconfiguration(): void
    {
        $container = new ContainerBuilder();
        // What the kernel does for every interface it finds under core/lib.
        (new RegisterAutoconfigureAttributesPass())->processClass($container, new \ReflectionClass(NavigationSectionVoterInterface::class));
        $container->register(FolderHidingVoter::class)->setAutoconfigured(true);
        $container->register(BackOfficeNavigation::class)->setAutowired(true)->setAutoconfigured(true)->setPublic(true);
        $container->compile();

        /** @var BackOfficeNavigation $navigation */
        $navigation = $container->get(BackOfficeNavigation::class);

        self::assertFalse($navigation->isSectionVisible('folder'));
        self::assertTrue($navigation->isSectionVisible('catalog'));
    }
}

final readonly class HidingVoter implements NavigationSectionVoterInterface
{
    /**
     * @param list<string> $hiddenSections
     */
    public function __construct(private array $hiddenSections)
    {
    }

    public function hidesSection(string $section): bool
    {
        return \in_array($section, $this->hiddenSections, true);
    }
}

final readonly class FolderHidingVoter implements NavigationSectionVoterInterface
{
    public function hidesSection(string $section): bool
    {
        return 'folder' === $section;
    }
}

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

namespace Thelia\Tests\Unit\Core\Content\Slot;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Compiler\RegisterAutoconfigureAttributesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\Content\Slot\ContentSlotLink;
use Thelia\Core\Content\Slot\ContentSlotResolverInterface;
use Thelia\Core\Content\Slot\ContentSlotService;
use Thelia\Core\Content\Slot\NativeContentSlotResolver;
use Thelia\Tools\URL;

/**
 * The first resolver that answers something other than null owns the slot, an empty list
 * included, and a slot nobody owns is empty.
 */
final class ContentSlotServiceTest extends TestCase
{
    public function testTheFirstResolverThatOwnsTheSlotAnswers(): void
    {
        $service = new ContentSlotService([
            new FixedContentSlotResolver(['footer_links' => [self::link('First')]]),
            new FixedContentSlotResolver(['header_links' => [self::link('Second')]]),
            new FixedContentSlotResolver(['header_links' => [self::link('Third')]]),
        ]);

        self::assertSame(['Second'], self::labels($service->links('header_links', 'en_US')));
    }

    public function testAnEmptyAnswerOwnsTheSlotAndStopsTheLookup(): void
    {
        $service = new ContentSlotService([
            new FixedContentSlotResolver(['header_links' => []]),
            new FixedContentSlotResolver(['header_links' => [self::link('Native')]]),
        ]);

        self::assertSame([], $service->links('header_links', 'en_US'));
        self::assertNull($service->first('header_links', 'en_US'));
    }

    public function testASlotNobodyOwnsIsEmpty(): void
    {
        $service = new ContentSlotService([
            new FixedContentSlotResolver(['header_links' => [self::link('Native')]]),
        ]);

        self::assertSame([], $service->links('unknown_slot', 'en_US'));
        self::assertNull($service->first('unknown_slot', 'en_US'));
        self::assertSame([], (new ContentSlotService([]))->links('header_links', 'en_US'));
    }

    public function testFirstIsTheFirstLinkOfTheOwner(): void
    {
        $service = new ContentSlotService([
            new FixedContentSlotResolver(['consent.terms_and_conditions' => [self::link('Terms'), self::link('Other')]]),
        ]);

        self::assertSame('Terms', $service->first('consent.terms_and_conditions', 'en_US')?->label);
    }

    public function testTheLocaleOfTheCallerReachesTheResolver(): void
    {
        $resolver = new FixedContentSlotResolver([]);

        (new ContentSlotService([$resolver]))->links('header_links', 'fr_FR');

        self::assertSame([['header_links', 'fr_FR']], $resolver->calls);
    }

    /**
     * The order a module relies on to take a slot over: the tag priority declared with
     * #[AsTaggedItem], highest first, and the core resolver below any module that declares
     * none. Built through the container, as the kernel builds it.
     */
    public function testResolversAreAskedByDescendingTagPriorityWithTheCoreOneBelowModules(): void
    {
        $container = new ContainerBuilder();
        // What the kernel does for every interface it finds under core/lib.
        (new RegisterAutoconfigureAttributesPass())->processClass($container, new \ReflectionClass(ContentSlotResolverInterface::class));
        $container->register(URL::class)->setSynthetic(true);
        $container->register(NativeContentSlotResolver::class)->setAutowired(true)->setAutoconfigured(true);
        $container->register(DefaultPriorityResolver::class)->setAutoconfigured(true);
        $container->register(HighPriorityResolver::class)->setAutoconfigured(true);
        $container->register(ContentSlotService::class)->setAutowired(true)->setAutoconfigured(true)->setPublic(true);
        $container->compile();

        $container->set(URL::class, $this->createStub(URL::class));

        /** @var ContentSlotService $service */
        $service = $container->get(ContentSlotService::class);

        self::assertSame(['High'], self::labels($service->links('shared_slot', 'en_US')));
        self::assertSame(['Default'], self::labels($service->links('header_links', 'en_US')));
    }

    private static function link(string $label): ContentSlotLink
    {
        return new ContentSlotLink(label: $label, url: null, source: 'url');
    }

    /**
     * @param list<ContentSlotLink> $links
     *
     * @return list<string>
     */
    private static function labels(array $links): array
    {
        return array_map(static fn (ContentSlotLink $link): string => $link->label, $links);
    }
}

final class FixedContentSlotResolver implements ContentSlotResolverInterface
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    /**
     * @param array<string, list<ContentSlotLink>> $answers
     */
    public function __construct(private readonly array $answers)
    {
    }

    public function resolve(string $slot, string $locale): ?array
    {
        $this->calls[] = [$slot, $locale];

        return $this->answers[$slot] ?? null;
    }
}

final class DefaultPriorityResolver implements ContentSlotResolverInterface
{
    public function resolve(string $slot, string $locale): ?array
    {
        return \in_array($slot, ['shared_slot', 'header_links'], true)
            ? [new ContentSlotLink(label: 'Default', url: null, source: 'url')]
            : null;
    }
}

#[AsTaggedItem(priority: 100)]
final class HighPriorityResolver implements ContentSlotResolverInterface
{
    public function resolve(string $slot, string $locale): ?array
    {
        return 'shared_slot' === $slot
            ? [new ContentSlotLink(label: 'High', url: null, source: 'url')]
            : null;
    }
}

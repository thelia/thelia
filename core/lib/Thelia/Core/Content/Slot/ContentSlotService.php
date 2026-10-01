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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The entries of a content slot, as the highest priority resolver that owns it answers.
 *
 * A slot nobody owns, an unknown code included, is empty: a theme asking for a slot no
 * module fills renders nothing rather than an error.
 */
final readonly class ContentSlotService
{
    /**
     * @param iterable<ContentSlotResolverInterface> $resolvers
     */
    public function __construct(
        #[AutowireIterator('thelia.content_slot_resolver')]
        private iterable $resolvers,
    ) {
    }

    /**
     * @return list<ContentSlotLink> first non-null answer, [] when nobody owns the slot
     */
    public function links(string $slot, string $locale): array
    {
        foreach ($this->resolvers as $resolver) {
            $links = $resolver->resolve($slot, $locale);

            if (null !== $links) {
                return $links;
            }
        }

        return [];
    }

    public function first(string $slot, string $locale): ?ContentSlotLink
    {
        return $this->links($slot, $locale)[0] ?? null;
    }
}

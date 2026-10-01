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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Fills a content slot a theme asks for by its code (see ContentSlots).
 *
 * Resolvers are collected through the "thelia.content_slot_resolver" tag (autoconfigured)
 * and asked in the order of the tag priority, highest first; the first one that does not
 * answer null owns the slot. An empty list is an answer: the resolver owns the slot and
 * has nothing to put in it, so the resolvers after it are not asked. The core resolver,
 * built on contents and folders, sits at priority -100; a module that takes a slot over
 * declares a higher priority with #[AsTaggedItem].
 *
 * The locale is always given by the caller: a resolver never reads it from a request, so
 * that it answers the same way from a console command.
 */
#[AutoconfigureTag('thelia.content_slot_resolver')]
interface ContentSlotResolverInterface
{
    /**
     * @return list<ContentSlotLink>|null null = this resolver does not own the slot
     */
    public function resolve(string $slot, string $locale): ?array;
}

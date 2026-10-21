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

namespace Thelia\Domain\CMS\Publication\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A content cannot go online with nothing to show in the default language of the shop.
 * The message is translated for the person who asked and never quotes the body: the
 * body is what is missing.
 */
#[Exclude]
final class EmptyContentPublicationException extends PublicationException
{
    public const MESSAGE = 'This content has no body in the default language (%locale) and cannot be published.';

    private function __construct(
        string $message,
        public readonly int $contentId,
        public readonly string $locale,
    ) {
        parent::__construct($message);
    }

    public static function forContent(int $contentId, string $locale): self
    {
        return new self(self::translated(self::MESSAGE, ['%locale' => $locale]), $contentId, $locale);
    }
}

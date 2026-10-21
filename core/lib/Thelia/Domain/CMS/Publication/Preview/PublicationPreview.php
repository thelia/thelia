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

namespace Thelia\Domain\CMS\Publication\Preview;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Request;

/**
 * What a request previewing an unpublished row carries, and the names shared by the
 * route, the controller, the listeners and the themes.
 */
#[Exclude]
final readonly class PublicationPreview
{
    /**
     * The request attribute a preview sets, read by a theme to show its banner:
     * `app.request.attributes.get('_publication_preview')`. Its value names the row
     * previewed, `['type' => 'content', 'id' => 12]`.
     */
    public const string ATTRIBUTE = '_publication_preview';

    public const string ROUTE = 'publication_preview';

    public const string CONTENT = 'content';

    public const string FOLDER = 'folder';

    public const array TYPES = [self::CONTENT, self::FOLDER];

    private function __construct(
        public string $type,
        public int $id,
    ) {
    }

    public static function of(string $type, int $id): self
    {
        if (!\in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a type a preview knows.', $type));
        }

        return new self($type, $id);
    }

    public static function fromRequest(?Request $request): ?self
    {
        $value = $request?->attributes->get(self::ATTRIBUTE);

        if (!\is_array($value) || !isset($value['type'], $value['id']) || !\in_array($value['type'], self::TYPES, true)) {
            return null;
        }

        return new self((string) $value['type'], (int) $value['id']);
    }

    public function markRequest(Request $request): void
    {
        $request->attributes->set(self::ATTRIBUTE, ['type' => $this->type, 'id' => $this->id]);
    }

    public function covers(string $view, mixed $viewId): bool
    {
        return $view === $this->type && (int) $viewId === $this->id;
    }
}

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

namespace Thelia\Domain\Media;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileModelInterface;
use Thelia\Model\Brand;
use Thelia\Model\BrandDocumentQuery;
use Thelia\Model\BrandImageQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryDocumentQuery;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderDocumentQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductVideoQuery;

/**
 * The images, documents and videos of a product, a category, a content, a folder
 * or a brand that is being deleted.
 *
 * Their rows go with the item through the foreign keys, and the file of an image
 * is named in its translations: read once the item is deleted, a medium no longer
 * knows its files and they stay on disk, with their published copies. So the media
 * are read before the item is deleted, and their files are deleted once that
 * deletion is committed, never before: a deletion rolled back keeps them.
 * PendingMediaDeletions holds them until the outermost deletion is committed.
 */
final readonly class DeletedItemMedia
{
    /**
     * @param list<array{FileModelInterface, string}> $media each medium with the event that deletes it
     */
    private function __construct(
        private array $media,
    ) {
    }

    /**
     * Reads the media of an item while its rows still exist. Any other model has none.
     */
    public static function of(object $item): self
    {
        [$images, $documents, $videos] = match (true) {
            $item instanceof Product => [
                ProductImageQuery::create()->findByProductId($item->getId()),
                ProductDocumentQuery::create()->findByProductId($item->getId()),
                ProductVideoQuery::create()->findByProductId($item->getId()),
            ],
            // A category takes its whole subtree with it.
            $item instanceof Category => [
                CategoryImageQuery::create()->filterByCategoryId(self::categoryTreeIds($item))->find(),
                CategoryDocumentQuery::create()->filterByCategoryId(self::categoryTreeIds($item))->find(),
                [],
            ],
            $item instanceof Content => [
                ContentImageQuery::create()->findByContentId($item->getId()),
                ContentDocumentQuery::create()->findByContentId($item->getId()),
                [],
            ],
            $item instanceof Folder => [
                FolderImageQuery::create()->findByFolderId($item->getId()),
                FolderDocumentQuery::create()->findByFolderId($item->getId()),
                [],
            ],
            $item instanceof Brand => [
                BrandImageQuery::create()->findByBrandId($item->getId()),
                BrandDocumentQuery::create()->findByBrandId($item->getId()),
                [],
            ],
            default => [[], [], []],
        };

        $media = [];

        foreach ($images as $image) {
            $image->retainStoredFiles();
            $media[] = [$image, TheliaEvents::IMAGE_DELETE];
        }

        foreach ($documents as $document) {
            $media[] = [$document, TheliaEvents::DOCUMENT_DELETE];
        }

        foreach ($videos as $video) {
            $media[] = [$video, TheliaEvents::PRODUCT_VIDEO_DELETE];
        }

        return new self($media);
    }

    /**
     * @return list<int> the category and every category under it
     */
    private static function categoryTreeIds(Category $category): array
    {
        return [
            $category->getId(),
            ...array_map(static fn (Category $child): int => $child->getId(), CategoryQuery::findAllChild($category->getId())),
        ];
    }

    /**
     * Deletes the files of the media, their published copies included, through the
     * events that delete a single medium.
     */
    public function delete(EventDispatcherInterface $dispatcher): void
    {
        foreach ($this->media as [$medium, $eventName]) {
            $dispatcher->dispatch(new FileDeleteEvent($medium), $eventName);
        }
    }
}

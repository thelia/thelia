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

namespace Thelia\Tests\Api\Front;

use Thelia\Domain\CMS\Publication\ScheduledPublicationRunner;
use Thelia\Model\Content;
use Thelia\Model\ContentDocument;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentImage;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The front API serves what the shop publishes and nothing else, the relations and the
 * media of a content included; the admin API reads every state and writes the dates.
 */
final class ContentPublicationApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    /** @var list<string> the media files written on disk, removed after the test */
    private array $filesWritten = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        foreach ($this->filesWritten as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function testADraftContentIsNotOnTheFrontApi(): void
    {
        $draft = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $published = $this->factory->content($this->factory->folder(), ['visible' => 1]);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/contents/'.$draft->getId())->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/contents/'.$published->getId())->getStatusCode());

        $ids = $this->idsOf($this->jsonRequest('GET', '/api/front/contents?itemsPerPage=500')->getContent());
        self::assertNotContains((int) $draft->getId(), $ids);
        self::assertContains((int) $published->getId(), $ids);

        // The filter is the caller's, the rule is the shop's: asking for the hidden ones opens nothing.
        self::assertSame([], $this->idsOf($this->jsonRequest('GET', '/api/front/contents?itemsPerPage=500&visible=false')->getContent()));
    }

    public function testAContentOfAFolderNotOnlineYetIsNotOnTheFrontApi(): void
    {
        $inADraftFolder = $this->factory->content($this->factory->folder(0, ['visible' => 0]), ['visible' => 1]);
        $offlineFolder = $this->factory->folder(0, ['visible' => 0]);
        $offlineFolder->setUnpublishAt(new \DateTimeImmutable('-1 day'))->save();
        $inAnOfflineFolder = $this->factory->content($offlineFolder, ['visible' => 1]);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/contents/'.$inADraftFolder->getId())->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/contents/'.$inAnOfflineFolder->getId())->getStatusCode(), 'an unpublished folder keeps its contents reachable, as an invisible folder did');
    }

    public function testADraftFolderIsNotOnTheFrontApi(): void
    {
        $draft = $this->factory->folder(0, ['visible' => 0]);
        $published = $this->factory->folder(0, ['visible' => 1]);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/folders/'.$draft->getId())->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/folders/'.$published->getId())->getStatusCode());
        self::assertNotContains((int) $draft->getId(), $this->idsOf($this->jsonRequest('GET', '/api/front/folders?itemsPerPage=500')->getContent()));
    }

    public function testThePublishedContentCarriesItsStateAndNotItsDates(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 1]);
        $folder->setPublishAt(new \DateTimeImmutable('2026-10-01 09:00:00'))->setUnpublishAt(new \DateTimeImmutable('+1 month'))->save();
        $published = $this->factory->content($folder, ['visible' => 1]);
        $published->setPublishAt(new \DateTimeImmutable('2026-10-01 10:00:00'))->setUnpublishAt(new \DateTimeImmutable('+1 month'))->save();

        $content = json_decode((string) $this->jsonRequest('GET', '/api/front/contents/'.$published->getId())->getContent(), true);
        $folderData = json_decode((string) $this->jsonRequest('GET', '/api/front/folders/'.$folder->getId())->getContent(), true);

        self::assertSame('published', $content['publicationState'], 'a front reads published rows only, so the state says nothing more');
        self::assertArrayNotHasKey('publishAt', $content, 'when a row went online, or goes offline, is the back office\'s');
        self::assertArrayNotHasKey('unpublishAt', $content);
        self::assertArrayNotHasKey('publishAt', $folderData);
        self::assertArrayNotHasKey('unpublishAt', $folderData);
    }

    public function testTheAdminApiReadsEveryStateAndWritesTheDates(): void
    {
        $token = $this->authenticateAsAdmin();
        $draft = $this->factory->content($this->factory->folder(), ['visible' => 0]);

        $data = json_decode((string) $this->jsonRequest('GET', '/api/admin/contents/'.$draft->getId(), token: $token)->getContent(), true);
        self::assertSame('draft', $data['publicationState']);

        $response = $this->jsonRequest('PATCH', '/api/admin/contents/'.$draft->getId(), [
            'publishAt' => '2026-12-01T09:00:00+00:00',
            'unpublishAt' => '2026-12-08T09:00:00+00:00',
        ], $token, 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('scheduled', $data['publicationState']);
        self::assertSame(self::inTheTimezoneOfPhp('2026-12-01T09:00:00+00:00'), ContentQuery::create()->findPk($draft->getId())->getPublishAt('Y-m-d H:i:s'));
        self::assertNotNull($data['publishAt']);
    }

    /**
     * A `DATETIME` column keeps neither a timezone nor a fraction of a second: a date
     * sent in another zone is brought to the zone of PHP, which every "now" of the
     * publication is read in, and MySQL would round the fraction up where MariaDB
     * truncates it.
     */
    public function testTheAdminApiWritesADateInTheTimezoneOfPhpAtTheSecond(): void
    {
        $token = $this->authenticateAsAdmin();
        $draft = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $folder = $this->factory->folder(0, ['visible' => 0]);

        $content = $this->jsonRequest('PATCH', '/api/admin/contents/'.$draft->getId(), ['publishAt' => '2026-12-01T09:00:00.700+02:00'], $token, 'merge-patch+json');
        $folderResponse = $this->jsonRequest('PATCH', '/api/admin/folders/'.$folder->getId(), ['publishAt' => '2026-12-01T09:00:00.700+02:00'], $token, 'merge-patch+json');

        self::assertSame(200, $content->getStatusCode(), (string) $content->getContent());
        self::assertSame(200, $folderResponse->getStatusCode(), (string) $folderResponse->getContent());
        $expected = self::inTheTimezoneOfPhp('2026-12-01T09:00:00+02:00').'.000000';
        self::assertSame($expected, ContentQuery::create()->findPk($draft->getId())->getPublishAt('Y-m-d H:i:s.u'));
        self::assertSame($expected, FolderQuery::create()->findPk($folder->getId())->getPublishAt('Y-m-d H:i:s.u'));
    }

    public function testTheAdminApiRefusesAWithdrawalBeforeThePublication(): void
    {
        $token = $this->authenticateAsAdmin();
        $draft = $this->factory->content($this->factory->folder(), ['visible' => 0]);

        $response = $this->jsonRequest('PATCH', '/api/admin/contents/'.$draft->getId(), [
            'publishAt' => '2026-12-08T09:00:00+00:00',
            'unpublishAt' => '2026-12-01T09:00:00+00:00',
        ], $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheAdminApiCannotPutAnEmptyContentOnline(): void
    {
        $token = $this->authenticateAsAdmin();
        $draft = $this->factory->content($this->factory->folder(), ['visible' => 0]);

        $response = $this->jsonRequest('PATCH', '/api/admin/contents/'.$draft->getId(), ['visible' => true], $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('no body in the default language', (string) $response->getContent());
        self::assertSame(0, (int) ContentQuery::create()->findPk($draft->getId())->getVisible());
    }

    public function testAContentHiddenThroughTheAdminApiIsNotPutBackOnlineByTheScheduledCommand(): void
    {
        $token = $this->authenticateAsAdmin();
        $published = $this->factory->content($this->factory->folder(), ['visible' => 1]);
        $published->setLocale('en_US')->setDescription('<p>A body</p>')->setPublishAt(new \DateTimeImmutable('-1 week'))->save();

        $response = $this->jsonRequest('PATCH', '/api/admin/contents/'.$published->getId(), ['visible' => false], $token, 'merge-patch+json');
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('unpublished', json_decode((string) $response->getContent(), true)['publicationState']);

        $this->getService(ScheduledPublicationRunner::class)->run();

        $row = ContentQuery::create()->findPk($published->getId());
        self::assertSame(0, (int) $row->getVisible());
        self::assertNotNull($row->getUnpublishAt());
    }

    public function testTheLinkOfADraftContentToItsFolderIsNotOnTheFrontApi(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 1]);
        $draft = $this->factory->content($folder, ['visible' => 0]);
        $published = $this->factory->content($folder, ['visible' => 1]);

        self::assertSame(404, $this->jsonRequest('GET', \sprintf('/api/front/content_folders/%d/folders/%d', $draft->getId(), $folder->getId()))->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', \sprintf('/api/front/content_folders/%d/folders/%d', $published->getId(), $folder->getId()))->getStatusCode());
    }

    public function testTheLinksOfAFolderOnTheFrontApiNameOnlyTheContentsTheShopShows(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 1]);
        $published = $this->factory->content($folder, ['visible' => 1]);
        $draft = $this->factory->content($folder, ['visible' => 0]);
        $heldByADraftFolder = $this->factory->content($this->factory->folder(0, ['visible' => 0]), ['visible' => 1]);
        $this->fileIn($heldByADraftFolder, $folder);

        $data = json_decode((string) $this->jsonRequest('GET', '/api/front/folders/'.$folder->getId())->getContent(), true);

        self::assertSame([(int) $published->getId()], $this->linkedIds($data['contentFolders'], 'content'));
        self::assertNotContains((int) $draft->getId(), $this->linkedIds($data['contentFolders'], 'content'));
    }

    public function testTheLinksOfAContentOnTheFrontApiNameOnlyThePublishedFolders(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 1]);
        $draftFolder = $this->factory->folder(0, ['visible' => 0]);
        $published = $this->factory->content($folder, ['visible' => 1]);
        $this->fileIn($published, $draftFolder);

        $data = json_decode((string) $this->jsonRequest('GET', '/api/front/contents/'.$published->getId())->getContent(), true);

        self::assertSame([(int) $folder->getId()], $this->linkedIds($data['contentFolders'], 'folder'));
    }

    public function testTheAssociatedContentsOfAProductOnTheFrontApiNameOnlyTheContentsTheShopShows(): void
    {
        $product = $this->product();
        $published = $this->factory->content($this->factory->folder(), ['visible' => 1]);
        $draft = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $this->associate($product, $published);
        $draftAssociation = $this->associate($product, $draft);

        $data = json_decode((string) $this->jsonRequest('GET', '/api/front/products/'.$product->getId())->getContent(), true);

        self::assertSame([(int) $published->getId()], $this->linkedIds($data['productAssociatedContents'], 'content'));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/product_associated_contents/'.$draftAssociation->getId())->getStatusCode());
    }

    public function testTheImagesAndDocumentsOfADraftContentAreNotOnTheFrontApi(): void
    {
        $draft = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $published = $this->factory->content($this->factory->folder(), ['visible' => 1]);
        $draftImage = $this->image($draft);
        $publishedImage = $this->image($published);
        $draftDocument = $this->document($draft);
        $publishedDocument = $this->document($published);

        self::assertNotContains((int) $draftImage->getId(), $this->idsOf($this->jsonRequest('GET', '/api/front/content_images?itemsPerPage=500')->getContent()));
        self::assertContains((int) $publishedImage->getId(), $this->idsOf($this->jsonRequest('GET', '/api/front/content_images?itemsPerPage=500')->getContent()));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/content_images/'.$draftImage->getId())->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/content_images/'.$publishedImage->getId())->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/content_images/'.$draftImage->getId().'/file')->getStatusCode(), 'the file behind the image is the draft itself');
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/content_images/'.$publishedImage->getId().'/file')->getStatusCode());

        self::assertNotContains((int) $draftDocument->getId(), $this->idsOf($this->jsonRequest('GET', '/api/front/content_documents?itemsPerPage=500')->getContent()));
        self::assertContains((int) $publishedDocument->getId(), $this->idsOf($this->jsonRequest('GET', '/api/front/content_documents?itemsPerPage=500')->getContent()));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/content_documents/'.$draftDocument->getId())->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/content_documents/'.$draftDocument->getId().'/file')->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/content_documents/'.$publishedDocument->getId().'/file')->getStatusCode());
    }

    public function testTheMediaOfAContentHeldByADraftFolderAreNotOnTheFrontApi(): void
    {
        $content = $this->factory->content($this->factory->folder(0, ['visible' => 0]), ['visible' => 1]);
        $image = $this->image($content);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/content_images/'.$image->getId())->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/content_images/'.$image->getId().'/file')->getStatusCode());
    }

    private function fileIn(Content $content, Folder $folder): void
    {
        (new ContentFolder())->setContentId($content->getId())->setFolderId($folder->getId())->setDefaultFolder(false)->setPosition(1)->save();
    }

    private function image(Content $content): ContentImage
    {
        $image = (new ContentImage())->setContentId($content->getId())->setVisible(1)->setFile(\sprintf('publication-%d.png', $content->getId()));
        $image->save();
        $this->writeFile($image->getUploadDir().\DIRECTORY_SEPARATOR.$image->getFile(), static fn (string $path) => imagepng(imagecreatetruecolor(1, 1), $path));

        return $image;
    }

    private function document(Content $content): ContentDocument
    {
        $document = (new ContentDocument())->setContentId($content->getId())->setVisible(1)->setFile(\sprintf('publication-%d.txt', $content->getId()));
        $document->save();
        $this->writeFile($document->getUploadDir().\DIRECTORY_SEPARATOR.$document->getFile(), static fn (string $path) => file_put_contents($path, 'A document'));

        return $document;
    }

    /**
     * @param \Closure(string): mixed $write
     */
    private function writeFile(string $path, \Closure $write): void
    {
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0o775, true);
        }

        $write($path);
        $this->filesWritten[] = $path;
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
    }

    private function associate(Product $product, Content $content): ProductAssociatedContent
    {
        $association = (new ProductAssociatedContent())->setProductId($product->getId())->setContentId($content->getId())->setPosition(1);
        $association->save();

        return $association;
    }

    /**
     * The timezone of PHP is the one every "now" of the publication is read in, and the
     * one a date is stored as: CI runs on UTC, a developer's container on local time.
     */
    private static function inTheTimezoneOfPhp(string $date): string
    {
        return (new \DateTimeImmutable($date))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return list<int>
     */
    private function linkedIds(array $links, string $end): array
    {
        return array_map(static fn (array $link): int => (int) $link[$end]['id'], $links);
    }

    /**
     * @return list<int>
     */
    private function idsOf(string|false $json): array
    {
        $data = json_decode((string) $json, true);
        $members = $data['hydra:member'] ?? $data['member'] ?? [];

        return array_map(static fn (array $member): int => (int) $member['id'], $members);
    }
}

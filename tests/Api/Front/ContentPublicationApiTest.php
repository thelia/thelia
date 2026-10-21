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
use Thelia\Model\ContentQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The front API serves what the shop publishes and nothing else; the admin API reads
 * every state and writes the dates.
 */
final class ContentPublicationApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
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

    public function testThePublishedContentCarriesItsState(): void
    {
        $published = $this->factory->content($this->factory->folder(), ['visible' => 1]);
        $published->setPublishAt(new \DateTimeImmutable('2026-10-01 10:00:00'))->save();

        $data = json_decode((string) $this->jsonRequest('GET', '/api/front/contents/'.$published->getId())->getContent(), true);

        self::assertSame('published', $data['publicationState']);
        self::assertStringStartsWith('2026-10-01T10:00:00', $data['publishAt']);
        self::assertArrayNotHasKey('unpublishAt', $data);
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
        self::assertSame('2026-12-01 09:00:00', ContentQuery::create()->findPk($draft->getId())->getPublishAt('Y-m-d H:i:s'));
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

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

namespace Thelia\Tests\Http\Flexy;

use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\CMS\Publication\Preview\PublicationPreview;
use Thelia\Domain\CMS\Publication\Preview\PublicationPreviewLinkGenerator;
use Thelia\Model\Content;
use Thelia\Model\Folder;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * A draft is not on the shop, and is shown by its signed preview link, to an
 * administrator without one, and to nobody else. The preview answer is kept out of the
 * caches and the indexes; the public page of a published content is served as before.
 */
final class PublicationPreviewTest extends WebIntegrationTestCase
{
    private const string LIMITER = 'limiter.publication_preview_per_client';

    private ?AdminSessionInjector $adminInjector = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminInjector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->adminInjector);

        // The pool the limiter writes in outlives the test database; the caller of these
        // tests starts with its whole budget.
        $this->getService(self::LIMITER)->create('127.0.0.1')->reset();
    }

    protected function tearDown(): void
    {
        $this->adminInjector?->clear();

        parent::tearDown();
    }

    public function testADraftIsNotOnTheShopAndIsShownByItsSignedLink(): void
    {
        $draft = $this->draftContent('flexy-preview-draft.html');

        $this->client->request('GET', '/flexy-preview-draft.html');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        $link = $this->getService(PublicationPreviewLinkGenerator::class)->generate(PublicationPreview::of('content', (int) $draft->getId()));
        self::assertGreaterThan(new \DateTimeImmutable('+6 days'), $link->expiresAt);

        $this->client->request('GET', $link->url);
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Preview draft body', (string) $response->getContent());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function testAContentOfAFolderNotOnlineYetIsShownByItsSignedLink(): void
    {
        $folder = $this->fixtures()->folder(0, ['visible' => 0]);
        $content = $this->content($folder, 1, 'flexy-preview-in-draft-folder.html');

        $this->client->request('GET', '/flexy-preview-in-draft-folder.html');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', $this->signedUrl('content', (int) $content->getId()));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testADraftFolderIsShownByItsSignedLink(): void
    {
        $folder = $this->fixtures()->folder(0, ['visible' => 0, 'title' => 'Preview draft folder']);

        $this->client->request('GET', $this->signedUrl('folder', (int) $folder->getId()));
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function testALinkWithoutASignatureAnswersNotFound(): void
    {
        $draft = $this->draftContent('flexy-preview-no-hash.html');

        $this->client->request('GET', '/preview/content/'.$draft->getId());

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testAnAlteredLinkAnswersForbidden(): void
    {
        $draft = $this->draftContent('flexy-preview-altered.html');
        $url = $this->signedUrl('content', (int) $draft->getId());

        $this->client->request('GET', str_replace('_hash=', '_hash=x', $url));
        $altered = $this->client->getResponse();

        // The same link pointed at another row carries a signature that does not match it.
        $other = $this->draftContent('flexy-preview-other.html');
        $this->client->request('GET', str_replace('/preview/content/'.$draft->getId(), '/preview/content/'.$other->getId(), $url));
        $retargeted = $this->client->getResponse();

        self::assertSame(403, $altered->getStatusCode());
        self::assertSame(403, $retargeted->getStatusCode());
    }

    public function testAnExpiredLinkAnswersTheSameForbidden(): void
    {
        $draft = $this->draftContent('flexy-preview-expired.html');
        $generator = new PublicationPreviewLinkGenerator(
            $this->getService(UriSigner::class),
            $this->getService(UrlGeneratorInterface::class),
            new MockClock('-30 days'),
            7,
        );
        $link = $generator->generate(PublicationPreview::of('content', (int) $draft->getId()));

        $this->client->request('GET', $link->url);
        $expired = $this->client->getResponse();

        $this->client->request('GET', str_replace('_hash=', '_hash=x', $this->signedUrl('content', (int) $draft->getId())));
        $altered = $this->client->getResponse();

        self::assertSame(403, $expired->getStatusCode());
        self::assertSame($altered->getContent(), $expired->getContent(), 'expired and altered are told apart by nothing');
    }

    public function testASignedInAdministratorNeedsNoSignature(): void
    {
        $draft = $this->draftContent('flexy-preview-admin.html');
        $this->adminInjector?->setAdmin($this->fixtures()->admin());

        $this->client->request('GET', '/preview/content/'.$draft->getId());
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testARowThatDoesNotExistAnswersNotFoundEvenWithAValidSignature(): void
    {
        $this->client->request('GET', $this->signedUrl('content', 999999999));

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testThePublicPageOfAPublishedContentIsServedAsBefore(): void
    {
        $this->content($this->fixtures()->folder(0, ['visible' => 1]), 1, 'flexy-preview-published.html');

        $this->client->request('GET', '/flexy-preview-published.html');
        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->headers->has('X-Robots-Tag'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    private function draftContent(string $url): Content
    {
        return $this->content($this->fixtures()->folder(0, ['visible' => 1]), 0, $url);
    }

    /**
     * Built on the connection, not through the base class helper: that one pushes a
     * synthetic request that becomes the main one, and the page then renders without
     * a session.
     */
    private function fixtures(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    private function content(Folder $folder, int $visible, string $url): Content
    {
        $content = $this->fixtures()->content($folder, ['visible' => $visible, 'title' => 'Preview content']);
        $content->setLocale('en_US')->setDescription('<p>Preview draft body</p>')->save($this->getPropelConnection());
        $content->setRewrittenUrl('en_US', $url);

        return $content;
    }

    private function signedUrl(string $type, int $id): string
    {
        return $this->getService(PublicationPreviewLinkGenerator::class)->generate(PublicationPreview::of($type, $id))->url;
    }
}

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

namespace Thelia\Tests\Unit\Domain\CMS;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\CMS\Content\ContentFacade;
use Thelia\Domain\CMS\Content\DTO\ContentCreateDTO;
use Thelia\Domain\CMS\Content\DTO\ContentSeoDTO;
use Thelia\Domain\CMS\Content\DTO\ContentUpdateDTO;
use Thelia\Model\Content;

class ContentFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private ContentFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->facade = new ContentFacade($this->dispatcher);
    }

    public function testCreate(): void
    {
        $dto = new ContentCreateDTO(
            title: 'My Content',
            locale: 'en_US',
            defaultFolderId: 5,
            visible: true,
        );

        $content = $this->createContentMock(10);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (ContentCreateEvent $event) use ($content) {
                    self::assertSame('My Content', $event->getTitle());
                    self::assertSame('en_US', $event->getLocale());
                    self::assertSame(5, $event->getDefaultFolder());
                    self::assertTrue($event->getVisible());

                    $event->setContent($content);

                    return true;
                }),
                TheliaEvents::CONTENT_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($content, $result);
    }

    public function testCreateMinimal(): void
    {
        $dto = new ContentCreateDTO(
            title: 'Minimal Content',
            locale: 'fr_FR',
        );

        $content = $this->createContentMock(11);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (ContentCreateEvent $event) use ($content) {
                    self::assertSame('Minimal Content', $event->getTitle());
                    self::assertSame('fr_FR', $event->getLocale());
                    self::assertSame(0, $event->getDefaultFolder());
                    self::assertTrue($event->getVisible());

                    $event->setContent($content);

                    return true;
                }),
                TheliaEvents::CONTENT_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($content, $result);
    }

    public function testUpdate(): void
    {
        $dto = new ContentUpdateDTO(
            title: 'Updated Content',
            locale: 'en_US',
            defaultFolderId: 3,
            visible: false,
            chapo: 'Short description',
            description: 'Full description',
            postscriptum: 'Footer text',
        );

        $content = $this->createContentMock(10);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (ContentUpdateEvent $event) use ($content) {
                    self::assertSame(10, $event->getContentId());
                    self::assertSame('Updated Content', $event->getTitle());
                    self::assertSame('en_US', $event->getLocale());
                    self::assertSame(3, $event->getDefaultFolder());
                    self::assertFalse($event->getVisible());
                    self::assertSame('Short description', $event->getChapo());
                    self::assertSame('Full description', $event->getDescription());
                    self::assertSame('Footer text', $event->getPostscriptum());

                    $event->setContent($content);

                    return true;
                }),
                TheliaEvents::CONTENT_UPDATE
            );

        $result = $this->facade->update(10, $dto);

        $this->assertSame($content, $result);
    }

    public function testContentCreateDTOToArray(): void
    {
        $dto = new ContentCreateDTO(
            title: 'My Content',
            locale: 'en_US',
            defaultFolderId: 5,
            visible: true,
        );

        $array = $dto->toArray();

        $this->assertSame('My Content', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame(5, $array['default_folder']);
        $this->assertTrue($array['visible']);
    }

    public function testContentCreateDTODefaultValues(): void
    {
        $dto = new ContentCreateDTO(
            title: 'Test',
            locale: 'fr_FR',
        );

        $this->assertSame(0, $dto->defaultFolderId);
        $this->assertTrue($dto->visible);
    }

    public function testContentUpdateDTOToArray(): void
    {
        $dto = new ContentUpdateDTO(
            title: 'Updated Content',
            locale: 'en_US',
            defaultFolderId: 3,
            visible: true,
            chapo: 'Short description',
            description: 'Full description',
            postscriptum: 'Footer text',
        );

        $array = $dto->toArray();

        $this->assertSame('Updated Content', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame(3, $array['default_folder']);
        $this->assertTrue($array['visible']);
        $this->assertSame('Short description', $array['chapo']);
        $this->assertSame('Full description', $array['description']);
        $this->assertSame('Footer text', $array['postscriptum']);
    }

    public function testContentUpdateDTODefaultValues(): void
    {
        $dto = new ContentUpdateDTO(
            title: 'Test',
            locale: 'fr_FR',
        );

        $this->assertSame(0, $dto->defaultFolderId);
        $this->assertTrue($dto->visible);
        $this->assertNull($dto->chapo);
        $this->assertNull($dto->description);
        $this->assertNull($dto->postscriptum);
    }

    public function testContentSeoDTOToArray(): void
    {
        $dto = new ContentSeoDTO(
            locale: 'en_US',
            url: 'my-content',
            metaTitle: 'My Content - Site',
            metaDescription: 'Discover our content',
            metaKeywords: 'content, article, blog',
        );

        $array = $dto->toArray();

        $this->assertSame('en_US', $array['locale']);
        $this->assertSame('my-content', $array['url']);
        $this->assertSame('My Content - Site', $array['meta_title']);
        $this->assertSame('Discover our content', $array['meta_description']);
        $this->assertSame('content, article, blog', $array['meta_keywords']);
    }

    public function testContentSeoDTODefaultValues(): void
    {
        $dto = new ContentSeoDTO(
            locale: 'fr_FR',
        );

        $this->assertNull($dto->url);
        $this->assertNull($dto->metaTitle);
        $this->assertNull($dto->metaDescription);
        $this->assertNull($dto->metaKeywords);
    }

    private function createContentMock(int $id): MockObject&Content
    {
        $content = $this->createMock(Content::class);
        $content->method('getId')->willReturn($id);

        return $content;
    }
}

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

namespace Thelia\Tests\Integration\Model;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\CMS\Publication\Exception\EmptyContentPublicationException;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The body guard sits on the model: an existing content going from hidden to visible is
 * refused when it has nothing to show in the default language of the shop, whichever
 * path writes the flag.
 */
final class ContentPublicationGuardTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testAPlainSaveTurningAnEmptyContentVisibleIsRefusedNamingTheLanguage(): void
    {
        $content = $this->hidden('<p>&nbsp;</p>');

        try {
            $content->setVisible(1)->save();
            self::fail('An empty content went online.');
        } catch (EmptyContentPublicationException $exception) {
            self::assertStringContainsString('(English)', $exception->getMessage(), 'the language is named as the shop writes it, not by its code');
            self::assertStringNotContainsString('en_US', $exception->getMessage());
            self::assertSame('en_US', $exception->locale);
        }
    }

    public function testTheBodyBeingSavedAlongWithTheFlagCounts(): void
    {
        $content = $this->hidden(null);

        $content->setLocale('en_US')->setDescription('<p>Written in the same save</p>')->setVisible(1)->save();

        self::assertSame(1, (int) $this->reloaded($content)->getVisible());
        self::assertSame('<p>Written in the same save</p>', $this->reloaded($content)->setLocale('en_US')->getDescription());
    }

    public function testTheBodyOfTheDefaultLanguageIsReadFromTheDatabaseWhileAnotherLanguageIsEdited(): void
    {
        $content = $this->hidden('<p>English body</p>');

        $content->setLocale('fr_FR')->setTitle('Contenu')->setDescription('')->setVisible(1)->save();

        self::assertSame(1, (int) $this->reloaded($content)->getVisible());
    }

    public function testAnotherLanguageDoesNotStandForTheDefaultOne(): void
    {
        $content = $this->hidden(null);

        $this->expectException(EmptyContentPublicationException::class);

        $content->setLocale('fr_FR')->setTitle('Contenu')->setDescription('<p>Corps en français</p>')->setVisible(1)->save();
    }

    public function testAnImageAloneIsABody(): void
    {
        $content = $this->hidden('<p><img src="/media/a.png" alt=""></p>');

        $content->setVisible(1)->save();

        self::assertSame(1, (int) $this->reloaded($content)->getVisible());
    }

    public function testANewContentCreatedVisibleIsNotChecked(): void
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 1]);

        self::assertSame(1, (int) $this->reloaded($content)->getVisible());
    }

    public function testAVisibleContentSavedAgainIsNotChecked(): void
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 1]);

        $content->setLocale('en_US')->setTitle('Renamed')->save();

        self::assertSame('Renamed', $this->reloaded($content)->setLocale('en_US')->getTitle());
    }

    public function testTheToggleEventGoesThroughTheGuard(): void
    {
        $content = $this->hidden(null);

        $this->expectException(EmptyContentPublicationException::class);

        $this->getService(EventDispatcherInterface::class)->dispatch(new ContentToggleVisibilityEvent($content), TheliaEvents::CONTENT_TOGGLE_VISIBILITY);
    }

    public function testTheUpdateEventGoesThroughTheGuardAndWritesNothing(): void
    {
        $content = $this->hidden(null);

        $event = (new ContentUpdateEvent($content->getId()))
            ->setTitle('Updated')
            ->setLocale('fr_FR')
            ->setDefaultFolder($content->getDefaultFolderId())
            ->setVisible(1)
            ->setChapo('')
            ->setDescription('<p>Seulement en français</p>')
            ->setPostscriptum('');

        try {
            $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::CONTENT_UPDATE);
            self::fail('The content went online with no body in the default language.');
        } catch (EmptyContentPublicationException) {
        }

        $row = $this->reloaded($content);
        self::assertSame(0, (int) $row->getVisible());
        self::assertNull($row->setLocale('fr_FR')->getDescription(), 'the update is rolled back as a whole');
    }

    private function hidden(?string $defaultLanguageBody): Content
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 0]);

        if (null !== $defaultLanguageBody) {
            $content->setLocale('en_US')->setDescription($defaultLanguageBody)->save();
        }

        return ContentQuery::create()->findPk($content->getId());
    }

    private function reloaded(Content $content): Content
    {
        $row = ContentQuery::create()->findPk($content->getId());
        self::assertInstanceOf(Content::class, $row);

        return $row;
    }
}

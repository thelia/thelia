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

namespace Thelia\Tests\Integration\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\ProductImageI18nTableMap;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageI18nQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The file of an image is translated: each language may show its own, and one without
 * falls back on the default language the way translated texts do.
 */
final class ImagesByLanguageTest extends ActionIntegrationTestCase
{
    use CreatesTestFiles;

    private string $defaultLocale;

    private string $otherLocale;

    private mixed $fallbackSetting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = Lang::getDefaultLanguage()->getLocale();
        $this->otherLocale = (string) LangQuery::create()->filterByByDefault(0)->orderByLocale()->findOne()?->getLocale();
        self::assertNotSame('', $this->otherLocale, 'The test database has a single language.');

        $this->fallbackSetting = ConfigQuery::read('default_lang_without_translation');
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('default_lang_without_translation', $this->fallbackSetting);
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testAnUploadLandsInTheLanguageOfTheModel(): void
    {
        $image = $this->createImage($this->otherLocale, 'other.png');

        self::assertSame([$this->otherLocale => $image->setLocale($this->otherLocale)->getOwnFile()], $this->storedFiles($image));
        self::assertNull($image->setLocale($this->defaultLocale)->getOwnFile());
    }

    public function testALanguageWithoutFileShowsTheDefaultOneWhenTheShopReplacesMissingTranslations(): void
    {
        ConfigQuery::write('default_lang_without_translation', (string) Lang::REPLACE_BY_DEFAULT_LANGUAGE);

        $image = $this->createImage($this->defaultLocale, 'default.png');
        $defaultFile = $image->setLocale($this->defaultLocale)->getFile();

        self::assertNotSame('', $defaultFile);
        self::assertSame($defaultFile, $this->reload($image)->setLocale($this->otherLocale)->getFile());
    }

    public function testALanguageWithoutFileShowsNothingWhenTheShopUsesTheRequestedLanguageOnly(): void
    {
        ConfigQuery::write('default_lang_without_translation', (string) Lang::STRICTLY_USE_REQUESTED_LANGUAGE);

        $image = $this->createImage($this->defaultLocale, 'default.png');

        self::assertNotSame('', $this->reload($image)->setLocale($this->defaultLocale)->getFile());
        self::assertSame('', $this->reload($image)->setLocale($this->otherLocale)->getFile());
    }

    public function testTheFileOfTheLanguageWinsOverTheDefaultOne(): void
    {
        ConfigQuery::write('default_lang_without_translation', (string) Lang::REPLACE_BY_DEFAULT_LANGUAGE);

        $image = $this->createImage($this->defaultLocale, 'default.png');
        $this->replaceFile($image, $this->otherLocale, 'other.png');

        $image = $this->reload($image);
        $defaultFile = $image->setLocale($this->defaultLocale)->getFile();
        $otherFile = $image->setLocale($this->otherLocale)->getFile();

        self::assertNotSame($defaultFile, $otherFile);
        self::assertFileExists($image->getUploadDir().DS.$defaultFile);
        self::assertFileExists($image->getUploadDir().DS.$otherFile);
    }

    /**
     * The default language has no translation here: looking for its file must not leave
     * an empty one behind for the next save() to write.
     */
    public function testReadingAFallbackWritesNoTranslation(): void
    {
        ConfigQuery::write('default_lang_without_translation', (string) Lang::REPLACE_BY_DEFAULT_LANGUAGE);

        $image = $this->reload($this->createImage($this->otherLocale, 'other.png'));
        $thirdLocale = (string) LangQuery::create()->filterByLocale([$this->defaultLocale, $this->otherLocale], Criteria::NOT_IN)->orderByLocale()->findOne()?->getLocale();
        self::assertNotSame('', $thirdLocale, 'The test database has fewer than three languages.');

        self::assertSame('', $image->setLocale($thirdLocale)->getFile());
        $image->setLocale($this->otherLocale)->setVisible(0)->save();

        self::assertSame(
            [$this->otherLocale],
            ProductImageI18nQuery::create()->filterById($image->getId())->select(['Locale'])->find()->getData(),
        );
    }

    /**
     * After an upgrade every language of an image shares the same file: replacing it in
     * one language must leave it on disk for the others.
     */
    public function testReplacingASharedFileKeepsItForTheOtherLanguages(): void
    {
        $image = $this->createImage($this->defaultLocale, 'shared.png');
        $shared = $image->setLocale($this->defaultLocale)->getOwnFile();
        $image->setLocale($this->otherLocale)->setFile($shared)->save();

        $this->replaceFile($this->reload($image), $this->otherLocale, 'replacement.png');

        $image = $this->reload($image);
        self::assertSame($shared, $image->setLocale($this->defaultLocale)->getOwnFile());
        self::assertFileExists($image->getUploadDir().DS.$shared, 'The default language still shows it.');

        $replacement = $image->setLocale($this->otherLocale)->getOwnFile();
        $this->replaceFile($image, $this->defaultLocale, 'default-replacement.png');

        self::assertFileDoesNotExist($image->getUploadDir().DS.$shared, 'No language shows it any more.');
        self::assertFileExists($image->getUploadDir().DS.$replacement);
    }

    public function testTwoLanguagesUploadingTheSameFileNameDoNotOverwriteEachOther(): void
    {
        $image = $this->createImage($this->defaultLocale, 'same-name.png');
        $this->replaceFile($this->reload($image), $this->otherLocale, 'same-name.png');

        $image = $this->reload($image);
        $defaultFile = $image->setLocale($this->defaultLocale)->getOwnFile();
        $otherFile = $image->setLocale($this->otherLocale)->getOwnFile();

        self::assertNotSame($defaultFile, $otherFile);
        self::assertFileExists($image->getUploadDir().DS.$defaultFile);
        self::assertFileExists($image->getUploadDir().DS.$otherFile);
    }

    public function testDeletingAnImageRemovesTheFileOfEveryLanguage(): void
    {
        $image = $this->createImage($this->defaultLocale, 'default.png');
        $this->replaceFile($this->reload($image), $this->otherLocale, 'other.png');

        $image = $this->reload($image);
        $paths = array_map(static fn (string $file): string => $image->getUploadDir().DS.$file, $image->getStoredFiles());
        self::assertCount(2, $paths);

        $this->dispatch(new FileDeleteEvent($image), TheliaEvents::IMAGE_DELETE);

        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
        }
    }

    public function testACloneKeepsTheFileOfEachLanguage(): void
    {
        $image = $this->createImage($this->defaultLocale, 'default.png');
        $this->replaceFile($this->reload($image), $this->otherLocale, 'other.png');
        $source = $this->reload($image);
        $sourceFiles = $this->storedFiles($source);

        $clonedProduct = $this->createProduct();
        $cloneEvent = new ProductCloneEvent('CLONE-'.$clonedProduct->getId(), $this->defaultLocale, $source->getProduct());
        $cloneEvent->setClonedProduct($clonedProduct);

        $this->dispatch($cloneEvent, TheliaEvents::FILE_CLONE);

        $clone = ProductImageQuery::create()->findOneByProductId($clonedProduct->getId());
        self::assertNotNull($clone);

        $files = $this->storedFiles($clone);
        foreach ($files as $file) {
            $this->trackFileForCleanup($clone->getUploadDir().DS.$file);
            self::assertFileExists($clone->getUploadDir().DS.$file);
        }

        self::assertArrayHasKey($this->defaultLocale, $files);
        self::assertArrayHasKey($this->otherLocale, $files);
        self::assertNotSame($files[$this->defaultLocale], $files[$this->otherLocale]);
        self::assertFileEquals($source->getUploadDir().DS.$sourceFiles[$this->otherLocale], $clone->getUploadDir().DS.$files[$this->otherLocale]);
        self::assertSame($sourceFiles, $this->storedFiles($source), 'The source keeps its files.');

        foreach ($sourceFiles as $file) {
            self::assertFileExists($source->getUploadDir().DS.$file);
        }
    }

    private function createProduct(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
    }

    private function createImage(string $locale, string $name): ProductImage
    {
        $model = new ProductImage();
        $model->setProductId($this->createProduct()->getId())->setVisible(1)->setLocale($locale)->setTitle('Image');

        $event = new FileCreateOrUpdateEvent($model->getProductId());
        $event
            ->setModel($model)
            ->setUploadedFile($this->createUploadedFile($this->createTestPng(), $name, 'image/png'))
            ->setParentName('Product');

        $this->dispatch($event, TheliaEvents::IMAGE_SAVE);

        $this->trackFileForCleanup($model->getUploadDir().DS.$model->getOwnFile());

        return $model;
    }

    private function replaceFile(ProductImage $image, string $locale, string $name): void
    {
        $oldModel = clone $image;
        $image->setLocale($locale);

        $event = new FileCreateOrUpdateEvent($image->getProductId());
        $event
            ->setModel($image)
            ->setUploadedFile($this->createUploadedFile($this->createTestPng(), $name, 'image/png'));
        $event->setOldModel($oldModel);

        $this->dispatch($event, TheliaEvents::IMAGE_UPDATE);

        $this->trackFileForCleanup($image->getUploadDir().DS.$image->getOwnFile());
    }

    private function reload(ProductImage $image): ProductImage
    {
        ProductImageTableMap::clearInstancePool();
        ProductImageI18nTableMap::clearInstancePool();

        return ProductImageQuery::create()->findPk($image->getId());
    }

    /**
     * @return array<string, string> the file each language stores, by locale
     */
    private function storedFiles(ProductImage $image): array
    {
        $files = [];

        foreach (ProductImageI18nQuery::create()->filterById($image->getId())->find() as $translation) {
            if (null !== $translation->getFile() && '' !== $translation->getFile()) {
                $files[$translation->getLocale()] = $translation->getFile();
            }
        }

        return $files;
    }
}

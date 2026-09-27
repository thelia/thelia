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

namespace Thelia\Tests\Http\BackOffice;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Admin;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\ProductImageI18nTableMap;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * The image edit screen of the back office replaces the file of the language being edited,
 * and leaves the other languages on theirs.
 */
final class ImageFileByLanguageTest extends WebIntegrationTestCase
{
    use CreatesTestFiles;

    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        if (!class_exists('BackOfficeDefaultTwigBundle\\Controller\\File\\FileController')) {
            self::markTestSkipped('The installed back-office theme has no image edit screen.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        $this->factory = new FixtureFactory($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testAFileUploadedWhileEditingALanguageIsStoredForThatLanguageOnly(): void
    {
        $defaultLocale = Lang::getDefaultLanguage()->getLocale();
        $otherLang = LangQuery::create()->filterByByDefault(0)->filterByActive(1)->orderByLocale()->findOne();
        self::assertNotNull($otherLang, 'The test database has a single active language.');

        $image = $this->createImage($defaultLocale);
        $defaultFile = (string) $image->setLocale($defaultLocale)->getOwnFile();

        $this->loginAs($this->factory->admin());

        $url = '/admin/image/type/product/'.$image->getId().'/update';
        $crawler = $this->client->request('GET', $url.'?edit_language_id='.$otherLang->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('[data-testid="bo-image-edit-save-stay"]')->form();
        self::assertSame($otherLang->getLocale(), $form['thelia_image_modification[locale]']->getValue());
        $form['thelia_image_modification[title]'] = 'Image in another language';
        $form['thelia_image_modification[file]']->upload($this->pngPath());
        $this->client->submit($form);

        self::assertSame(302, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        $image = $this->reload($image);
        $otherFile = $image->setLocale($otherLang->getLocale())->getOwnFile();

        self::assertNotNull($otherFile, 'The edited language got the uploaded file.');
        self::assertNotSame($defaultFile, $otherFile);
        self::assertSame($defaultFile, $image->setLocale($defaultLocale)->getOwnFile(), 'The default language keeps its file.');
        self::assertFileExists($image->getUploadDir().DS.$defaultFile);
        self::assertFileExists($image->getUploadDir().DS.$otherFile);

        $otherPage = (string) $this->client->request('GET', $url.'?edit_language_id='.$otherLang->getId())->html();
        self::assertStringContainsString($otherFile, $otherPage);

        $defaultPage = (string) $this->client->request('GET', $url.'?edit_language_id='.Lang::getDefaultLanguage()->getId())->html();
        self::assertStringContainsString($defaultFile, $defaultPage);
        self::assertStringNotContainsString($otherFile, $defaultPage);
    }

    private function createImage(string $locale): ProductImage
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        $model = new ProductImage();
        $model->setProductId($product->getId())->setVisible(1)->setLocale($locale)->setTitle('Image');

        $event = new FileCreateOrUpdateEvent($product->getId());
        $event
            ->setModel($model)
            ->setUploadedFile(new UploadedFile($this->pngPath(), 'default.png', 'image/png', null, true))
            ->setParentName('Product');

        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::IMAGE_SAVE);

        return $this->reload($model);
    }

    /**
     * A real file name with its extension: the browser sends it as the client name, and the
     * shop upload policy reads the extension off it.
     */
    private function pngPath(): string
    {
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('thelia_test_img_').'.png';
        rename($this->createTestPng(), $path);
        $this->trackFileForCleanup($path);

        return $path;
    }

    private function reload(ProductImage $image): ProductImage
    {
        ProductImageTableMap::clearInstancePool();
        ProductImageI18nTableMap::clearInstancePool();

        $reloaded = ProductImageQuery::create()->findPk($image->getId());
        self::assertNotNull($reloaded);

        foreach ($reloaded->getStoredFiles() as $file) {
            $this->trackFileForCleanup($reloaded->getUploadDir().DS.$file);
        }

        return $reloaded;
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }
}

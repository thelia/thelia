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

namespace Thelia\Tests\Unit\BackOfficeDefaultTwig\Form;

use BackOfficeDefaultTwigBundle\Form\File\VideoMetadataType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;

/**
 * The edition form of a product video, whose source can be replaced: by another
 * address, by a file, or by nothing at all when the merchant is only fixing the
 * wording.
 */
final class VideoMetadataTypeTest extends TestCase
{
    protected function setUp(): void
    {
        // The core is tested against the tip of the theme, which may predate the
        // product videos.
        if (!class_exists(VideoMetadataType::class)) {
            self::markTestSkipped('The installed back-office theme predates the product media screens.');
        }

        parent::setUp();
    }

    public function testAnEditionOfTheWordingAloneNamesNoSource(): void
    {
        $form = $this->submit(['title' => 'Demo', 'alt' => 'A demonstration of the bag']);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        // Nothing named: the controller reads both as "keep the source you have".
        $this->assertNull($form->getData()['url']);
        $this->assertNull($form->getData()['file']);
    }

    public function testAnAddressReplacesTheSource(): void
    {
        $form = $this->submit(['url' => 'https://vimeo.com/76979871']);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        $this->assertSame('https://vimeo.com/76979871', $form->getData()['url']);
    }

    public function testAFileReplacesTheSource(): void
    {
        $form = $this->submit([], ['file' => $this->uploadedFile()]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        $this->assertInstanceOf(UploadedFile::class, $form->getData()['file']);
    }

    public function testAnAddressAndAFileTogetherAreRefused(): void
    {
        // Two sources leave no way to say which one the merchant meant to keep.
        $form = $this->submit(['url' => 'https://vimeo.com/76979871'], ['file' => $this->uploadedFile()]);

        $this->assertFalse($form->isValid());
    }

    public function testAnAltLongerThanTheColumnIsRefused(): void
    {
        $form = $this->submit(['alt' => str_repeat('a', 256)]);

        $this->assertFalse($form->isValid());
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $files
     */
    private function submit(array $values, array $files = []): FormInterface
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            // Without it, an uploaded file never reaches the form: the native request
            // handler does not recognise an UploadedFile as an upload.
            ->addExtension(new HttpFoundationExtension())
            ->addType(new VideoMetadataType(new IdentityTranslator()))
            ->getFormFactory();

        $form = $factory->createNamed('thelia_product_video_modification', VideoMetadataType::class);
        // The screen always posts the video it edits and the language it edits in.
        $form->submit(['id' => '1', 'locale' => 'en_US'] + $values + $files);

        return $form;
    }

    private function uploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'video').'.mp4';
        file_put_contents($path, 'not a real video');

        return new UploadedFile($path, 'demo.mp4', 'video/mp4', null, true);
    }
}

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

use BackOfficeDefaultTwigBundle\Form\File\VideoType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;
use Thelia\Domain\Media\Video\VideoProviderResolver;

final class VideoTypeTest extends TestCase
{
    protected function setUp(): void
    {
        // The core is tested against the tip of the theme, which may predate the
        // product videos: VideoType ships with the alt text and the video screens,
        // so its absence means the theme under test has none of them yet.
        if (!class_exists(VideoType::class)) {
            self::markTestSkipped('The installed back-office theme predates the product media screens.');
        }

        parent::setUp();
    }

    public function testAnAddressOfAnEnabledPlatformIsAccepted(): void
    {
        $form = $this->submit(['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Demo']);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
    }

    public function testAnUnknownAddressIsRefusedAndNamesTheEnabledPlatforms(): void
    {
        $form = $this->submit(['url' => 'https://example.com/video/1']);

        $this->assertFalse($form->isValid());

        $message = (string) $form->getErrors(true, false);
        $this->assertStringContainsString('YouTube', $message);
        $this->assertStringContainsString('Vimeo', $message);
        // Dailymotion is off in this shop: naming it would send a merchant down a
        // path his own configuration refuses.
        $this->assertStringNotContainsString('Dailymotion', $message);
    }

    public function testAnAddressOfADisabledPlatformIsRefused(): void
    {
        $form = $this->submit(['url' => 'https://www.dailymotion.com/video/x8abcde']);

        $this->assertFalse($form->isValid());
    }

    public function testAFileAloneIsAccepted(): void
    {
        $form = $this->submit(['title' => 'Assembly'], ['file' => $this->uploadedFile()]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
    }

    public function testAnAddressAndAFileTogetherAreRefused(): void
    {
        $form = $this->submit(['url' => 'https://youtu.be/dQw4w9WgXcQ'], ['file' => $this->uploadedFile()]);

        $this->assertFalse($form->isValid());
    }

    public function testNeitherAnAddressNorAFileIsRefused(): void
    {
        $form = $this->submit(['url' => '', 'title' => 'Demo']);

        $this->assertFalse($form->isValid());
    }

    public function testTheAlternativeTextIsCarriedByTheAddForm(): void
    {
        // Accessibility belongs to the moment a video is added, not to a second
        // screen a merchant may never open.
        $form = $this->submit(['url' => 'https://youtu.be/dQw4w9WgXcQ', 'alt' => 'Demonstration video of the bag']);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        $this->assertSame('Demonstration video of the bag', $form->getData()['alt']);
    }

    public function testAnAltOfTwoHundredAndFiftyFiveCharactersIsAccepted(): void
    {
        $form = $this->submit(['url' => 'https://youtu.be/dQw4w9WgXcQ', 'alt' => str_repeat('a', 255)]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
    }

    public function testALongerAltIsRefusedRatherThanSentToTheDriver(): void
    {
        $form = $this->submit(['url' => 'https://youtu.be/dQw4w9WgXcQ', 'alt' => str_repeat('a', 256)]);

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
            // Out of the kernel: the resolver carries its own platform list instead
            // of reading the shop configuration.
            ->addType(new VideoType(new IdentityTranslator(), new VideoProviderResolver('youtube,vimeo')))
            ->getFormFactory();

        $form = $factory->createNamed('thelia_product_video_creation', VideoType::class);
        $form->submit($values + $files);

        return $form;
    }

    private function uploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'video').'.mp4';
        file_put_contents($path, 'not a real video');

        return new UploadedFile($path, 'demo.mp4', 'video/mp4', null, true);
    }
}

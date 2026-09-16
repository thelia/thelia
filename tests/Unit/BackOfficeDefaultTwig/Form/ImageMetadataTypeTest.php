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

use BackOfficeDefaultTwigBundle\Form\File\ImageMetadataType;
use BackOfficeDefaultTwigBundle\Form\File\VideoType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;

final class ImageMetadataTypeTest extends TestCase
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

    public function testAltAndDecorativeAreSubmitted(): void
    {
        $form = $this->submit([
            'id' => '7',
            'locale' => 'fr_FR',
            'title' => 'Sac',
            'alt' => 'Sac en cuir vu de face',
            'decorative' => '1',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));

        $data = $form->getData();
        $this->assertSame('Sac en cuir vu de face', $data['alt']);
        $this->assertTrue($data['decorative']);
    }

    public function testDecorativeIsFalseWhenTheBoxIsNotTicked(): void
    {
        // An unticked checkbox posts nothing at all: the form has to read that
        // absence as "not decorative", not as "the caller said nothing".
        $form = $this->submit([
            'id' => '7',
            'locale' => 'fr_FR',
            'title' => 'Sac',
            'alt' => 'Sac en cuir vu de face',
        ]);

        $this->assertFalse($form->getData()['decorative']);
    }

    public function testAltIsOptional(): void
    {
        $form = $this->submit([
            'id' => '7',
            'locale' => 'fr_FR',
            'title' => 'Sac',
        ]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        $this->assertNull($form->getData()['alt']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function submit(array $values): FormInterface
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new ImageMetadataType(new IdentityTranslator()))
            ->getFormFactory();

        $form = $factory->createNamed('thelia_image_modification', ImageMetadataType::class);
        $form->submit($values);

        return $form;
    }
}

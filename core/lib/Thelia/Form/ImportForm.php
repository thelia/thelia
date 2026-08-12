<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Handler\ImportHandler;
use Thelia\Model\LangQuery;

/**
 * Class ImportForm.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportForm extends BaseForm
{
    public function __construct(
        private readonly ImportHandler $importHandler,
    ) {
    }

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('file_upload', FileType::class, [
            'label' => $this->translator->trans('File to upload'),
            'label_attr' => ['for' => 'file_to_upload'],
            'required' => true,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Callback(
                        [$this, 'checkFileFormat']
                    ),
                ],
            ])
            ->add('language', IntegerType::class, [
                'label' => $this->translator->trans('Language'),
                'label_attr' => ['for' => 'language'],
                'required' => true,
                'constraints' => [
                    new Assert\Callback(
                        [$this, 'checkLanguage']
                    ),
                ],
            ])
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'thelia_import';
    }

    /**
     * The page advertises the formats the import handlers can read; only a NotNull
     * used to stand between the request and the import cache directory.
     */
    public function checkFileFormat($value, ExecutionContextInterface $context): void
    {
        if (!$value instanceof UploadedFile) {
            return;
        }

        try {
            $this->importHandler->validateUpload($value->getClientOriginalName());
        } catch (FormValidationException $exception) {
            $context->addViolation($exception->getMessage());
        }
    }

    public function checkLanguage($value, ExecutionContextInterface $context): void
    {
        if (null === LangQuery::create()->findPk($value)) {
            $context->addViolation(
                $this->translator->trans(
                    "The language \"%id\" doesn't exist",
                    [
                        '%id' => $value,
                    ]
                )
            );
        }
    }
}

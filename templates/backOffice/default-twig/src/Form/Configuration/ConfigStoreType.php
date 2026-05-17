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

namespace BackOfficeDefaultTwigBundle\Form\Configuration;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ConfigStoreType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('store_name', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Store name'),
                'attr' => ['placeholder' => $this->translator->trans('Used in your store front')],
            ])
            ->add('store_description', TextareaType::class, [
                'required' => false,
                'label' => $this->translator->trans('Store description'),
            ])
            ->add('store_business_id', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('Business ID'),
                'attr' => ['placeholder' => $this->translator->trans('Store Business Identification Number (SIRET, etc).')],
            ])
            ->add('store_email', TextType::class, [
                'constraints' => [new NotBlank(), new Email()],
                'label' => $this->translator->trans('Store email address'),
                'help' => $this->translator->trans('This is the contact email address, and the sender email of all e-mails sent by your store.'),
            ])
            ->add('store_notification_emails', TextType::class, [
                'constraints' => [new NotBlank(), new Callback($this->checkEmailList(...))],
                'label' => $this->translator->trans('Email addresses of notification recipients'),
                'help' => $this->translator->trans('A comma separated list of email addresses.'),
            ])
            ->add('store_phone', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('Phone'),
            ])
            ->add('store_fax', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('Fax'),
            ])
            ->add('store_address1', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Street Address'),
            ])
            ->add('store_address2', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('Additional address line'),
            ])
            ->add('store_address3', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('Additional address line'),
            ])
            ->add('store_zipcode', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Zip code'),
            ])
            ->add('store_city', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('City'),
            ])
            ->add('store_country', ChoiceType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Country'),
                'choices' => $options['country_choices'],
                'placeholder' => false,
            ])
            ->add('favicon_file', FileType::class, [
                'required' => false,
                'constraints' => [new Image(['mimeTypes' => ['image/png', 'image/x-icon']])],
                'label' => $this->translator->trans('Favicon image'),
                'help' => $this->translator->trans('Icon of the website. Only PNG and ICO files are allowed.'),
            ])
            ->add('logo_file', FileType::class, [
                'required' => false,
                'constraints' => [new Image()],
                'label' => $this->translator->trans('Store logo'),
            ])
            ->add('banner_file', FileType::class, [
                'required' => false,
                'constraints' => [new Image()],
                'label' => $this->translator->trans('Banner'),
                'help' => $this->translator->trans('Banner of the website. Used in e-mails sent to customers.'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('country_choices')
            ->setAllowedTypes('country_choices', 'array')
            ->setDefaults([
                'csrf_token_id' => 'admin.config-store',
            ]);
    }

    public function checkEmailList(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_string($value) || $value === '') {
            return;
        }

        $emailValidator = new Email();
        foreach (preg_split('/[,;]/', $value) ?: [] as $email) {
            $context->getValidator()->validate(trim($email), $emailValidator);
        }
    }
}

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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MailingSystemType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('Enable SMTP delivery'),
            ])
            ->add('host', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('SMTP host'),
            ])
            ->add('port', IntegerType::class, [
                'required' => false,
                'label' => $this->translator->trans('SMTP port'),
            ])
            ->add('encryption', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'None' => '',
                    'SSL' => 'ssl',
                    'TLS' => 'tls',
                ],
                'label' => $this->translator->trans('Encryption'),
                'placeholder' => false,
            ])
            ->add('username', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('Username'),
            ])
            ->add('password', PasswordType::class, [
                'required' => false,
                'always_empty' => false,
                'label' => $this->translator->trans('Password'),
            ])
            ->add('auth_mode', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'None' => '',
                    'Plain' => 'plain',
                    'Login' => 'login',
                    'CRAM-MD5' => 'cram-md5',
                ],
                'label' => $this->translator->trans('Authentication mode'),
                'placeholder' => false,
            ])
            ->add('timeout', IntegerType::class, [
                'required' => false,
                'label' => $this->translator->trans('Connection timeout (seconds)'),
            ])
            ->add('source_ip', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('Source IP'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'admin.mailing-system']);
    }
}

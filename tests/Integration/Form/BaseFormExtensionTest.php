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

namespace Thelia\Tests\Integration\Form;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormFactoryBuilderInterface;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validation;
use Thelia\Form\CouponCode;
use Thelia\Test\IntegrationTestCase;

/**
 * The form factory builder handed to BaseForm carries what modules register
 * through the thelia.forms.extension, thelia.form.type and
 * thelia.form.type_extension tags. A form built without it silently ignores
 * every one of them.
 */
final class BaseFormExtensionTest extends IntegrationTestCase
{
    public function testATypeExtensionAddedToTheInjectedBuilderReachesTheForm(): void
    {
        $builder = (new FormFactoryBuilder(true))
            ->addTypeExtension($this->probeTypeExtension('from-type-extension-tag'));

        $view = $this->buildCouponCodeFormWith($builder)->createView();

        self::assertSame('from-type-extension-tag', $view->vars['probe_marker'] ?? null);
    }

    public function testAFormExtensionAddedToTheInjectedBuilderReachesTheForm(): void
    {
        $builder = (new FormFactoryBuilder(true))
            ->addExtension(new PreloadedExtension(
                [],
                [FormType::class => [$this->probeTypeExtension('from-form-extension-tag')]],
            ));

        $view = $this->buildCouponCodeFormWith($builder)->createView();

        self::assertSame('from-form-extension-tag', $view->vars['probe_marker'] ?? null);
    }

    public function testTheContainerBuilderKnowsTheCoreTypesAndTheHttpFoundationRequestHandler(): void
    {
        /** @var FormFactoryBuilderInterface $builder */
        $builder = $this->getService('thelia.form_factory_builder');

        $form = $builder->getFormFactory()
            ->createNamedBuilder('probe', FormType::class)
            ->add('code', TextType::class)
            ->getForm();

        self::assertTrue($form->has('code'));
        self::assertInstanceOf(HttpFoundationRequestHandler::class, $form->getConfig()->getRequestHandler());
    }

    private function buildCouponCodeFormWith(FormFactoryBuilderInterface $builder): SymfonyFormInterface
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->getService('request_stack');

        $form = new CouponCode();
        $form->init(
            $requestStack->getMainRequest(),
            $this->getService('event_dispatcher'),
            $this->getService('thelia.translator'),
            $builder,
            Validation::createValidatorBuilder(),
            $this->getService('security.csrf.token_storage'),
        );

        return $form->getForm();
    }

    private function probeTypeExtension(string $marker): FormTypeExtensionInterface
    {
        return new class($marker) extends AbstractTypeExtension {
            public function __construct(private readonly string $marker)
            {
            }

            public static function getExtendedTypes(): iterable
            {
                return [FormType::class];
            }

            public function finishView(FormView $view, SymfonyFormInterface $form, array $options): void
            {
                $view->vars['probe_marker'] = $this->marker;
            }
        };
    }
}

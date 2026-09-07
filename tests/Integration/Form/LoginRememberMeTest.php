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

use Symfony\Component\Form\FormFactoryBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validation;
use Thelia\Form\AdminLogin;
use Thelia\Form\BaseForm;
use Thelia\Form\CustomerLogin;
use Thelia\Test\IntegrationTestCase;

/**
 * Staying signed in is an offer, not a condition of signing in.
 *
 * CheckboxType is required by default, and a required checkbox is marked `required`
 * in the rendered HTML: the browser then refuses the login form until the box is
 * ticked, turning "remember me" into something nobody can decline. The server never
 * enforced it — only the markup did — which is exactly the kind of disagreement
 * this pins down.
 */
final class LoginRememberMeTest extends IntegrationTestCase
{
    public function testTheCustomerLoginNeverRequiresToBeRemembered(): void
    {
        $view = $this->buildForm(new CustomerLogin())->createView();

        self::assertFalse(
            $view->children['remember_me']->vars['required'],
            'A required remember-me box blocks the login form in the browser.',
        );
    }

    public function testTheAdminLoginNeverRequiresToBeRemembered(): void
    {
        $view = $this->buildForm(new AdminLogin())->createView();

        self::assertFalse(
            $view->children['remember_me']->vars['required'],
            'A required remember-me box blocks the login form in the browser.',
        );
    }

    private function buildForm(BaseForm $form): \Symfony\Component\Form\FormInterface
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->getService('request_stack');

        /** @var FormFactoryBuilderInterface $builder */
        $builder = $this->getService('thelia.form_factory_builder');

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
}

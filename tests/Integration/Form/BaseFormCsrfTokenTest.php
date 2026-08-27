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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Form\EmptyForm;
use Thelia\Test\IntegrationTestCase;

final class BaseFormCsrfTokenTest extends IntegrationTestCase
{
    private RequestStack $requestStack;
    private int $pushed = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestStack = $this->getService('request_stack');
    }

    protected function tearDown(): void
    {
        while ($this->pushed-- > 0) {
            $this->requestStack->pop();
        }

        parent::tearDown();
    }

    public function testTheDefaultTokenIsBoundToTheSessionAndRoundTrips(): void
    {
        $this->pushRequest();

        $rendered = $this->tokenValueOf($this->createForm());
        self::assertNotSame('csrf-token', $rendered, 'a session token is a random value, never the stateless marker');

        self::assertFalse($this->hasCsrfError($this->submit($this->createForm(), $rendered)));
        self::assertTrue($this->hasCsrfError($this->submit($this->createForm(), 'not-the-session-token')));
    }

    public function testAStatelessTokenIdRendersAConstantMarkerAndValidatesOnTheOrigin(): void
    {
        $this->pushRequest(['HTTP_SEC_FETCH_SITE' => 'same-origin']);

        $stateless = ['csrf_token_id' => 'submit'];

        self::assertSame('csrf-token', $this->tokenValueOf($this->createForm($stateless)));
        self::assertFalse($this->hasCsrfError($this->submit($this->createForm($stateless), 'csrf-token')));
    }

    public function testAStatelessTokenIsRejectedWhenTheRequestComesFromAnotherSite(): void
    {
        $this->pushRequest(['HTTP_SEC_FETCH_SITE' => 'cross-site']);

        $form = $this->submit($this->createForm(['csrf_token_id' => 'submit']), 'csrf-token');

        self::assertTrue($this->hasCsrfError($form));
    }

    public function testAStatelessTokenIsRejectedWithoutAnyOriginInformation(): void
    {
        $this->pushRequest();

        $form = $this->submit($this->createForm(['csrf_token_id' => 'submit']), 'csrf-token');

        self::assertTrue($this->hasCsrfError($form));
    }

    private function pushRequest(array $server = []): void
    {
        $request = Request::create('http://localhost/cart', 'POST', server: $server);
        $request->setSession($this->requestStack->getCurrentRequest()->getSession());

        $this->requestStack->push($request);
        ++$this->pushed;
    }

    private function createForm(array $options = []): \Symfony\Component\Form\FormInterface
    {
        /** @var TheliaFormFactory $factory */
        $factory = $this->getService(TheliaFormFactory::class);

        return $factory->createForm(EmptyForm::getName(), options: $options)->getForm();
    }

    private function tokenValueOf(\Symfony\Component\Form\FormInterface $form): string
    {
        return (string) $form->createView()->children['_token']->vars['value'];
    }

    private function hasCsrfError(\Symfony\Component\Form\FormInterface $form): bool
    {
        foreach ($form->getErrors() as $error) {
            if (str_contains($error->getMessage(), 'CSRF')) {
                return true;
            }
        }

        return false;
    }

    private function submit(\Symfony\Component\Form\FormInterface $form, string $token): \Symfony\Component\Form\FormInterface
    {
        $form->submit(['_token' => $token]);

        return $form;
    }
}

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

namespace Thelia\Tests\Integration\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Controller\Front\ContactController;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Form\Definition\FrontForm;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The contact form is rendered by a theme and submitted to the core: this pins what the
 * core does with that submission, from the validated form to the redirect.
 */
final class ContactControllerTest extends IntegrationTestCase
{
    use MailerAssertionsTrait;

    private RequestStack $requestStack;

    /** @var list<Request> */
    private array $suspendedRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestStack = $this->getService(RequestStack::class);

        // A bare install leaves the store address empty, and the message then has no
        // recipient. The transaction of the test case rolls the value back.
        ConfigQuery::create()
            ->findOneByName('store_email')
            ?->setValue('shop@example.com')
            ->save();
    }

    protected function tearDown(): void
    {
        while (null !== $this->requestStack->getCurrentRequest()) {
            $this->requestStack->pop();
        }

        foreach ($this->suspendedRequests as $request) {
            $this->requestStack->push($request);
        }

        $this->suspendedRequests = [];

        parent::tearDown();
    }

    public function testAValidSubmissionRedirectsToTheSuccessViewAndMailsTheStore(): void
    {
        $response = $this->submit();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringEndsWith('/'.ContactController::SUCCESS_VIEW, $response->getTargetUrl());

        self::assertCount(1, self::getMailerMessages());
        self::assertSame('A question about an order', self::getMailerMessages()[0]->getSubject());
    }

    public function testAFormThatNamesItsOwnSuccessUrlIsSentThere(): void
    {
        $response = $this->submit(['success_url' => '/thank-you']);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringEndsWith('/thank-you', $response->getTargetUrl());
    }

    public function testAnInvalidSubmissionGoesToTheErrorUrlAndMailsNothing(): void
    {
        $response = $this->submit(['message' => '', 'error_url' => '/contact']);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringEndsWith('/contact', $response->getTargetUrl());

        self::assertCount(0, self::getMailerMessages());
    }

    /**
     * @param array<string, string> $overrides
     */
    private function submit(array $overrides = []): Response
    {
        $payload = $overrides + [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'A question about an order',
            'message' => 'Where is my parcel?',
            '_token' => $this->renderedCsrfToken(),
        ];

        $request = Request::create('http://localhost/contact', 'POST', ['thelia_contact' => $payload]);
        $request->setSession($this->requestStack->getCurrentRequest()->getSession());

        // A form reads the *main* request of the stack, so the submission has to become
        // that request: the synthetic one the test case pushes is set aside meanwhile.
        while (null !== $suspended = $this->requestStack->pop()) {
            array_unshift($this->suspendedRequests, $suspended);
        }

        $this->requestStack->push($request);

        return $this->getService(ContactController::class)
            ->send($this->getService(EventDispatcherInterface::class));
    }

    /**
     * The token the theme would have written into the page, taken from the session the
     * submission below runs in.
     */
    private function renderedCsrfToken(): string
    {
        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(FrontForm::CONTACT)
            ->getForm();

        return (string) $form->createView()->children['_token']->vars['value'];
    }
}

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

namespace Thelia\Tests\Http\Flexy;

use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Controller\Front\ContactController;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A theme serves its pages through a catch-all that matches any single segment whatever
 * the method, so the POST of the contact page is only handled if the core route is tried
 * first. That ordering is what these tests pin.
 */
final class ContactFormSubmitTest extends WebIntegrationTestCase
{
    use MailerAssertionsTrait;

    public function testTheContactPageIsServed(): void
    {
        $this->assertPageRenders('/contact');
    }

    public function testTheSuccessPageOfTheThemeIsServed(): void
    {
        $this->assertPageRenders('/'.ContactController::SUCCESS_VIEW);
    }

    public function testPostingTheContactPageReachesTheContactController(): void
    {
        $match = $this->getService(RouterInterface::class)
            ->matchRequest(Request::create('http://localhost/contact', 'POST'));

        self::assertSame(
            [ContactController::class, 'send'],
            $match['_controller'],
            'The POST of the contact page must not be swallowed by the catch-all of the theme.',
        );
    }

    public function testASubmissionTheFormRejectsIsAnsweredWithThePageAndNoMail(): void
    {
        $this->client->request('POST', '/contact', ['thelia_contact' => [
            'name' => 'Jane Doe',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => '',
        ]]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            'thelia_contact[message]',
            (string) $this->client->getResponse()->getContent(),
            'The contact page must be served again so the visitor can correct the form.',
        );

        self::assertCount(0, self::getMailerMessages());
    }
}

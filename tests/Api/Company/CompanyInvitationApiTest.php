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

namespace Thelia\Tests\Api\Company;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Thelia\Api\Service\Company\CompanyInvitationMailer;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

/**
 * Inviting someone to a company, reading what the invitation says, and accepting it: the token goes
 * to the address invited and nowhere else, and it is worth something to that address only.
 */
final class CompanyInvitationApiTest extends CompanyApiTestCase
{
    private Company $company;

    private Customer $administrator;

    private Customer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('store_email', 'shop@test.com');
        $this->company = $this->company('Acme');
        $this->administrator = $this->buyerOf($this->company, CompanyRoleCode::ADMINISTRATOR);
        $this->buyer = $this->buyerOf($this->company);
    }

    public function testTheAdministratorInvitesAnAddressAndTheTokenIsInTheMailOnly(): void
    {
        $this->skipUnlessTheMailTemplateShipsTheInvitation();

        $response = $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'New@Acme.test', 'roleCode' => 'buyer', 'orderLimit' => '100'], $this->token($this->administrator), 'json');

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->body($response);
        self::assertSame('new@acme.test', $body['email']);
        self::assertArrayNotHasKey('token', $body);
        self::assertStringNotContainsString($this->tokenOfTheLastMail(), (string) $response->getContent());

        $mail = $this->lastMail();
        self::assertSame(['new@acme.test'], array_map(static fn ($address) => $address->getAddress(), $mail->getTo()));
        self::assertStringContainsString('Acme', (string) $mail->getSubject());
        self::assertStringContainsString('/account/company/invitation/', (string) $mail->getTextBody());
    }

    public function testTheTokenOfTheMailReadsAsTheCompanyAndTheAddressAndNothingElse(): void
    {
        $this->skipUnlessTheMailTemplateShipsTheInvitation();

        $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'new@acme.test', 'roleCode' => 'buyer', 'orderLimit' => '100'], $this->token($this->administrator), 'json');

        $read = $this->jsonRequest('GET', '/api/front/company-invitations/'.$this->tokenOfTheLastMail());

        self::assertJsonResponseSuccessful($read);
        self::assertSame(['companyName' => 'Acme', 'email' => 'new@acme.test'], $this->body($read));
    }

    public function testAnInvitationThatDoesNotStandIsTheSame404(): void
    {
        $token = $this->invitationFor('new@acme.test');

        foreach ([$token.'x', strrev($token), 'nonsense', substr($token, 0, 10)] as $wrong) {
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/company-invitations/'.$wrong)->getStatusCode(), $wrong);
        }
    }

    public function testAnInvitationOfABuyerWhoJoinedInTheMeantimeNoLongerReads(): void
    {
        $token = $this->invitationFor('colleague@acme.test');
        $this->facade->attach($this->company, $this->customer('colleague@acme.test'), 'buyer', null, null, null);
        $this->forgetMemberships();

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/company-invitations/'.$token)->getStatusCode());
    }

    public function testAnAccountSignedInWithTheInvitedAddressAcceptsIt(): void
    {
        $invited = $this->customer('new@acme.test');
        $token = $this->invitationFor('new@acme.test', 'buyer', '100');

        $response = $this->jsonRequest('POST', '/api/front/account/company/invitations/accept', ['token' => $token], $this->token($invited), 'json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['companyId' => $this->company->getId(), 'roleCode' => 'buyer'], $this->body($response));
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($invited->getId());
        self::assertSame($this->company->getId(), $link?->getCompanyId());
        self::assertSame(0, bccomp('100', (string) $link?->getOrderLimit(), 6));
    }

    public function testAnotherAccountCannotUseTheInvitation(): void
    {
        $someoneElse = $this->customer('other@acme.test');
        $token = $this->invitationFor('new@acme.test');

        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations/accept', ['token' => $token], $this->token($someoneElse), 'json')->getStatusCode());
        self::assertNull(CompanyCustomerQuery::create()->findOneByCustomerId($someoneElse->getId()));
    }

    public function testAForgedOrMissingTokenIsRefused(): void
    {
        $invited = $this->customer('new@acme.test');
        $token = $this->invitationFor('new@acme.test');
        $access = $this->token($invited);

        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations/accept', ['token' => $token.'x'], $access, 'json')->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations/accept', ['token' => 'nonsense'], $access, 'json')->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations/accept', [], $access, 'json')->getStatusCode());
        self::assertNull(CompanyCustomerQuery::create()->findOneByCustomerId($invited->getId()));
    }

    public function testAnInvitationIsUsedOnce(): void
    {
        $invited = $this->customer('new@acme.test');
        $token = $this->invitationFor('new@acme.test');
        $access = $this->token($invited);

        self::assertSame(200, $this->jsonRequest('POST', '/api/front/account/company/invitations/accept', ['token' => $token], $access, 'json')->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations/accept', ['token' => $token], $access, 'json')->getStatusCode());
    }

    public function testAnonymousCallersCannotInviteOrAccept(): void
    {
        self::assertSame(401, $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'x@acme.test', 'roleCode' => 'buyer'], format: 'json')->getStatusCode());
        self::assertSame(401, $this->jsonRequest('POST', '/api/front/account/company/invitations/accept', ['token' => 'x'], format: 'json')->getStatusCode());
    }

    public function testASimpleBuyerCannotInvite(): void
    {
        $response = $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'new@acme.test', 'roleCode' => 'buyer'], $this->token($this->buyer), 'json');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $this->mails());
    }

    public function testAManagerCannotInviteWithARoleOrLimitsAboveTheirOwn(): void
    {
        $this->skipUnlessTheMailTemplateShipsTheInvitation();

        $this->roleOf('manager', $this->company, ['order.place', 'order.view_own', 'buyer.manage', 'quote.request']);
        $manager = $this->buyerOf($this->company, 'manager', '300');
        $access = $this->token($manager);

        self::assertSame(403, $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'a@acme.test', 'roleCode' => CompanyRoleCode::ADMINISTRATOR], $access, 'json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'b@acme.test', 'roleCode' => 'buyer', 'orderLimit' => '500'], $access, 'json')->getStatusCode());
        self::assertSame(201, $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'c@acme.test', 'roleCode' => 'buyer', 'orderLimit' => '300'], $access, 'json')->getStatusCode());
        self::assertCount(1, $this->mails());
    }

    public function testAnAddressThatBelongsToABuyerIsAConflict(): void
    {
        $response = $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => $this->buyer->getEmail(), 'roleCode' => 'buyer'], $this->token($this->administrator), 'json');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([], $this->mails());
    }

    public function testTheBodyIsChecked(): void
    {
        $access = $this->token($this->administrator);

        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations', ['roleCode' => 'buyer'], $access, 'json')->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'not-an-address', 'roleCode' => 'buyer'], $access, 'json')->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'a@acme.test', 'roleCode' => 'nope'], $access, 'json')->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'a@acme.test', 'roleCode' => ['buyer']], $access, 'json')->getStatusCode());
    }

    public function testTooManyInvitationsAreRefusedWithARetryDelay(): void
    {
        $this->skipUnlessTheMailTemplateShipsTheInvitation();

        $access = $this->token($this->administrator);
        $statuses = [];

        for ($attempt = 0; $attempt < 12; ++$attempt) {
            $statuses[] = $this->jsonRequest('POST', '/api/front/account/company/invitations', ['email' => 'guest'.$attempt.'@acme.test', 'roleCode' => 'buyer'], $access, 'json')->getStatusCode();
        }

        self::assertSame(201, $statuses[0]);
        self::assertSame(429, end($statuses));
        self::assertCount(10, array_filter($statuses, static fn (int $status): bool => 201 === $status), 'The window lets ten invitations out.');
    }

    /**
     * The invitation mail is a file of the email template, a package on its own release cycle: the
     * core's CI installs the template's main branch, which may not ship it yet. Without it the mail
     * cannot be sent and the invitation answers 503.
     */
    private function skipUnlessTheMailTemplateShipsTheInvitation(): void
    {
        $templatePath = $this->getService(TemplateHelperInterface::class)->getActiveMailTemplate()->getAbsolutePath();

        if (!file_exists($templatePath.\DIRECTORY_SEPARATOR.CompanyInvitationMailer::MESSAGE_CODE.'.html.twig')) {
            self::markTestSkipped('The active email template does not ship the company invitation yet.');
        }
    }

    private function invitationFor(string $email, string $roleCode = 'buyer', ?string $orderLimit = null): string
    {
        return $this->getService(CompanyInvitationService::class)->createToken($this->company, $email, $roleCode, $this->administrator, $orderLimit, null, null);
    }

    /**
     * @return list<Email>
     */
    private function mails(): array
    {
        return array_values(array_filter(self::getMailerMessages(), static fn (RawMessage $message): bool => $message instanceof Email));
    }

    private function lastMail(): Email
    {
        $mails = $this->mails();
        self::assertNotSame([], $mails, 'No invitation was mailed.');

        return $mails[array_key_last($mails)];
    }

    private function tokenOfTheLastMail(): string
    {
        self::assertSame(1, preg_match('#/account/company/invitation/([A-Za-z0-9_.-]+)#', (string) $this->lastMail()->getTextBody(), $matches));

        return $matches[1];
    }
}

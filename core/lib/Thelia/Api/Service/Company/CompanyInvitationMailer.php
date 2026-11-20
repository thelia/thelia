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

namespace Thelia\Api\Service\Company;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Mailer\Exception\EmailNotSentException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;

/**
 * Mails the link of an invitation to the address it names, in the language of the shop: the person
 * invited has no account, so there is no language of theirs to read.
 */
final readonly class CompanyInvitationMailer
{
    public const string MESSAGE_CODE = 'company_invitation';

    public function __construct(private MailerFactory $mailerFactory)
    {
    }

    public function send(string $email, string $token, string $companyName, string $issuerName): void
    {
        try {
            $this->mailerFactory->sendEmailMessageOrFail(
                self::MESSAGE_CODE,
                [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
                [$email => $email],
                [
                    'token' => $token,
                    'company_name' => $companyName,
                    'issuer_name' => $issuerName,
                    'lifetime_in_days' => intdiv(CompanyInvitationService::LIFETIME_IN_SECONDS, 86400),
                ],
            );
        } catch (EmailNotSentException $exception) {
            throw new ServiceUnavailableHttpException(null, 'The invitation could not be sent: try again later.', $exception);
        }
    }
}

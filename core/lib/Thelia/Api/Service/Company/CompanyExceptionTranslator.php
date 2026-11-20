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

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Domain\Company\Exception\CompanyBuyerNotFoundException;
use Thelia\Domain\Company\Exception\CompanyException;
use Thelia\Domain\Company\Exception\CompanyGrantRefusedException;
use Thelia\Domain\Company\Exception\CompanyHasOrdersException;
use Thelia\Domain\Company\Exception\CompanyIdentifiersLockedException;
use Thelia\Domain\Company\Exception\CompanyInvitationRefusedException;
use Thelia\Domain\Company\Exception\CustomerAlreadyInCompanyException;
use Thelia\Domain\Company\Exception\CustomerNotInCompanyException;
use Thelia\Domain\Company\Exception\GuestCannotJoinCompanyException;
use Thelia\Domain\Company\Exception\LastCompanyAdministratorException;
use Thelia\Domain\Company\Exception\UnknownCompanyException;

/**
 * What a refusal of the company domain answers over HTTP: a state of the company that does not
 * allow the change is a 409, a change beyond the caller's own rights a 403, a company or a buyer
 * that is not there a 404, too many invitations a 429, and any other refusal a 422.
 */
final class CompanyExceptionTranslator
{
    /**
     * Runs the work and answers its domain refusals with the HTTP exception that says them.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public static function translating(callable $work): mixed
    {
        try {
            return $work();
        } catch (CompanyException $exception) {
            throw self::translate($exception);
        }
    }

    public static function translate(CompanyException $exception): \Throwable
    {
        return match (true) {
            $exception instanceof CompanyHasOrdersException,
            $exception instanceof CompanyIdentifiersLockedException,
            $exception instanceof LastCompanyAdministratorException,
            $exception instanceof CustomerAlreadyInCompanyException => new ConflictHttpException($exception->getMessage(), $exception),
            $exception instanceof CompanyInvitationRefusedException => $exception->getMessage() === CompanyInvitationRefusedException::tooManySent()->getMessage()
                ? new TooManyRequestsHttpException(null, $exception->getMessage(), $exception)
                : new ConflictHttpException($exception->getMessage(), $exception),
            $exception instanceof CompanyGrantRefusedException,
            $exception instanceof GuestCannotJoinCompanyException => new AccessDeniedHttpException($exception->getMessage(), $exception),
            $exception instanceof UnknownCompanyException,
            $exception instanceof CompanyBuyerNotFoundException,
            $exception instanceof CustomerNotInCompanyException => new NotFoundHttpException($exception->getMessage(), $exception),
            default => new UnprocessableEntityHttpException($exception->getMessage(), $exception),
        };
    }
}

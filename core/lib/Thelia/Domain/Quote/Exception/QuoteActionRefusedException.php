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

namespace Thelia\Domain\Quote\Exception;

/**
 * A buyer cannot do this to this quote, although they can read it.
 */
final class QuoteActionRefusedException extends QuoteException
{
    public const string NOT_PERMITTED = 'not_permitted';
    public const string REVISION_STALE = 'revision_stale';
    public const string COMPANY_SUSPENDED = 'company_suspended';

    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function notPermitted(): self
    {
        return new self(self::NOT_PERMITTED, 'Your role in your company does not allow you to answer quotes.');
    }

    public static function revisionIsNotTheCurrentOne(): self
    {
        return new self(self::REVISION_STALE, 'The quote has a more recent revision than the one you answered.');
    }

    public static function companySuspended(): self
    {
        return new self(self::COMPANY_SUSPENDED, 'Your company account is suspended: quotes cannot be asked for or answered until it is reactivated.');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

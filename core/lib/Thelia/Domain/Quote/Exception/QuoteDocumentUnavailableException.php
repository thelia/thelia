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
 * The printed document of a revision cannot be made: the revision is not one the buyer was sent,
 * or the shop has nothing to print it with.
 */
final class QuoteDocumentUnavailableException extends QuoteException
{
    public const string REVISION_NOT_FOUND = 'revision_not_found';
    public const string REVISION_NOT_SENT = 'revision_not_sent';
    public const string NOT_RENDERED = 'document_not_rendered';

    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function revisionNotFound(): self
    {
        return new self(self::REVISION_NOT_FOUND, 'This quote has no such revision.');
    }

    public static function revisionNotSent(): self
    {
        return new self(self::REVISION_NOT_SENT, 'Only a revision that was sent can be printed.');
    }

    public static function notRendered(): self
    {
        return new self(self::NOT_RENDERED, 'The document of this quote cannot be made now.');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

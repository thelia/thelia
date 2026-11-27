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

namespace Thelia\Api\Service\Quote;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Model\Quote;
use Thelia\Model\QuoteQuery;

/**
 * The quote the uri of an administration operation names. A quote nobody wrote answers 404: who
 * may ask at all was settled before this runs, by the right on the quotes.
 */
final readonly class AdminQuoteLookup
{
    /**
     * @param array<string, mixed> $uriVariables
     *
     * @throws NotFoundHttpException
     */
    public function fromUriVariables(array $uriVariables): Quote
    {
        $quoteId = filter_var($uriVariables['id'] ?? null, \FILTER_VALIDATE_INT);

        return (false === $quoteId || $quoteId < 1 ? null : QuoteQuery::create()->findPk($quoteId))
            ?? throw new NotFoundHttpException('No such quote.');
    }
}

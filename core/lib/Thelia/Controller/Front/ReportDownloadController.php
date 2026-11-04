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

namespace Thelia\Controller\Front;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Domain\Reporting\Mailing\ReportMailStorage;

/**
 * `GET /report-download/{file}`: the file of a report that was too big to be attached.
 *
 * The link comes from the e-mail and is signed: the path, the name the file is saved
 * under and the expiry are all covered, so an altered or expired link answers the same
 * 403 whichever part was touched. The signatures are tried under a per-client limit. The
 * file is read inside the report folder only, and the answer is never cached.
 */
#[AsController]
final readonly class ReportDownloadController
{
    private const string FALLBACK_NAME = 'report.csv';

    public function __construct(
        private UriSigner $uriSigner,
        #[Autowire(service: 'limiter.report_mail_download_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
    ) {
    }

    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        if (!$this->perClientLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        if (!$this->uriSigner->checkRequest($request)) {
            throw new AccessDeniedHttpException('This download link is not valid or has expired.');
        }

        $path = ReportMailStorage::resolve($file);

        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $name = $request->query->getString('name');

        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,150}\z/', $name)) {
            $name = self::FALLBACK_NAME;
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}

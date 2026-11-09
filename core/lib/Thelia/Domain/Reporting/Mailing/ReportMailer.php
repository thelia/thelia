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

namespace Thelia\Domain\Reporting\Mailing;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Export\ExportStorage;
use Thelia\Domain\DataTransfer\Export\MailableReportInterface;
use Thelia\Domain\DataTransfer\Export\ReportSummaryLine;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Log\Tlog;
use Thelia\Mailer\Exception\EmailNotSentException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\ReportMailing;

/**
 * Sends a report: runs the export of the mailing over its period, then mails the result,
 * one e-mail per recipient so that one refusal never reaches the others.
 *
 * The file travels attached when it is small enough, and otherwise stays on the server
 * behind a signed link that expires. An empty period still gets its e-mail, which says so.
 * The mail is handed to the server at once: the queue would carry the whole file inside
 * the message.
 */
final readonly class ReportMailer
{
    public const string MESSAGE_CODE = 'report_mail';
    public const string SERIALIZER_ID = 'thelia.csv';

    public function __construct(
        private ExportHandler $exportHandler,
        private SerializerManager $serializerManager,
        private MailerFactory $mailerFactory,
        private ReportMailingSettings $settings,
        private ReportDownloadLinkGenerator $linkGenerator,
    ) {
    }

    /**
     * @param \DateTimeImmutable $reference      the day the period is relative to
     * @param list<string>|null  $onlyRecipients when given, only those of these addresses that are still recipients of the mailing get the report
     * @param \Closure|null      $onServed       called with each address once it is dealt with for good, delivered or rejected
     *                                           as invalid, so that a caller can note its progress: a failure is not served
     */
    public function send(ReportMailing $mailing, \DateTimeImmutable $reference, ?array $onlyRecipients = null, ?\Closure $onServed = null): ReportMailingResult
    {
        $export = $this->exportHandler->getExportByRef((string) $mailing->getExportRef(), true);
        $period = ReportPeriod::from((string) $mailing->getPeriod())->resolve($reference);
        $locale = (string) $mailing->getLocale();
        $language = LangQuery::create()->findOneByLocale($locale) ?? Lang::getDefaultLanguage();
        $recipients = null === $onlyRecipients ? $mailing->getRecipientList() : $mailing->keepCurrentRecipients($onlyRecipients);

        $storeEmail = (string) ConfigQuery::getStoreEmail();

        if ('' === $storeEmail) {
            throw EmailNotSentException::storeEmailMissing(self::MESSAGE_CODE);
        }

        $export->setLocale($locale);

        $parameters = [
            'report_title' => $mailing->getTitle(),
            'export_title' => $export->getTitle() ?: $export->getRef(),
            'period_start' => $this->shortDate($period['start'], $locale),
            'period_end' => $this->shortDate($period['end'], $locale),
            'is_empty' => true,
            'summary' => [],
            'attachment_name' => null,
            'download_url' => null,
            'download_expires_at' => null,
        ];

        $rows = 0;

        try {
            $event = $this->exportHandler->export(
                $export,
                $this->serializerManager->get(self::SERIALIZER_ID),
                null,
                $language,
                false,
                false,
                $export->getHandleClassInstance()->useRangeDate() ? $period : null,
                static function (int $written) use (&$rows): void {
                    $rows = $written;
                },
            );
        } catch (DataTransferNoDataFoundException) {
            return $this->mailEach($recipients, $storeEmail, $parameters, $locale, null, ReportDeliveryMode::None, true, $onServed);
        }

        $filePath = $event->getFilePath();

        if (0 === $rows) {
            ExportStorage::discard($filePath);

            return $this->mailEach($recipients, $storeEmail, $parameters, $locale, null, ReportDeliveryMode::None, true, $onServed);
        }

        $summary = $event->getExport() instanceof MailableReportInterface ? $this->summaryOf($event->getExport()) : [];
        $parameters['is_empty'] = false;
        $parameters['summary'] = array_map(static fn (ReportSummaryLine $line): array => ['label' => $line->label, 'value' => $line->value], $summary);

        $downloadName = $this->downloadName($mailing, $period);

        if (ReportDeliveryMode::Attached === $this->settings->deliveryFor((int) filesize($filePath))) {
            $parameters['attachment_name'] = $downloadName;

            try {
                return $this->mailEach($recipients, $storeEmail, $parameters, $locale, [$filePath, $downloadName], ReportDeliveryMode::Attached, false, $onServed);
            } finally {
                ExportStorage::discard($filePath);
            }
        }

        // Whatever fails before a link exists leaves a file nobody can reach: it goes, and the failure goes on.
        try {
            $storedFile = ReportMailStorage::adopt($filePath);
        } catch (\Throwable $notAdopted) {
            ExportStorage::discard($filePath);

            throw $notAdopted;
        }

        try {
            $link = $this->linkGenerator->generate($storedFile, $downloadName);
        } catch (\Throwable $noLink) {
            ReportMailStorage::discard($storedFile);

            throw $noLink;
        }

        $parameters['download_url'] = $link->url;
        $parameters['download_expires_at'] = $this->shortDate($link->expiresAt, $locale);

        $result = $this->mailEach($recipients, $storeEmail, $parameters, $locale, null, ReportDeliveryMode::Linked, false, $onServed);

        // A link nobody got leads nowhere: the file goes, the retry makes another one.
        if ([] === $result->delivered) {
            ReportMailStorage::discard($storedFile);
        }

        return $result;
    }

    /**
     * @param list<string>                     $recipients
     * @param array<string, mixed>             $parameters
     * @param array{0: string, 1: string}|null $attachment path and name
     */
    private function mailEach(array $recipients, string $storeEmail, array $parameters, string $locale, ?array $attachment, ReportDeliveryMode $delivery, bool $empty, ?\Closure $onServed): ReportMailingResult
    {
        $delivered = [];
        $failed = [];
        $rejected = [];

        foreach ($recipients as $recipient) {
            // An address that is not one is no use trying again: it is set aside for good.
            try {
                new Address($recipient);
            } catch (RfcComplianceException|InvalidArgumentException) {
                $rejected[] = $recipient;

                if (null !== $onServed) {
                    $onServed($recipient);
                }

                continue;
            }

            try {
                $email = $this->mailerFactory->createEmailMessage(
                    self::MESSAGE_CODE,
                    [$storeEmail => (string) ConfigQuery::getStoreName()],
                    [$recipient => $recipient],
                    $parameters,
                    $locale,
                );

                if (null !== $attachment) {
                    $email->attachFromPath($attachment[0], $attachment[1], 'text/csv');
                }

                $this->mailerFactory->sendNow($email);
            } catch (\Throwable $notSent) {
                Tlog::getInstance()->addWarning(\sprintf('A report e-mail was not sent (%s): %s', $notSent::class, $notSent->getMessage()));
                $failed[] = $recipient;

                continue;
            }

            // Out of the try: a caller that fails to note its progress is not a recipient that failed.
            $delivered[] = $recipient;

            if (null !== $onServed) {
                $onServed($recipient);
            }
        }

        return new ReportMailingResult($delivered, $failed, $rejected, $empty, $delivery);
    }

    /**
     * @return list<ReportSummaryLine>
     */
    private function summaryOf(MailableReportInterface $export): array
    {
        // The figures open the e-mail, they are not the e-mail: a summary that cannot be
        // computed leaves the file to speak for itself.
        try {
            return $export->summarize();
        } catch (\Throwable $notSummarized) {
            Tlog::getInstance()->addWarning(\sprintf('The summary of a report could not be computed (%s): %s', $notSummarized::class, $notSummarized->getMessage()));

            return [];
        }
    }

    /**
     * @param array{start: \DateTimeImmutable, end: \DateTimeImmutable} $period
     */
    private function downloadName(ReportMailing $mailing, array $period): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $mailing->getTitle()))), '-');

        return \sprintf('%s-%s-%s.csv', '' === $slug ? 'report' : $slug, $period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
    }

    private function shortDate(\DateTimeInterface $date, string $locale): string
    {
        return (string) (new \IntlDateFormatter($locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, date_default_timezone_get()))->format($date);
    }
}

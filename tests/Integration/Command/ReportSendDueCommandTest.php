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

namespace Thelia\Tests\Integration\Command;

use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Command\ReportMailCommand;
use Thelia\Command\ReportSendDueCommand;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Accounting\InvalidAccountingChartException;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\Reporting\Mailing\ImmediateReportSender;
use Thelia\Domain\Reporting\Mailing\ReportDownloadLinkGenerator;
use Thelia\Domain\Reporting\Mailing\ReportDueResolver;
use Thelia\Domain\Reporting\Mailing\ReportMailer;
use Thelia\Domain\Reporting\Mailing\ReportMailingLock;
use Thelia\Domain\Reporting\Mailing\ReportMailingSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ReportMailing;
use Thelia\Model\ReportMailingQuery;
use Thelia\Tests\Integration\Domain\Reporting\Mailing\ReportMailingTestCase;
use Thelia\Tests\Support\Mailer\ObservingMailerFactory;
use Thelia\Tests\Support\Mailer\RefusingMailerFactory;

/**
 * `report:send-due` sends each period of each mailing once, retries on their own the
 * recipients a send failed for, leaves a mailing another run holds, and prints ids and
 * counts, never what the reports hold.
 */
final class ReportSendDueCommandTest extends ReportMailingTestCase
{
    private ?LockFactory $lockFactory = null;

    public function testTwoSuccessiveRunsSendThePeriodOnce(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing(['one@example.com']);

        $first = $this->runSendDue();
        $second = $this->runSendDue();

        self::assertSame(0, $first->getStatusCode(), $first->getDisplay());
        self::assertStringContainsString(\sprintf('Mailing #%d: 1 sent, 0 failed, 0 rejected.', $mailing->getId()), $first->getDisplay());
        self::assertSame('', trim($second->getDisplay()), 'nothing is due the second time');
        self::assertEmailCount(1);

        $reloaded = ReportMailingQuery::create()->findPk($mailing->getId());
        self::assertSame((new \DateTimeImmutable('yesterday'))->format('Y-m-d'), $reloaded->getLastPeriodEnd('Y-m-d'));
        self::assertNull($reloaded->getPendingRecipients());
        self::assertNotNull($reloaded->getLastSentAt());
    }

    public function testADisabledMailingIsLeftAlone(): void
    {
        $mailing = $this->mailing();
        $mailing->setEnabled(0)->save();

        $this->runSendDue();

        self::assertEmailCount(0);
    }

    public function testAnInvalidAddressIsDroppedForGoodAndNeverTriedAgain(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing(['broken address', 'good@example.com']);

        $first = $this->runSendDue();

        self::assertSame(1, $first->getStatusCode(), 'once, so the scheduler alerts');
        self::assertStringContainsString(\sprintf('Mailing #%d: 1 sent, 0 failed, 1 rejected.', $mailing->getId()), $first->getDisplay());
        self::assertEmailCount(1);
        self::assertNull(ReportMailingQuery::create()->findPk($mailing->getId())->getPendingRecipients());

        $second = $this->runSendDue();

        self::assertSame('', trim($second->getDisplay()));
        self::assertEmailCount(1);
    }

    public function testATransportFailureIsKeptAndRetriedAloneOnTheNextRun(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing(['good@example.com', 'refused@example.com']);
        $this->mailerFactory = new RefusingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            self::getContainer()->get('mailer.transports'),
            ['refused@example.com'],
        );

        $first = $this->runSendDue();

        self::assertSame(1, $first->getStatusCode());
        self::assertStringContainsString('1 sent, 1 failed, 0 rejected.', $first->getDisplay());
        self::assertSame(['refused@example.com'], ReportMailingQuery::create()->findPk($mailing->getId())->getPendingRecipientList());

        // The server takes the address again: the next run sends to it alone.
        $this->mailerFactory = null;
        $second = $this->runSendDue();

        self::assertSame(0, $second->getStatusCode(), $second->getDisplay());
        self::assertCount(2, self::getMailerMessages());
        self::assertEmailAddressContains($this->sentEmail(1), 'To', 'refused@example.com');
        self::assertNull(ReportMailingQuery::create()->findPk($mailing->getId())->getPendingRecipients());

        $this->runSendDue();
        self::assertCount(2, self::getMailerMessages(), 'and nothing more once everyone has it');
    }

    public function testTheWaitingListShrinksWithEachRecipientServedSoAnInterruptionResendsOnlyTheRest(): void
    {
        $this->orderYesterday();
        $recipients = ['one@example.com', 'two@example.com', 'three@example.com', 'four@example.com'];
        $mailing = $this->mailing($recipients);

        // What a run killed just before each mail would have left in the database.
        $leftBehind = [];
        $this->mailerFactory = new ObservingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            self::getContainer()->get('mailer.transports'),
            2,
            static function (int $alreadySent) use (&$leftBehind, $mailing): void {
                $row = ReportMailingQuery::create()->findPk($mailing->getId());
                $leftBehind[$alreadySent] = [$row->getLastPeriodEnd('Y-m-d'), $row->getPendingRecipientList()];
            },
        );

        $first = $this->runSendDue();

        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        self::assertSame($yesterday, $leftBehind[0][0], 'the period is noted before the first mail');
        self::assertSame($recipients, $leftBehind[0][1]);
        self::assertSame(['two@example.com', 'three@example.com', 'four@example.com'], $leftBehind[1][1]);
        self::assertSame(['three@example.com', 'four@example.com'], $leftBehind[2][1], 'the two served are off the list');
        self::assertSame(1, $first->getStatusCode());
        self::assertCount(2, self::getMailerMessages());

        $this->mailerFactory = null;
        $second = $this->runSendDue();

        self::assertSame(0, $second->getStatusCode(), $second->getDisplay());
        self::assertCount(4, self::getMailerMessages());
        self::assertEmailAddressContains($this->sentEmail(2), 'To', 'three@example.com');
        self::assertEmailAddressContains($this->sentEmail(3), 'To', 'four@example.com');
        self::assertNull(ReportMailingQuery::create()->findPk($mailing->getId())->getPendingRecipients());
    }

    public function testARecipientTheServerKeepsRefusingIsGivenUpAfterThreeTries(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing(['refused@example.com']);
        $tries = 0;
        $this->mailerFactory = new ObservingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            self::getContainer()->get('mailer.transports'),
            0,
            static function () use (&$tries): void {
                ++$tries;
            },
        );
        $reload = static fn (): ReportMailing => ReportMailingQuery::create()->findPk($mailing->getId());

        $first = $this->runSendDue();
        self::assertSame(1, $tries);
        self::assertSame(0, $reload()->getPendingAttempts());
        self::assertSame(['refused@example.com'], $reload()->getPendingRecipientList());

        $this->runSendDue();
        self::assertSame(2, $tries);
        self::assertSame(1, $reload()->getPendingAttempts());

        $third = $this->runSendDue();
        self::assertSame(3, $tries, 'the initial send and two retries');
        self::assertSame(1, $third->getStatusCode(), 'the scheduler is still told');
        self::assertStringContainsString(\sprintf('Mailing #%d: 0 sent, 1 failed, 0 rejected.', $mailing->getId()), $third->getDisplay());
        self::assertNull($reload()->getPendingRecipients(), 'given up for the period');
        self::assertSame(2, $reload()->getPendingAttempts());
        self::assertStringNotContainsString('refused@', $first->getDisplay().$third->getDisplay());

        $fourth = $this->runSendDue();
        self::assertSame('', trim($fourth->getDisplay()));
        self::assertSame(3, $tries, 'nothing more is tried for this period');
    }

    public function testANewPeriodStartsTheCountOfTriesOver(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing(['one@example.com']);
        $mailing
            ->setLastPeriodEnd((new \DateTimeImmutable('-2 days'))->format('Y-m-d'))
            ->setPendingRecipientList(['stale@example.com'])
            ->setPendingAttempts(2)
            ->save();

        $this->runSendDue();

        self::assertEmailCount(1);
        self::assertEmailAddressContains($this->sentEmail(0), 'To', 'one@example.com');
        self::assertSame(0, ReportMailingQuery::create()->findPk($mailing->getId())->getPendingAttempts());
    }

    public function testAMailingAnotherRunIsSendingIsSkipped(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing();
        $held = $this->lockFactory()->createLock(ReportMailingLock::KEY_PREFIX.$mailing->getId());
        self::assertTrue($held->acquire());

        try {
            $tester = $this->runSendDue();
        } finally {
            $held->release();
        }

        self::assertStringContainsString(\sprintf('Mailing #%d is being sent by another run, skipped.', $mailing->getId()), $tester->getDisplay());
        self::assertEmailCount(0);
        self::assertNull(ReportMailingQuery::create()->findPk($mailing->getId())->getLastPeriodEnd());

        $after = $this->runSendDue();
        self::assertEmailCount(1);
        self::assertSame(0, $after->getStatusCode());
    }

    public function testARetryIsNotSentToARecipientTheMerchantRemoved(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing(['kept@example.com', 'removed@example.com']);
        $this->mailerFactory = new RefusingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            self::getContainer()->get('mailer.transports'),
            ['kept@example.com', 'removed@example.com'],
        );

        $this->runSendDue();
        self::assertSame(['kept@example.com', 'removed@example.com'], ReportMailingQuery::create()->findPk($mailing->getId())->getPendingRecipientList());

        // The merchant removes one of the two, with another case for the one kept.
        ReportMailingQuery::create()->findPk($mailing->getId())->setRecipients('KEPT@example.com')->save();
        $this->mailerFactory = null;
        $retry = $this->runSendDue();

        self::assertSame(0, $retry->getStatusCode(), $retry->getDisplay());
        self::assertEmailCount(1);
        self::assertEmailAddressContains($this->sentEmail(0), 'To', 'kept@example.com');
        self::assertNull(ReportMailingQuery::create()->findPk($mailing->getId())->getPendingRecipients());
    }

    public function testARetryWhoseRecipientsWereAllRemovedSendsNothingAndEndsThePeriod(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing(['removed@example.com']);
        $mailing
            ->setRecipients('someone.else@example.com')
            ->setLastPeriodEnd((new \DateTimeImmutable('yesterday'))->format('Y-m-d'))
            ->setPendingRecipientList(['removed@example.com'])
            ->setPendingAttempts(0)
            ->save();

        $tester = $this->runSendDue();

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertEmailCount(0);
        self::assertNull(ReportMailingQuery::create()->findPk($mailing->getId())->getPendingRecipients());
        self::assertSame('', trim($this->runSendDue()->getDisplay()), 'nothing is due any more for the period');
    }

    public function testASendThatFailsAsAWholeIsGivenUpAfterThreeTries(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing(['one@example.com']);
        ConfigQuery::write('store_email', '');
        $reload = static fn (): ReportMailing => ReportMailingQuery::create()->findPk($mailing->getId());

        foreach ([0, 1, 2] as $attemptsAfterTheRun) {
            $run = $this->runSendDue();
            self::assertSame(1, $run->getStatusCode());
            self::assertStringContainsString(\sprintf('Mailing #%d could not be sent', $mailing->getId()), $run->getDisplay());
            self::assertSame($attemptsAfterTheRun, $reload()->getPendingAttempts());
        }

        self::assertNull($reload()->getPendingRecipients(), 'given up for the period after the third try');

        $fourth = $this->runSendDue();
        self::assertSame(0, $fourth->getStatusCode());
        self::assertSame('', trim($fourth->getDisplay()), 'the fourth run sends nothing');
        self::assertEmailCount(0);
    }

    public function testAFailureMeantForTheAdministratorIsExplainedAndAnyOtherIsNamedByItsTypeOnly(): void
    {
        $this->orderYesterday();
        $journal = $this->mailing(['one@example.com'], 'thelia.export.sales_journal');
        $run = $this->runSendDue();

        self::assertSame(1, $run->getStatusCode());
        self::assertStringContainsString(
            \sprintf('Mailing #%d could not be sent (The chart of accounts is incomplete: set ', $journal->getId()),
            $run->getDisplay(),
            'the message names the settings to fill',
        );
        self::assertStringNotContainsString(InvalidAccountingChartException::class, $run->getDisplay());
        ReportMailingQuery::create()->filterById($journal->getId())->delete();

        $technical = $this->mailing(['one@example.com']);
        ConfigQuery::write('store_email', '');
        $run = $this->runSendDue();

        self::assertMatchesRegularExpression(\sprintf('/^Mailing #%d could not be sent \\([A-Za-z\\\\]+\\)\\.$/m', $technical->getId()), $run->getDisplay());
    }

    public function testReportMailRefusesAMailingAnotherRunIsSending(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing();
        $held = $this->lockFactory()->createLock(ReportMailingLock::KEY_PREFIX.$mailing->getId());
        self::assertTrue($held->acquire());

        try {
            $tester = $this->runMail($mailing);
        } finally {
            $held->release();
        }

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString(\sprintf('Mailing #%d is being sent by another run, nothing was sent', $mailing->getId()), $tester->getDisplay());
        self::assertEmailCount(0);
        self::assertNull(ReportMailingQuery::create()->findPk($mailing->getId())->getLastSentAt());
    }

    public function testTheDryRunTellsWhatIsDueAndSendsNothing(): void
    {
        $mailing = $this->mailing();

        $tester = $this->runSendDue(dryRun: true);

        self::assertStringContainsString(\sprintf('Mailing #%d is due', $mailing->getId()), $tester->getDisplay());
        self::assertEmailCount(0);
        self::assertNull(ReportMailingQuery::create()->findPk($mailing->getId())->getLastSentAt());
    }

    public function testTheOutputNeverCarriesAnAmountAnAddressNorAName(): void
    {
        $order = $this->orderYesterday();
        $this->mailing(['private.person@example.com']);

        $display = $this->runSendDue()->getDisplay().$this->runMail($this->mailing(['private.person@example.com']))->getDisplay();

        self::assertStringNotContainsString('private.person', $display);
        self::assertStringNotContainsString('40', $display);
        self::assertStringNotContainsString($order->getRef(), $display);
        self::assertStringNotContainsString('Weekly sales', $display);
        self::assertMatchesRegularExpression('/^Mailing #\d+: \d+ sent, \d+ failed, \d+ rejected\.$/m', $display);
    }

    public function testReportMailSendsNowWhateverTheScheduleSaysAndMovesOnlyTheLastSendDate(): void
    {
        $this->orderYesterday();
        $mailing = $this->mailing();
        $mailing->setLastPeriodEnd((new \DateTimeImmutable('yesterday'))->format('Y-m-d'))->save();

        $tester = $this->runMail($mailing);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertEmailCount(1);
        $reloaded = ReportMailingQuery::create()->findPk($mailing->getId());
        self::assertNotNull($reloaded->getLastSentAt());
        self::assertSame((new \DateTimeImmutable('yesterday'))->format('Y-m-d'), $reloaded->getLastPeriodEnd('Y-m-d'));
        self::assertNull($reloaded->getPendingRecipients());
    }

    public function testReportMailOfAnUnknownMailingFails(): void
    {
        $tester = new CommandTester($this->mailCommand());
        $tester->execute(['id' => '999999']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('There is no mailing #999999.', $tester->getDisplay());
    }

    private function runSendDue(bool $dryRun = false): CommandTester
    {
        $command = new ReportSendDueCommand($this->reportMailer(), new ReportDueResolver(), new ReportMailingLock($this->lockFactory()), new MockClock());
        $command->setContainer(self::getContainer());

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => $dryRun]);

        return $tester;
    }

    private function runMail(ReportMailing $mailing): CommandTester
    {
        $tester = new CommandTester($this->mailCommand());
        $tester->execute(['id' => (string) $mailing->getId()]);

        return $tester;
    }

    private function mailCommand(): ReportMailCommand
    {
        $command = new ReportMailCommand(new ImmediateReportSender($this->reportMailer(), new ReportMailingLock($this->lockFactory()), new MockClock()));
        $command->setContainer(self::getContainer());

        return $command;
    }

    private function reportMailer(): ReportMailer
    {
        return new ReportMailer(
            $this->getService(ExportHandler::class),
            $this->getService(SerializerManager::class),
            $this->mailerFactory(),
            new ReportMailingSettings(),
            $this->getService(ReportDownloadLinkGenerator::class),
        );
    }

    private function lockFactory(): LockFactory
    {
        return $this->lockFactory ??= $this->getService(LockFactory::class);
    }
}

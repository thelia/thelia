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

namespace Thelia\Domain\Order\Reminder;

/**
 * The steps an unpaid order goes through, counted in hours from its creation, as the
 * merchant sets them: "24:order_payment_reminder,72:order_payment_reminder,168:cancel".
 * Each step names the message sent then; "cancel", allowed once and last, cancels the
 * order instead. An empty setting is an empty schedule: nothing is ever sent.
 */
final readonly class UnpaidOrderReminderSchedule
{
    public const MAXIMUM_STEPS = 10;

    /**
     * @param list<UnpaidOrderReminderStep> $steps by increasing delay
     */
    private function __construct(
        private array $steps,
    ) {
    }

    /**
     * @throws InvalidReminderScheduleException
     */
    public static function fromSetting(string $setting): self
    {
        $steps = [];

        foreach (explode(',', $setting) as $rawStep) {
            $rawStep = trim($rawStep);

            if ('' === $rawStep) {
                continue;
            }

            if (1 !== preg_match('/^(\d+)\s*:\s*([a-z0-9_]+)$/', $rawStep, $matches) || 0 === (int) $matches[1]) {
                throw new InvalidReminderScheduleException(\sprintf('"%s" is not a reminder step: write the delay in whole hours, then the code of the message to send or "cancel", as in "24:order_payment_reminder".', $rawStep));
            }

            $delay = (int) $matches[1];

            if (isset($steps[$delay])) {
                throw new InvalidReminderScheduleException(\sprintf('Two reminder steps fall at %d hours.', $delay));
            }

            $steps[$delay] = new UnpaidOrderReminderStep($delay, UnpaidOrderReminderStep::CANCELLATION === $matches[2] ? null : $matches[2]);
        }

        if (\count($steps) > self::MAXIMUM_STEPS) {
            throw new InvalidReminderScheduleException(\sprintf('A reminder schedule holds at most %d steps.', self::MAXIMUM_STEPS));
        }

        ksort($steps);
        $steps = array_values($steps);

        foreach ($steps as $index => $step) {
            if ($step->isCancellation() && $index !== \count($steps) - 1) {
                throw new InvalidReminderScheduleException('The cancellation is the last step of a reminder schedule: nothing can be sent for an order once it is cancelled.');
            }
        }

        return new self($steps);
    }

    /**
     * @return list<UnpaidOrderReminderStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function isEmpty(): bool
    {
        return [] === $this->steps;
    }

    public function firstDelayInHours(): ?int
    {
        return $this->steps[0]->delayInHours ?? null;
    }

    /**
     * The step an order of that age is in: the last one it reached. The earlier ones are
     * behind it and are not sent late.
     */
    public function stepReachedAfter(int $ageInHours): ?UnpaidOrderReminderStep
    {
        $reached = null;

        foreach ($this->steps as $step) {
            if ($step->delayInHours > $ageInHours) {
                break;
            }

            $reached = $step;
        }

        return $reached;
    }

    public function toSetting(): string
    {
        return implode(',', array_map(static fn (UnpaidOrderReminderStep $step): string => $step->toSetting(), $this->steps));
    }
}

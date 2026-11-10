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

namespace Thelia\Domain\Subscription;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Subscription\SubscriptionStatusChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\CMS\Publication\PublicationMoment;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Domain\Subscription\Exception\SubscriptionTransitionRefusedException;
use Thelia\Model\Map\SubscriptionTableMap;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionHistory;
use Thelia\Model\SubscriptionQuery;

/**
 * The only writer of subscription.status.
 *
 * A change is refused when the map of SubscriptionStatus does not lead there, and when the
 * stored status is no longer the one the subscription was read with: the update only
 * matches the row in that status (compare and set), so of two processes moving the same
 * subscription the second one changes nothing and is told so. Each change leaves a row in
 * subscription_history and is announced once committed.
 */
final readonly class SubscriptionStateMachine
{
    private const int NOTE_MAXIMUM_LENGTH = 255;

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $alsoSet other columns written with the status, by their
     *                                      phpName ("NextRetryAt"), in the same statement
     *
     * @throws SubscriptionTransitionRefusedException
     */
    public function transition(
        Subscription $subscription,
        SubscriptionStatus $to,
        ActorType $actorType,
        ?int $actorId = null,
        ?string $note = null,
        array $alsoSet = [],
    ): Subscription {
        $from = SubscriptionStatus::from((string) $subscription->getStatus());

        if (!$from->canBecome($to)) {
            throw SubscriptionTransitionRefusedException::notAllowed($from, $to);
        }

        $note = null === $note ? null : mb_substr($note, 0, self::NOTE_MAXIMUM_LENGTH);
        $connection = Propel::getConnection(SubscriptionTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $updated = SubscriptionQuery::create()
                ->filterById($subscription->getId())
                ->filterByStatus($from->value)
                ->update(
                    ['Status' => $to->value, 'UpdatedAt' => \DateTime::createFromImmutable(PublicationMoment::atTheSecond($this->clock->now()))] + $alsoSet,
                    $connection,
                );

            if (1 !== $updated) {
                // Nothing was written: committed rather than rolled back, so that a caller's
                // transaction is not left unable to commit by a refusal that changed nothing.
                $connection->commit();

                throw SubscriptionTransitionRefusedException::movedMeanwhile($from);
            }

            (new SubscriptionHistory())
                ->setSubscriptionId($subscription->getId())
                ->setFromStatus($from->value)
                ->setToStatus($to->value)
                ->setActorType($actorType->value)
                ->setActorId($actorId)
                ->setNote($note)
                ->save($connection);

            $connection->commit();
        } catch (SubscriptionTransitionRefusedException $refused) {
            throw $refused;
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        SubscriptionTableMap::removeInstanceFromPool($subscription);
        $subscription->reload(false, $connection);

        $this->dispatcher->dispatch(
            new SubscriptionStatusChangeEvent($subscription, $from, $to, $actorType, $actorId, $note),
            TheliaEvents::SUBSCRIPTION_STATUS_CHANGE,
        );

        return $subscription;
    }

    /**
     * A row of history for an action that does not change the status (a retry asked, a new
     * payment method): the status is written as it stands, on both sides.
     */
    public function recordNote(Subscription $subscription, ActorType $actorType, ?int $actorId, string $note): void
    {
        (new SubscriptionHistory())
            ->setSubscriptionId($subscription->getId())
            ->setFromStatus((string) $subscription->getStatus())
            ->setToStatus((string) $subscription->getStatus())
            ->setActorType($actorType->value)
            ->setActorId($actorId)
            ->setNote(mb_substr($note, 0, self::NOTE_MAXIMUM_LENGTH))
            ->save();
    }

    /**
     * The first row of the history of a subscription just created: no previous status.
     */
    public function recordCreation(Subscription $subscription, ActorType $actorType, ?int $actorId, ?string $note, ?ConnectionInterface $connection = null): void
    {
        (new SubscriptionHistory())
            ->setSubscriptionId($subscription->getId())
            ->setFromStatus(null)
            ->setToStatus((string) $subscription->getStatus())
            ->setActorType($actorType->value)
            ->setActorId($actorId)
            ->setNote(null === $note ? null : mb_substr($note, 0, self::NOTE_MAXIMUM_LENGTH))
            ->save($connection);
    }
}

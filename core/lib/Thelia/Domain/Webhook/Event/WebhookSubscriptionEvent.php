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

namespace Thelia\Domain\Webhook\Event;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookSubscription;

class WebhookSubscriptionEvent extends ActionEvent
{
    private ?WebhookSubscription $subscription = null;
    private string $title = '';
    private string $url = '';

    /** @var list<string> */
    private array $events = [];
    private ?string $payloadMode = null;
    private ?string $status = null;
    private ?string $generatedSecret = null;
    private ?WebhookDelivery $testDelivery = null;

    public function getSubscription(): ?WebhookSubscription
    {
        return $this->subscription;
    }

    public function setSubscription(?WebhookSubscription $subscription): static
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param list<string> $events
     */
    public function setEvents(array $events): static
    {
        $this->events = $events;

        return $this;
    }

    public function getPayloadMode(): ?string
    {
        return $this->payloadMode;
    }

    public function setPayloadMode(string $payloadMode): static
    {
        $this->payloadMode = $payloadMode;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getGeneratedSecret(): ?string
    {
        return $this->generatedSecret;
    }

    public function setGeneratedSecret(string $generatedSecret): static
    {
        $this->generatedSecret = $generatedSecret;

        return $this;
    }

    public function getTestDelivery(): ?WebhookDelivery
    {
        return $this->testDelivery;
    }

    public function setTestDelivery(WebhookDelivery $testDelivery): static
    {
        $this->testDelivery = $testDelivery;

        return $this;
    }
}

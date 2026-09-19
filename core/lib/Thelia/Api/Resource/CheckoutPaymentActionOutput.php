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

namespace Thelia\Api\Resource;

use Thelia\Domain\Checkout\DTO\PaymentAction;

/**
 * What the front has to do once the order is placed.
 *
 * The three fields always travel, null included: a client reading `url` on a redirection
 * must not have to tell a missing key from a key that is not there yet.
 */
final readonly class CheckoutPaymentActionOutput
{
    /**
     * @param string      $type "none" (nothing to do: the payment is settled, or it happens off the
     *                          web), "redirect" (send the buyer to $url) or "form" (render $html)
     * @param string|null $html raw HTML written by the payment module — typically the self-posting
     *                          form a gateway requires — passed on exactly as the module wrote it and
     *                          never escaped or rewritten. It is the module's markup, not the shop's:
     *                          a front renders it only when $type is "form", only on the page that
     *                          hands the buyer over to the gateway, and nowhere else. Injected into
     *                          any other screen it is a script the shop asked a third party to run
     */
    public function __construct(
        public string $type,
        public ?string $url = null,
        public ?string $html = null,
    ) {
    }

    public static function fromPaymentAction(PaymentAction $paymentAction): self
    {
        return new self(
            $paymentAction->type->value,
            $paymentAction->url,
            $paymentAction->html,
        );
    }

    /**
     * @return array{type: string, url: string|null, html: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'url' => $this->url,
            'html' => $this->html,
        ];
    }
}

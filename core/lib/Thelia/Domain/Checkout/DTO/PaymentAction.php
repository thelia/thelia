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

namespace Thelia\Domain\Checkout\DTO;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Checkout\Enum\PaymentActionType;

/**
 * What the front has to do next, read off what the payment module answered.
 *
 * The module speaks HTTP because the checkout it was written for is a browser: it hands
 * back a redirection, or a page holding the form the gateway wants posted, or nothing at
 * all. A client that is not a browser cannot be handed a Response, so the answer is read
 * once, here, and turned into something a JSON payload can carry.
 *
 * The markup of a form is passed on exactly as the module wrote it. Rewriting it — even
 * to tidy it — would break the modules whose form is signed, and there is no shared
 * shape to rewrite it into.
 */
final readonly class PaymentAction
{
    private function __construct(
        public PaymentActionType $type,
        public ?string $url = null,
        public ?string $html = null,
    ) {
    }

    public static function none(): self
    {
        return new self(PaymentActionType::None);
    }

    /**
     * A module with nothing to say answers null — Cheque does, because a cheque is
     * posted. One that settled the payment on the spot answers an empty Response, which
     * is a way of saying "the browser has nothing left to render": FreeOrder does, and
     * reporting that as a blank page to display would be inventing a step.
     */
    public static function fromPaymentResponse(?Response $response): self
    {
        if (!$response instanceof Response) {
            return self::none();
        }

        if ($response instanceof RedirectResponse) {
            return new self(PaymentActionType::Redirect, url: $response->getTargetUrl());
        }

        $content = $response->getContent();

        if (false === $content || '' === trim($content)) {
            return self::none();
        }

        return new self(PaymentActionType::Form, html: $content);
    }
}

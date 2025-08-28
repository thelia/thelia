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

namespace Thelia\Domain\Marketing\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Customer;
use Thelia\Model\NewsletterQuery;

readonly class NewsletterSubscriber
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private RequestStack $requestStack,
    ) {
    }

    public function subscribe(
        Customer $customer,
    ): void {
        $request = $this->requestStack->getMainRequest();
        if (!$request instanceof Request) {
            throw new \RuntimeException('Current request is not an instance of Thelia\Core\HttpFoundation\Request');
        }
        $newsletterEmail = $customer->getEmail();
        $newsletterEvent = (new NewsletterEvent(
            $newsletterEmail,
            $request->getSession()->getLang()?->getLocale()
        ))
            ->setFirstname($customer->getFirstname())
            ->setLastname($customer->getLastname());

        if (null !== $newsletter = NewsletterQuery::create()->findOneByEmail($newsletterEmail)) {
            $newsletterEvent->setId((string) $newsletter->getId());
            $this->eventDispatcher->dispatch($newsletterEvent, TheliaEvents::NEWSLETTER_UPDATE);

            return;
        }

        $this->eventDispatcher->dispatch($newsletterEvent, TheliaEvents::NEWSLETTER_SUBSCRIBE);
    }
}

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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\Customer\CustomerPersonalDataExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Customer\Service\CustomerPersonalDataExporter;

/**
 * Entry point of the two personal data operations a compliance module needs:
 * exporting the data of one person, and erasing it without losing the orders.
 */
class CustomerPersonalData extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        protected CustomerAnonymizer $customerAnonymizer,
        protected CustomerPersonalDataExporter $customerPersonalDataExporter,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function anonymize(CustomerAnonymizeEvent $event): void
    {
        $this->customerAnonymizer->anonymize($event->getCustomer());
    }

    public function export(CustomerPersonalDataExportEvent $event): void
    {
        $event->setPersonalData($this->customerPersonalDataExporter->export($event->getCustomer()));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CUSTOMER_ANONYMIZE => ['anonymize', 128],
            TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT => ['export', 128],
        ];
    }
}

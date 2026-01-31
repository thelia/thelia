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

namespace Thelia\Domain\Catalog\Currency;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Domain\Catalog\Currency\DTO\CurrencyCreateDTO;
use Thelia\Domain\Catalog\Currency\DTO\CurrencyUpdateDTO;
use Thelia\Domain\Catalog\Currency\Exception\CurrencyNotFoundException;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

final readonly class CurrencyFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function create(CurrencyCreateDTO $dto): Currency
    {
        $event = new CurrencyCreateEvent();
        $event
            ->setCurrencyName($dto->name)
            ->setCode($dto->code)
            ->setSymbol($dto->symbol)
            ->setLocale($dto->locale)
            ->setRate($dto->rate)
            ->setFormat($dto->format);

        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_CREATE);

        return $event->getCurrency();
    }

    public function update(int $currencyId, CurrencyUpdateDTO $dto): Currency
    {
        $event = new CurrencyUpdateEvent($currencyId);
        $event
            ->setCurrencyName($dto->name)
            ->setCode($dto->code)
            ->setSymbol($dto->symbol)
            ->setLocale($dto->locale)
            ->setRate($dto->rate)
            ->setFormat($dto->format)
            ->setVisible($dto->visible ? 1 : 0)
            ->setIsDefault($dto->isDefault ? 1 : 0);

        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_UPDATE);

        return $event->getCurrency();
    }

    public function delete(int $currencyId): void
    {
        $event = new CurrencyDeleteEvent($currencyId);

        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_DELETE);
    }

    public function setDefault(int $currencyId): void
    {
        $currency = $this->getById($currencyId);

        if (null === $currency) {
            throw CurrencyNotFoundException::withId($currencyId);
        }

        $event = new CurrencyUpdateEvent($currencyId);
        $event->setIsDefault(1);

        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_SET_DEFAULT);
    }

    public function setVisible(int $currencyId, bool $visible): void
    {
        $currency = $this->getById($currencyId);

        if (null === $currency) {
            throw CurrencyNotFoundException::withId($currencyId);
        }

        $event = new CurrencyUpdateEvent($currencyId);
        $event->setVisible($visible ? 1 : 0);

        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_SET_VISIBLE);
    }

    public function updatePosition(int $currencyId, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $event = new UpdatePositionEvent($currencyId, $mode, $position);

        $this->dispatcher->dispatch($event, TheliaEvents::CURRENCY_UPDATE_POSITION);
    }

    public function getById(int $currencyId): ?Currency
    {
        return CurrencyQuery::create()->findPk($currencyId);
    }

    public function getByCode(string $code): ?Currency
    {
        return CurrencyQuery::create()->findOneByCode($code);
    }

    public function getDefault(): ?Currency
    {
        return CurrencyQuery::create()->findOneByByDefault(true);
    }

    public function getAll(bool $visibleOnly = false): array
    {
        $query = CurrencyQuery::create()
            ->orderByPosition();

        if ($visibleOnly) {
            $query->filterByVisible(true);
        }

        return $query->find()->getData();
    }
}

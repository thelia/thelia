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
use Thelia\Core\Event\Lang\LangToggleActiveEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Install\I18n\SeedI18nInstaller;
use Thelia\Log\Tlog;
use Thelia\Model\Event\LangEvent;

/**
 * Replays the translations shipped in `setup/I18n` for a language that was not
 * part of the installation.
 *
 * `setup/insert.sql` only carries the locales of the languages it creates, and
 * nothing brought the other files to the database afterwards. Seeding on
 * creation covers new languages; seeding on activation covers the ones a shop
 * added before this listener existed.
 */
class LangSeedI18n implements EventSubscriberInterface
{
    public function __construct(private readonly SeedI18nInstaller $installer)
    {
    }

    public function seedCreatedLang(LangEvent $event): void
    {
        $this->seed($event->getModel()->getLocale());
    }

    public function seedActivatedLang(LangToggleActiveEvent $event): void
    {
        $lang = $event->getLang();

        if (null === $lang || !$lang->getActive()) {
            return;
        }

        $this->seed($lang->getLocale());
    }

    private function seed(?string $locale): void
    {
        if (null === $locale || '' === $locale) {
            return;
        }

        try {
            $this->installer->installLocale($locale);
        } catch (\Throwable $throwable) {
            // A shop must not be left without its language because the seed
            // could not be replayed.
            Tlog::getInstance()->addError(
                \sprintf('Failed to seed the %s translations: %s', $locale, $throwable->getMessage()),
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LangEvent::POST_INSERT => ['seedCreatedLang', 64],
            TheliaEvents::LANG_TOGGLEACTIVE => ['seedActivatedLang', 64],
        ];
    }
}

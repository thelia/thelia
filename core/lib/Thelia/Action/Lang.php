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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Lang\LangCreateEvent;
use Thelia\Core\Event\Lang\LangDefaultBehaviorEvent;
use Thelia\Core\Event\Lang\LangDeleteEvent;
use Thelia\Core\Event\Lang\LangToggleActiveEvent;
use Thelia\Core\Event\Lang\LangToggleDefaultEvent;
use Thelia\Core\Event\Lang\LangToggleVisibleEvent;
use Thelia\Core\Event\Lang\LangUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Exception\TemplateException;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Lang\LangUrlEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Event\LangEvent;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\LangQuery;

/**
 * Class Lang.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Lang extends BaseAction implements EventSubscriberInterface
{
    public function __construct(protected TemplateHelperInterface $templateHelper, protected RequestStack $requestStack)
    {
    }

    /**
     * @throws PropelException
     */
    public function update(LangUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $lang = LangQuery::create()->findPk($event->getId())) {
            $lang->setTitle($event->getTitle())
                ->setLocale($event->getLocale())
                ->setCode($event->getCode())
                ->setDateTimeFormat($event->getDateTimeFormat())
                ->setDateFormat($event->getDateFormat())
                ->setTimeFormat($event->getTimeFormat())
                ->setDecimalSeparator($event->getDecimalSeparator())
                ->setThousandsSeparator($event->getThousandsSeparator())
                ->setDecimals($event->getDecimals())
                ->save();

            $event->setLang($lang);
        }
    }

    /**
     * @throws PropelException
     */
    public function toggleDefault(LangToggleDefaultEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $lang = LangQuery::create()->findPk($event->getLangId())) {
            $lang->toggleDefault();

            $event->setLang($lang);
        }
    }

    /**
     * @throws PropelException
     */
    public function toggleActive(LangToggleActiveEvent $event): void
    {
        if (null !== $lang = LangQuery::create()->findPk($event->getLangId())) {
            if ($lang->getByDefault()) {
                throw new \RuntimeException(Translator::getInstance()->trans('Cannot disable the default language'));
            }

            $lang->setActive($lang->getActive() ? 0 : 1);

            if (!$lang->getActive()) {
                $lang->setVisible(0);
            }

            $lang->save();

            $event->setLang($lang);
        }
    }

    /**
     * @throws PropelException
     */
    public function toggleVisible(LangToggleVisibleEvent $event): void
    {
        if (null !== $lang = LangQuery::create()->findPk($event->getLangId())) {
            if ($lang->getByDefault()) {
                throw new \RuntimeException(Translator::getInstance()->trans('Cannot hide the default language'));
            }

            $lang->setVisible($lang->getVisible() ? 0 : 1);

            if (!$lang->getActive() && $lang->getVisible()) {
                $lang->setActive(1);
            }

            $lang->save();

            $event->setLang($lang);
        }
    }

    /**
     * @throws PropelException
     */
    public function create(LangCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $lang = new LangModel();

        $lang
            ->setTitle($event->getTitle())
            ->setCode($event->getCode())
            ->setLocale($event->getLocale())
            ->setDateTimeFormat($event->getDateTimeFormat())
            ->setDateFormat($event->getDateFormat())
            ->setTimeFormat($event->getTimeFormat())
            ->setDecimalSeparator($event->getDecimalSeparator())
            ->setThousandsSeparator($event->getThousandsSeparator())
            ->setDecimals($event->getDecimals())
            ->save();

        $event->setLang($lang);
    }

    /**
     * @throws PropelException
     */
    public function delete(LangDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $lang = LangQuery::create()->findPk($event->getLangId())) {
            if ($lang->getByDefault()) {
                throw new \RuntimeException(Translator::getInstance()->trans('It is not allowed to delete the default language'));
            }

            $lang
                ->delete();

            /** @var Session $session */
            $session = $this->requestStack->getMainRequest()->getSession();

            // If we've just deleted the current admin edition language, set it to the default one.
            if ($lang->getId() === $session->getAdminEditionLang()->getId()) {
                $session->setAdminEditionLang(LangModel::getDefaultLanguage());
            }

            // If we've just deleted the current admin language, set it to the default one.
            if ($lang->getId() === $session->getLang()->getId()) {
                $session->setLang(LangModel::getDefaultLanguage());
            }

            $event->setLang($lang);
        }
    }

    /**
     * @throws \Exception
     * @throws PropelException
     */
    public function defaultBehavior(LangDefaultBehaviorEvent $event): void
    {
        ConfigQuery::create()
            ->filterByName('default_lang_without_translation')
            ->update(['Value' => $event->getDefaultBehavior()]);
    }

    /**
     * @throws \Exception
     * @throws PropelException
     */
    public function langUrl(LangUrlEvent $event): void
    {
        foreach ($event->getUrl() as $id => $url) {
            LangQuery::create()
                ->filterById($id)
                ->update(['Url' => $url]);
        }
    }

    public function fixMissingFlag(LangEvent $event): void
    {
        // Be sure that a lang have a flag, otherwise copy the
        // "unknown" flag
        $adminTemplate = $this->templateHelper->getActiveAdminTemplate();
        $unknownFlag = ConfigQuery::getUnknownFlagPath();

        try {
            $unknownFlagPath = $adminTemplate->getTemplateFilePath($unknownFlag);

            // Check if the country flag exists
            $countryFlag = rtrim(\dirname($unknownFlagPath), DS).DS.$event->getModel()->getCode().'.png';

            if (!file_exists($countryFlag)) {
                $fs = new Filesystem();

                $fs->copy($unknownFlagPath, $countryFlag);
            }
        } catch (TemplateException $templateException) {
            throw new \RuntimeException(Translator::getInstance()->trans('The image which replaces an undefined country flag (%file) was not found. Please check unknown-flag-path configuration variable, and check that the image exists.', ['%file' => $unknownFlag]), 0, $templateException);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::LANG_UPDATE => ['update', 128],
            TheliaEvents::LANG_TOGGLEDEFAULT => ['toggleDefault', 128],
            TheliaEvents::LANG_TOGGLEACTIVE => ['toggleActive', 128],
            TheliaEvents::LANG_TOGGLEVISIBLE => ['toggleVisible', 128],
            TheliaEvents::LANG_CREATE => ['create', 128],
            TheliaEvents::LANG_DELETE => ['delete', 128],
            TheliaEvents::LANG_DEFAULTBEHAVIOR => ['defaultBehavior', 128],
            TheliaEvents::LANG_URL => ['langUrl', 128],
            LangEvent::POST_INSERT => ['fixMissingFlag', 128],
            LangEvent::POST_UPDATE => ['fixMissingFlag', 128],
        ];
    }
}

<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Config\ConfigUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;
use Thelia\Service\Composer\ComposerHelper;

class TheliaTemplateHelper implements TemplateHelperInterface, EventSubscriberInterface
{
    public function __construct(
        protected string $kernelCacheDir,
        protected ComposerHelper $composerHelper
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getActiveMailTemplate(): TemplateDefinition
    {
        return new TemplateDefinition(
            ConfigQuery::read('active-mail-template', 'default'),
            TemplateDefinition::EMAIL
        );
    }

    /**
     * Check if a template definition is the current active template.
     *
     * @return bool true is the given template is the active template
     */
    public function isActive(TemplateDefinition $templateDefinition): bool
    {
        $configTemplateName = '';

        switch ($templateDefinition->getType()) {
            case TemplateDefinition::FRONT_OFFICE:
                $configTemplateName = 'active-front-template';
                break;
            case TemplateDefinition::BACK_OFFICE:
                $configTemplateName = 'active-admin-template';
                break;
            case TemplateDefinition::PDF:
                $configTemplateName = 'active-pdf-template';
                break;
            case TemplateDefinition::EMAIL:
                $configTemplateName = 'active-mail-template';
                break;
        }

        return $templateDefinition->getName() === ConfigQuery::read($configTemplateName, 'default');
    }

    /**
     * @throws \Exception
     */
    public function getActivePdfTemplate(): TemplateDefinition
    {
        return new TemplateDefinition(
            ConfigQuery::read('active-pdf-template', 'default'),
            TemplateDefinition::PDF
        );
    }

    /**
     * @throws \Exception
     */
    public function getActiveAdminTemplate(): TemplateDefinition
    {
        return new TemplateDefinition(
            ConfigQuery::read('active-admin-template', 'default'),
            TemplateDefinition::BACK_OFFICE
        );
    }

    /**
     * @throws \Exception
     */
    public function getActiveFrontTemplate(): TemplateDefinition
    {
        return new TemplateDefinition(
            ConfigQuery::read('active-front-template', 'default'),
            TemplateDefinition::FRONT_OFFICE
        );
    }

    /**
     * @throws \Exception
     */
    public function getStandardTemplateDefinitions(): array
    {
        return [
            $this->getActiveFrontTemplate(),
            $this->getActiveAdminTemplate(),
            $this->getActivePdfTemplate(),
            $this->getActiveMailTemplate(),
        ];
    }

    /**
     * Return a list of existing templates for a given template type.
     */
    public function getList(int $templateType, string $base = THELIA_TEMPLATE_DIR): array
    {
        $list = $exclude = [];

        $tplIterator = TemplateDefinition::getStandardTemplatesSubdirsIterator();

        foreach ($tplIterator as $type => $subdir) {
            if ($templateType !== $type) {
                continue;
            }
            $baseDir = rtrim($base, DS).DS.$subdir;

            try {
                // Every subdir of the basedir is supposed to be a template.
                $di = new \DirectoryIterator($baseDir);

                /** @var \DirectoryIterator $file */
                foreach ($di as $file) {
                    // Ignore 'dot' elements
                    if ($file->isDot() || !$file->isDir()) {
                        continue;
                    }

                    // Ignore reserved directory names
                    if (\in_array($file->getFilename(), $exclude, true)) {
                        continue;
                    }

                    $list[] = new TemplateDefinition($file->getFilename(), $templateType);
                }
            } catch (\Exception) {
                continue;
            }
        }

        return $list;
    }

    /**
     * Clear the cache if the front or admin template is changed in the back-office.
     */
    public function clearCache(ConfigUpdateEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (
            (null === $config = ConfigQuery::create()->findPk($event->getConfigId()))
            || ($config->getValue() === $event->getValue())
            || ($config->getName() !== TemplateDefinition::BACK_OFFICE_CONFIG_NAME
             && $config->getName() !== TemplateDefinition::FRONT_OFFICE_CONFIG_NAME)
        ) {
            return;
        }

        $cacheEvent = new CacheEvent($this->kernelCacheDir);
        $dispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
    }

    public function enableThemeAsBundle(string $path): void
    {
        $bundleName = $this->composerHelper->findFirstClassBundle($path);
        if (null === $bundleName) {
            return;
        }
        $this->composerHelper->addNamespaceToBundlesSymfony($bundleName, ['all' => true]);
        $this->composerHelper->addPsr4NamespaceToComposer($bundleName, $path);
    }

    public function setConfigToTemplate(string $configType, string $name): void
    {
        ConfigQuery::write($configType, $name);
        $envName = mb_strtoupper(str_replace('-', '_', $configType));
        file_put_contents(
            THELIA_ROOT.'.env.local',
            sprintf(
                "\n\n###> thelia/templates ###\n%s=%s\n###< thelia/templates ###\n",
                $envName,
                $name
            ),
            \FILE_APPEND
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CONFIG_SETVALUE => ['clearCache', 130],
        ];
    }
}

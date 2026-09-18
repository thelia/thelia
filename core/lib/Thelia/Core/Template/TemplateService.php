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

namespace Thelia\Core\Template;

use Thelia\Model\ConfigQuery;

class TemplateService
{
    public static function getTemplatesAbsolutePath(): array
    {
        $templatesPath = [];
        $configTemplatesNames = TemplateDefinition::CONFIG_NAMES;

        foreach ($configTemplatesNames as $templateType => $configName) {
            $templatePath = THELIA_TEMPLATE_DIR.$templateType.DS.ConfigQuery::read($configName, 'default');

            if (is_dir($templatePath)) {
                $templatesPath[$templateType] = $templatePath;
            }
        }

        return $templatesPath;
    }

    /**
     * The same directories as getTemplatesAbsolutePath(), each followed by the directories of
     * the templates it inherits from, nearest parent first.
     *
     * A separate method on purpose: getTemplatesAbsolutePath() answers one directory per
     * template type, and a caller that expects a single directory must not silently start
     * receiving a list.
     *
     * @return array<string, list<string>> template type => directories, active template first
     */
    public static function getTemplatesAbsolutePathWithParents(): array
    {
        $templatesPath = [];

        foreach (TemplateDefinition::CONFIG_NAMES as $templateType => $configName) {
            $templateChain = self::getTemplateChainAbsolutePath(
                $templateType,
                (string) ConfigQuery::read($configName, 'default'),
            );

            if ([] !== $templateChain) {
                $templatesPath[$templateType] = $templateChain;
            }
        }

        return $templatesPath;
    }

    public static function getTemplateAbsolutePathByType(string $type): string
    {
        if (!isset(TemplateDefinition::CONFIG_NAMES[$type])) {
            throw new \InvalidArgumentException('Invalid template type: '.$type);
        }

        $configName = TemplateDefinition::CONFIG_NAMES[$type];

        return THELIA_TEMPLATE_DIR.$type.DS.ConfigQuery::read($configName, 'default');
    }

    /**
     * The directory of a template, followed by the directories of the templates it inherits
     * from, nearest parent first. A template that declares no parent, or whose directory does
     * not exist, yields at most its own directory.
     *
     * The chain is read straight from the template.xml descriptors. Walking it through
     * TemplateDefinition would be richer, but building one validates the descriptor through
     * Tlog and therefore needs a Propel connection: this method also runs while the container
     * is being compiled, where no connection is booted.
     *
     * @return list<string>
     */
    public static function getTemplateChainAbsolutePath(string $type, string $templateName): array
    {
        $templateChain = [];
        $visitedTemplates = [];

        while ('' !== $templateName && !isset($visitedTemplates[$templateName])) {
            $visitedTemplates[$templateName] = true;

            $templatePath = THELIA_TEMPLATE_DIR.$type.DS.$templateName;

            if (!is_dir($templatePath)) {
                break;
            }

            $templateChain[] = $templatePath;
            $templateName = self::readParentTemplateName($templatePath);
        }

        return $templateChain;
    }

    private static function readParentTemplateName(string $templatePath): string
    {
        $descriptorPath = $templatePath.DS.'template.xml';

        if (!is_file($descriptorPath)) {
            return '';
        }

        $previousInternalErrors = libxml_use_internal_errors(true);
        $descriptor = simplexml_load_file($descriptorPath);
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);

        if (!$descriptor instanceof \SimpleXMLElement) {
            return '';
        }

        return trim((string) $descriptor->parent);
    }
}

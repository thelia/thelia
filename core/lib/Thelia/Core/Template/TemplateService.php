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

    public static function getTemplateAbsolutePathByType(string $type): string
    {
        if (!isset(TemplateDefinition::CONFIG_NAMES[$type])) {
            throw new \InvalidArgumentException('Invalid template type: '.$type);
        }

        $configName = TemplateDefinition::CONFIG_NAMES[$type];

        return THELIA_TEMPLATE_DIR.$type.DS.ConfigQuery::read($configName, 'default');
    }
}

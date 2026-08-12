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

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Thelia\Core\Template\Exception\TemplateException;

/**
 * Reads the list of internal views a template declares in `config/views.yaml`:
 *
 *     internal:
 *         - checkout-delivery
 *         - checkout-payment
 *
 * Internal views are root templates a controller renders with the context it prepares.
 * They are not pages, so they must not answer on a URL made of their own name.
 *
 * The file is optional: a template that ships none declares nothing, and every one of its
 * root templates stays reachable by name, as it always was. A file that is there but
 * unreadable is a template packaging error and is reported as such.
 */
final class InternalViewsDeclaration
{
    public const FILE_NAME = 'config/views.yaml';

    /**
     * @param string $templateDirectory absolute path of the template directory
     *
     * @return list<string>|null the declared view names, or null when the template declares nothing
     *
     * @throws TemplateException when the declaration is present but cannot be read
     */
    public static function readFrom(string $templateDirectory): ?array
    {
        $file = rtrim($templateDirectory, '/\\').\DIRECTORY_SEPARATOR.self::FILE_NAME;

        if (!is_file($file)) {
            return null;
        }

        try {
            $declaration = Yaml::parseFile($file);
        } catch (ParseException $e) {
            throw new TemplateException(\sprintf('%s is not a valid YAML file: %s', $file, $e->getMessage()));
        }

        if (!\is_array($declaration) || !\array_key_exists('internal', $declaration)) {
            return null;
        }

        if (!\is_array($declaration['internal'])) {
            throw self::invalidList($file);
        }

        $views = [];
        foreach ($declaration['internal'] as $view) {
            if (!\is_string($view) || '' === trim($view)) {
                throw self::invalidList($file);
            }

            $views[] = trim($view);
        }

        return $views;
    }

    private static function invalidList(string $file): TemplateException
    {
        return new TemplateException(\sprintf('The "internal" key of %s must contain a list of view names.', $file));
    }
}

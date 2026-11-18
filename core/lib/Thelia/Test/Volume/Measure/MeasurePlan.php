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

namespace Thelia\Test\Volume\Measure;

use Symfony\Component\Yaml\Yaml;

/**
 * The pages and the long jobs a measure campaign covers, read from a YAML file, with their
 * placeholders ({product_url}, {customer_email}...) replaced by values read from the dataset.
 */
final readonly class MeasurePlan
{
    /**
     * @param array<string, array{form: string, action: string, fields: array<string, string>}> $signIns
     * @param list<array{name: string, area: string, path: string, session: string}>            $pages
     * @param list<array{name: string, command: list<string>}>                                  $jobs
     */
    public function __construct(
        public array $signIns,
        public array $pages,
        public array $jobs,
    ) {
    }

    /**
     * @param array<string, string|int> $placeholders
     */
    public static function fromFile(string $file, array $placeholders): self
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException(\sprintf('No measure plan at %s.', $file));
        }

        /** @var array{sign_in?: array<string, array{form: string, action: string, fields?: array<string, string>}>, pages?: list<array{name: string, area?: string, path: string, session?: string}>, jobs?: list<array{name: string, command: list<string>}>} $data */
        $data = Yaml::parseFile($file);

        return self::fromArray($data, $placeholders);
    }

    /**
     * @param array{sign_in?: array<string, array{form: string, action: string, fields?: array<string, string>}>, pages?: list<array{name: string, area?: string, path: string, session?: string}>, jobs?: list<array{name: string, command: list<string>}>} $data
     * @param array<string, string|int>                                                                                                                                                                                                                      $placeholders
     */
    public static function fromArray(array $data, array $placeholders): self
    {
        $replace = static function (string $value) use ($placeholders): string {
            $resolved = preg_replace_callback('/\{([a-z_]+)\}/', static function (array $match) use ($placeholders): string {
                if (!\array_key_exists($match[1], $placeholders)) {
                    throw new \InvalidArgumentException(\sprintf('The measure plan uses {%s}, which the dataset does not give.', $match[1]));
                }

                return (string) $placeholders[$match[1]];
            }, $value);

            return (string) $resolved;
        };

        $signIns = [];
        foreach ($data['sign_in'] ?? [] as $session => $signIn) {
            $signIns[$session] = [
                'form' => $replace($signIn['form']),
                'action' => $replace($signIn['action']),
                'fields' => array_map($replace, $signIn['fields'] ?? []),
            ];
        }

        $pages = [];
        foreach ($data['pages'] ?? [] as $page) {
            $session = $page['session'] ?? 'anonymous';
            if ('anonymous' !== $session && !isset($signIns[$session])) {
                throw new \InvalidArgumentException(\sprintf('The page %s needs the session %s, which the plan does not sign in.', $page['name'], $session));
            }
            $pages[] = ['name' => $page['name'], 'area' => $page['area'] ?? 'front', 'path' => $replace($page['path']), 'session' => $session];
        }

        $jobs = [];
        foreach ($data['jobs'] ?? [] as $job) {
            $jobs[] = ['name' => $job['name'], 'command' => array_map($replace, $job['command'])];
        }

        return new self($signIns, $pages, $jobs);
    }
}

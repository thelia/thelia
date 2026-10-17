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

// Compares two runtime surface snapshots written by snapshot.sh: the Twig
// callables and globals, the routes and the event listeners. What the base
// exposes and the pull request no longer does is a removal; the same name with
// another signature, path or method is a change. Both may break a theme, a
// module or an integration written against the base. Additions are counted.
//
// Usage: php surface-diff.php <base-directory> <pr-directory> <output.json>

if (4 !== $argc) {
    fwrite(\STDERR, "Usage: php surface-diff.php <base-directory> <pr-directory> <output.json>\n");
    exit(2);
}

[, $baseDirectory, $prDirectory, $output] = $argv;

$errors = [];

function load(string $directory, string $name, array &$errors): ?array
{
    $file = "$directory/$name.json";
    if (!is_file($file)) {
        $errors[] = "$name: no snapshot in ".basename($directory);

        return null;
    }

    return json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
}

/**
 * @param array<string, mixed> $base
 * @param array<string, mixed> $pr
 */
function compare(array $base, array $pr): array
{
    $removed = array_values(array_diff(array_keys($base), array_keys($pr)));
    $added = array_values(array_diff(array_keys($pr), array_keys($base)));
    $changed = [];
    foreach (array_intersect_key($base, $pr) as $name => $value) {
        if ($value !== $pr[$name]) {
            $changed[] = ['name' => (string) $name, 'base' => $value, 'pr' => $pr[$name]];
        }
    }
    sort($removed);
    sort($added);

    return ['removed' => $removed, 'changed' => $changed, 'added' => $added];
}

$result = [];

$baseTwig = load($baseDirectory, 'twig', $errors);
$prTwig = load($prDirectory, 'twig', $errors);
if (null !== $baseTwig && null !== $prTwig) {
    foreach (['functions', 'filters', 'tests'] as $kind) {
        $result['twig'][$kind] = compare($baseTwig[$kind] ?? [], $prTwig[$kind] ?? []);
    }
    // Only the names of the globals matter: their values depend on the data.
    $result['twig']['globals'] = compare(
        array_fill_keys(array_keys($baseTwig['globals'] ?? []), true),
        array_fill_keys(array_keys($prTwig['globals'] ?? []), true),
    );
}

$routeShape = static fn (array $route): array => [
    'path' => $route['path'] ?? null,
    'method' => $route['method'] ?? null,
    'host' => $route['host'] ?? null,
    'scheme' => $route['scheme'] ?? null,
];
$baseRoutes = load($baseDirectory, 'routes', $errors);
$prRoutes = load($prDirectory, 'routes', $errors);
if (null !== $baseRoutes && null !== $prRoutes) {
    $result['routes'] = compare(array_map($routeShape, $baseRoutes), array_map($routeShape, $prRoutes));
}

// A listener is named by its class and method; its priority is part of the
// change, since it decides the order listeners see the event in. A lazy
// service is a proxy class generated in the container namespace, whose name
// changes with the container: it is named after the class it stands for.
$listeners = static function (array $events): array {
    $named = [];
    foreach ($events as $event => $eventListeners) {
        foreach ($eventListeners as $listener) {
            $class = preg_replace(['/^Container\w+\\\\/', '/Ghost[0-9a-zA-Z]+$/', '/Proxy[0-9a-zA-Z]+$/'], '', $listener['class'] ?? $listener['type'] ?? '?');
            $name = $class.'::'.($listener['name'] ?? '?');
            $named["$event => $name"] = $listener['priority'] ?? null;
        }
    }

    return $named;
};
$baseEvents = load($baseDirectory, 'events', $errors);
$prEvents = load($prDirectory, 'events', $errors);
if (null !== $baseEvents && null !== $prEvents) {
    $result['events'] = [
        'events' => compare(array_fill_keys(array_keys($baseEvents), true), array_fill_keys(array_keys($prEvents), true)),
        'listeners' => compare($listeners($baseEvents), $listeners($prEvents)),
    ];
}

$result['errors'] = $errors;

file_put_contents($output, json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n");

$annotate = static function (string $title, array $names): void {
    if ([] !== $names) {
        echo "::warning title=$title::".implode(', ', array_slice($names, 0, 20)).(count($names) > 20 ? ', ...' : '')."\n";
    }
};
foreach ($result['twig'] ?? [] as $kind => $diff) {
    $annotate("Twig $kind removed", $diff['removed']);
    $annotate("Twig $kind changed", array_column($diff['changed'], 'name'));
}
$annotate('Routes removed', $result['routes']['removed'] ?? []);
$annotate('Routes changed', array_column($result['routes']['changed'] ?? [], 'name'));
$annotate('Event listeners removed', $result['events']['listeners']['removed'] ?? []);
foreach ($errors as $error) {
    echo "::warning title=Surface comparison incomplete::$error\n";
}

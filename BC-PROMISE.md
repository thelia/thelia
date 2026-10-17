# Backward compatibility promise

Thelia follows semantic versioning. A minor or patch release (3.x.y) does not
break code, templates or integrations written against an earlier 3.x release.
Breaking changes only ship in a major release, and only after a deprecation
period in the preceding minor releases.

## What the promise covers

- **PHP classes** under `core/lib/Thelia`, unless the class or member is marked
  `@internal`. Public and protected methods keep their signature: no required
  parameter is added, no parameter type is narrowed, no return type is widened.
  A constructor of a service registered in the container is not covered: get
  the service from the container instead of building it.
- **Events**: the event names (the `TheliaEvents` constants and the event
  classes) and the getters of their payloads.
- **Hooks**: the hook names declared by the core and called by the themes.
- **Twig**: the functions, filters, tests and globals the core and the official
  themes register.
- **API resources**: the operations (path, method), their input and output
  fields, and the meaning of each field.
- **Routes**: the route names and the parameters they take.
- **Configuration**: the configuration variables (the `config` table) and the
  bundle configuration keys.
- **Database**: the tables and columns of `local/config/schema.xml`. A schema
  change always comes with its migration in `setup/update/sql/`.

## What it does not cover

- Anything marked `@internal`, and the code under `Test` namespaces.
- The classes Propel generates (`Model\Base`, `Model\Map`): use the model and
  query classes they are extended by.
- Template markup and CSS class names of the themes, unless documented as an
  extension point.
- Error messages, log messages and the order of listeners at equal priority.
- Behaviour that contradicts the documentation: fixing a bug is not a break.

## Deprecating something

Nothing covered by this promise is removed or changed without being deprecated
first, in a minor release, with a replacement available in that same release.

- PHP: keep the old code path working and call
  `trigger_deprecation('thelia/core', '3.x', 'Use %s instead.', ...)` when it is
  used. Mark the symbol `@deprecated since 3.x, use ... instead`.
- Twig: pass the `deprecation_info` option (`Twig\DeprecatedCallableInfo`,
  available since Twig 3.15) to the `TwigFunction`, `TwigFilter` or `TwigTest`,
  naming the replacement.
- API: keep the field or operation, flag it `deprecationReason` in the
  resource metadata, and document the replacement.
- Events, hooks, routes, configuration: keep the old name working as an alias
  of the new one for the whole deprecation period.

The test suite runs without deprecations: a deprecated path used by the core
itself is a bug.

## Where to write it

- Every deprecation and every behaviour change gets a line in `CHANGELOG.md`
  under the upcoming version.
- Every deprecation also gets an entry in `UPGRADE-3.x.md` at the repository
  root (the file of the minor release that introduces it), saying what to
  replace and with what. The file of the next major release lists the removals.
- A pull request that touches the covered surface says so in its description.

## Checking it

Each pull request gets an automated report of the public surface it changes:
classes, Twig callables, routes, event listeners, API operations and fields,
and database schema. It flags, it does not block: a reviewer decides whether a
change is a break, and whether it needs a deprecation first.

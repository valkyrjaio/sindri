<p align="center"><a href="https://valkyrja.io" target="_blank">
    <img src="https://raw.githubusercontent.com/valkyrjaio/art/refs/heads/master/long-banner/orange/php.png" width="100%">
</a></p>

# Sindri

[Sindri][github sindri] is the build tool and application creator for the
[Valkyrja][Valkyrja url] PHP framework.

Sindri scaffolds new Valkyrja applications, generates cache files for faster
runtime performance, and handles build-time concerns across the Valkyrja
ecosystem. Named after the dwarven smith in Norse mythology who forged
Mjölnir and other divine artifacts, Sindri does for your Valkyrja app what
his namesake did for the gods: crafts the tools and artifacts that make it
all work faster and better.

<p>
    <a href="https://packagist.org/packages/valkyrja/sindri"><img src="https://poser.pugx.org/valkyrja/sindri/require/php" alt="PHP Version Require"></a>
    <a href="https://packagist.org/packages/valkyrja/sindri"><img src="https://poser.pugx.org/valkyrja/sindri/v" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/valkyrja/sindri"><img src="https://poser.pugx.org/valkyrja/sindri/license" alt="License"></a>
    <a href="https://github.com/valkyrjaio/sindri-php/actions/workflows/ci.yml?query=branch%3A26.x"><img src="https://github.com/valkyrjaio/sindri-php/actions/workflows/ci.yml/badge.svg?branch=26.x" alt="CI Status"></a>
    <a href="https://scrutinizer-ci.com/g/valkyrjaio/sindri-php/?branch=26.x"><img src="https://scrutinizer-ci.com/g/valkyrjaio/sindri-php/badges/quality-score.png?b=26.x" alt="Scrutinizer"></a>
    <a href="https://coveralls.io/github/valkyrjaio/sindri-php?branch=26.x"><img src="https://coveralls.io/repos/github/valkyrjaio/sindri-php/badge.svg?branch=26.x" alt="Coverage Status" /></a>
    <a href="https://shepherd.dev/github/valkyrjaio/sindri-php"><img src="https://shepherd.dev/github/valkyrjaio/sindri-php/coverage.svg" alt="Psalm Shepherd" /></a>
    <a href="https://sonarcloud.io/summary/new_code?id=valkyrjaio_sindri"><img src="https://sonarcloud.io/api/project_badges/measure?project=valkyrjaio_sindri&metric=sqale_rating" alt="Maintainability Rating" /></a>
</p>

What Sindri Does
----------------

- **Scaffolds new Valkyrja applications** — bootstrap a fresh project with
  the correct structure, entry points, and configuration
- **Generates cache files** — produces compiled configuration, route, and
  container data that lets your app skip discovery work at runtime
- **Builds artifacts** — prepares deployable outputs optimized for production
  runtimes
- **Handles upgrades** — assists with migrations between major Valkyrja
  versions

Installation
------------

### Via Composer _(recommended)_

Add Sindri to an existing Valkyrja project:

```
composer require --dev valkyrja/sindri
```

Or use Sindri to scaffold a new project from scratch:

```
composer create-project valkyrja/sindri your-project
cd your-project
```

### Phar

_Phar distribution is planned for a future release._

Getting Started
---------------

### Scaffolding a New Application

Sindri can generate a new Valkyrja application with the correct structure
and entry points:

```
vendor/bin/sindri new your-app-name
```

This creates a fresh project directory with pre-wired HTTP and CLI kernels,
example controllers and commands, and a complete configuration scaffold —
equivalent to using the [`valkyrja-starter-app-php`][starter url] template
but driven from the CLI.

### Building Cache Files

Once your application is developed, Sindri can generate cache files that
significantly improve runtime performance by eliminating discovery and
configuration overhead:

```
vendor/bin/sindri cache
```

See the [Sindri documentation][docs url] for the full list of cache
generation commands and options.

### Listing Available Commands

```
vendor/bin/sindri list
```

Documentation
-------------

Full Sindri [documentation][docs url] is baked into the repository so you
can browse it offline.

For framework-level questions about Valkyrja itself, see the
[Valkyrja framework repository][framework url].

Versioning and Release Process
------------------------------

Sindri follows [semantic versioning][semantic versioning url] with a major
release every year, and support for each major version for 2 years from the
date of release.

For more information see our
[Versioning and Release Process documentation][Versioning and Release Process url].

### Supported Versions

Bug fixes are provided until 3 months after the next major release. Security
fixes are provided for 2 years after the initial release.

| Version | PHP       | Release        | Bug Fixes Until | Security Fixes Until |
|:--------|:----------|:---------------|:----------------|:---------------------|
| 26      | 8.4 – 8.6 | March 31, 2026 | Q2 2027         | Q1 2028              |
| 27      | 8.5 – 8.6 | Q1 2027        | Q2 2028         | Q1 2029              |
| 28      | 8.6+      | Q1 2028        | Q2 2029         | Q1 2030              |

Contributing
------------

Sindri is an open-source, community-driven project. Thank you for your
interest in helping develop, maintain, and release it.

See [`CONTRIBUTING.md`][contributing url] for the submission process and
[`VOCABULARY.md`][vocabulary url] for the terminology used across Valkyrja.

Security Issues
---------------

If you discover a security vulnerability within Sindri, please follow our
[disclosure procedure][security vulnerabilities url].

License
-------

Sindri is open-source software licensed under the
[MIT license][MIT license url]. See [`LICENSE.md`](./LICENSE.md).

[Valkyrja url]: https://valkyrja.io

[framework url]: https://github.com/valkyrjaio/valkyrja-php

[github sindri]: https://github.com/valkyrjaio/sindri-php

[starter url]: https://github.com/valkyrjaio/valkyrja-starter-app-php

[docs url]: ./src/Valkyrja/README.md

[Versioning and Release Process url]: ./src/Valkyrja/VERSIONING_AND_RELEASE_PROCESS.md

[contributing url]: https://github.com/valkyrjaio/.github/blob/master/CONTRIBUTING.md

[vocabulary url]: https://github.com/valkyrjaio/.github/blob/master/VOCABULARY.md

[security vulnerabilities url]: https://github.com/valkyrjaio/.github/blob/master/SECURITY.md

[semantic versioning url]: https://semver.org/

[MIT license url]: https://opensource.org/licenses/MIT

[license url]: ./LICENSE.md

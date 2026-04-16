<p align="center"><a href="https://valkyrja.io" target="_blank">
    <img src="https://raw.githubusercontent.com/valkyrjaio/art/refs/heads/master/full-logo/orange/php.png" width="400">
</a></p>

# Sindri

[Sindri][github sindri] is the build tool for the [Valkyrja][Valkyrja url] PHP
framework for web and console applications.

About Valkyrja
--------------

> This repository contains the core code of the Valkyrja Sindri build tool.

Sindri is the dwarf in Norse Mythology who is responsible for crafting magical
items and tools for the gods. In a similar sense, the Sindri build tool crafts
your application and builds out data cache files for your application to run
even faster.

<p>
    <a href="https://packagist.org/packages/valkyrja/sindri"><img src="https://poser.pugx.org/valkyrja/sindri/require/php" alt="PHP Version Require"></a>
    <a href="https://packagist.org/packages/valkyrja/sindri"><img src="https://poser.pugx.org/valkyrja/sindri/v" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/valkyrja/sindri"><img src="https://poser.pugx.org/valkyrja/sindri/license" alt="License"></a>
    <!-- <a href="https://packagist.org/packages/valkyrja/sindri"><img src="https://poser.pugx.org/valkyrja/sindri/downloads" alt="Total Downloads"></a>-->
    <a href="https://scrutinizer-ci.com/g/valkyrjaio/sindri/?branch=master"><img src="https://scrutinizer-ci.com/g/valkyrjaio/sindri/badges/quality-score.png?b=master" alt="Scrutinizer"></a>
    <a href="https://coveralls.io/github/valkyrjaio/sindri?branch=master"><img src="https://coveralls.io/repos/github/valkyrjaio/sindri/badge.svg?branch=master" alt="Coverage Status" /></a>
    <a href="https://shepherd.dev/github/valkyrjaio/sindri"><img src="https://shepherd.dev/github/valkyrjaio/sindri/coverage.svg" alt="Psalm Shepherd" /></a>
    <a href="https://sonarcloud.io/summary/new_code?id=valkyrjaio_sindri"><img src="https://sonarcloud.io/api/project_badges/measure?project=valkyrjaio_sindri&metric=sqale_rating" alt="Maintainability Rating" /></a>
</p>

Build Status
------------

<table>
    <tbody>
        <tr>
            <td>Linting</td>
            <td>
                <a href="https://github.com/valkyrjaio/sindri/actions/workflows/phpcodesniffer.yml?query=branch%3Amaster"><img src="https://github.com/valkyrjaio/sindri/actions/workflows/phpcodesniffer.yml/badge.svg?branch=master" alt="PHP Code Sniffer Build Status"></a>
            </td>
            <td>
                <a href="https://github.com/valkyrjaio/sindri/actions/workflows/phpcsfixer.yml?query=branch%3Amaster"><img src="https://github.com/valkyrjaio/sindri/actions/workflows/phpcsfixer.yml/badge.svg?branch=master" alt="PHP CS Fixer Build Status"></a>
            </td>
        </tr>
        <tr>
            <td>Coding Rules</td>
            <td>
                <a href="https://github.com/valkyrjaio/sindri/actions/workflows/phparkitect.yml?query=branch%3Amaster"><img src="https://github.com/valkyrjaio/sindri/actions/workflows/phparkitect.yml/badge.svg?branch=master" alt="PHPArkitect Build Status"></a>
            </td>
            <td>
                <a href="https://github.com/valkyrjaio/sindri/actions/workflows/rector.yml?query=branch%3Amaster"><img src="https://github.com/valkyrjaio/sindri/actions/workflows/rector.yml/badge.svg?branch=master" alt="Rector Build Status"></a>
            </td>
        </tr>
        <tr>
            <td>Static Analysis</td>
            <td>
                <a href="https://github.com/valkyrjaio/sindri/actions/workflows/phpstan.yml?query=branch%3Amaster"><img src="https://github.com/valkyrjaio/sindri/actions/workflows/phpstan.yml/badge.svg?branch=master" alt="PHPStan Build Status"></a>
            </td>
            <td>
                <a href="https://github.com/valkyrjaio/sindri/actions/workflows/psalm.yml?query=branch%3Amaster"><img src="https://github.com/valkyrjaio/sindri/actions/workflows/psalm.yml/badge.svg?branch=master" alt="Psalm Build Status"></a>
            </td>
        </tr>
        <tr>
            <td>Testing</td>
            <td>
                <a href="https://github.com/valkyrjaio/sindri/actions/workflows/phpunit.yml?query=branch%3Amaster"><img src="https://github.com/valkyrjaio/sindri/actions/workflows/phpunit.yml/badge.svg?branch=master" alt="PHPUnit Build Status"></a>
            </td>
            <td></td>
        </tr>
    </tbody>
</table>

Documentation
-------------

The Sindri [documentation][docs url] is baked into the repo so you can
access it even when working offline.

Installation
------------

There are two ways to install the Sindri build tool.

### Composer

You can either choose to install via composer as a dependency to a new or
existing project.

Run the command below to require Sindri in your existing composer json:

```
composer require valkyrja/sindri
```

You can even add Sindri to a completely empty project and allow it to generate
the base files for your project and give you a head start.

### Phar

Versioning and Release Process
------------------------------

Sindri uses [semantic versioning][semantic versioning url] with a major
release every year, and support for each major version for 2 years from the
date of release.

For more information view our
[Versioning and Release Process documentation][Versioning and Release Process url].

### Supported Versions

Bug fixes will be provided until 3 months after the next major release. Security
fixes will be provided for 2 years after the initial release.

| Version | PHP (*)   | Release        | Bug Fixes Until | Security Fixes Until |
|:--------|:----------|:---------------|:----------------|:---------------------|
| 26      | 8.4 - 8.6 | March 31, 2026 | Q2 2027         | Q1 2028              |
| 27      | 8.5 - 8.6 | Q1 2027        | Q2 2028         | Q1 2029              |
| 28      | 8.6+      | Q1 2028        | Q2 2029         | Q1 2030              |

(*) Supported PHP versions
(**) Pre-release that is not supported once v26 is released

Contributing
------------

Sindri is an Open Source, community-driven project.

Thank you for your interest in helping us develop, maintain, and release the
Sindri build tool!

You can find more information in our
[Contributing documentation][contributing url].

Security Issues
---------------

If you discover a security vulnerability within Sindri, please follow our
[disclosure procedure][security vulnerabilities url].

License
-------

The Sindri build tool is open-sourced software licensed under
the [MIT license][MIT license url]. You can view the
[Valkyrja License here][license url].

[Valkyrja url]: https://valkyrja.io

[github sindri]: https://github.com/valkyrjaio/sindri

[docs url]: ./src/Valkyrja/README.md

[New Project Guide url]: src/Valkyrja/GETTING_STARTED.md

[Versioning and Release Process url]: ./src/Valkyrja/VERSIONING_AND_RELEASE_PROCESS.md

[security vulnerabilities url]: ./SECURITY.md

[semantic versioning url]: https://semver.org/

[MIT license url]: https://opensource.org/licenses/MIT

[license url]: ./LICENSE.md

[contributing url]: ./CONTRIBUTING.md

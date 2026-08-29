# json-normalize

[![Integrate](https://github.com/ergebnis/json-normalize/workflows/Integrate/badge.svg)](https://github.com/ergebnis/json-normalize/actions)
[![Merge](https://github.com/ergebnis/json-normalize/workflows/Merge/badge.svg)](https://github.com/ergebnis/json-normalize/actions)
[![Release](https://github.com/ergebnis/json-normalize/workflows/Release/badge.svg)](https://github.com/ergebnis/json-normalize/actions)
[![Renew](https://github.com/ergebnis/json-normalize/workflows/Renew/badge.svg)](https://github.com/ergebnis/json-normalize/actions)

[![Code Coverage](https://codecov.io/gh/ergebnis/json-normalize/branch/main/graph/badge.svg)](https://codecov.io/gh/ergebnis/json-normalize)

[![Latest Stable Version](https://poser.pugx.org/ergebnis/json-normalize/v/stable)](https://packagist.org/packages/ergebnis/json-normalize)
[![Total Downloads](https://poser.pugx.org/ergebnis/json-normalize/downloads)](https://packagist.org/packages/ergebnis/json-normalize)
[![Monthly Downloads](http://poser.pugx.org/ergebnis/json-normalize/d/monthly)](https://packagist.org/packages/ergebnis/json-normalize)

This project provides a [`composer`](https://getcomposer.org) package with a console command for normalizing JSON documents, building on top of [`ergebnis/json-normalizer`](https://github.com/ergebnis/json-normalizer).

## Installation

Run

```sh
composer require ergebnis/json-normalize
```

## Usage

This project provides a `json-normalize` binary. When you have installed this project as a dependency, you can find it in `vendor/bin/json-normalize`.

### Normalizing a JSON file

Run

```sh
vendor/bin/json-normalize normalize composer.json
```

to normalize `composer.json`.

### Showing a diff

Run

```sh
vendor/bin/json-normalize normalize --diff composer.json
```

to show the difference between the original and the normalized JSON file.

### Performing a dry run

Run

```sh
vendor/bin/json-normalize normalize --diff --dry-run composer.json
```

to show the difference between the original and the normalized JSON file without modifying it.

The command exits with a non-zero exit code when the JSON file is not normalized, so you can use it to verify that JSON files are normalized on continuous integration.

### Normalizing according to a JSON schema

Without a JSON schema, the command normalizes formatting only: it applies a consistent indent, consistent escaping, and a final new-line. Since it detects the indent from the JSON file itself, a JSON file that is already formatted consistently is reported as already normalized.

To also sort properties, use a JSON schema. Run

```sh
vendor/bin/json-normalize normalize --schema=schema.json composer.json
```

to normalize `composer.json` according to the JSON schema in `schema.json`. The `--schema` option accepts a URI as well as a path, and resolves a relative path from the current working directory.

When you do not use the `--schema` option, the command uses the `$schema` property of the JSON file, if present, and resolves a relative path from the directory of the JSON file. Run

```sh
vendor/bin/json-normalize normalize --no-schema composer.json
```

to ignore the `$schema` property and normalize formatting only.

The command fails when it can not read the JSON schema, and when the JSON file is not valid according to the JSON schema. It never silently normalizes less than you asked for, so you can rely on `--dry-run` on continuous integration.

⚠️ When the JSON schema is a remote URI, the command fetches it on every run, and fails when it can not reach it.

### Specifying an indent

Run

```sh
vendor/bin/json-normalize normalize --indent-size=2 --indent-style=space composer.json
```

to normalize `composer.json` with an indent of two spaces.

The `--indent-size` option accepts an integer greater than `0`, and the `--indent-style` option accepts one of `space` and `tab`. You need to use both options together.

When you do not specify an indent, the command detects the indent from the JSON file.

## Changelog

The maintainers of this project record notable changes to this project in a [changelog](CHANGELOG.md).

## Contributing

The maintainers of this project suggest following the [contribution guide](.github/CONTRIBUTING.md).

## Code of Conduct

The maintainers of this project ask contributors to follow the [code of conduct](https://github.com/ergebnis/.github/blob/main/CODE_OF_CONDUCT.md).

## General Support Policy

The maintainers of this project provide limited support.

You can support the maintenance of this project by [sponsoring @ergebnis](https://github.com/sponsors/ergebnis).

## PHP Version Support Policy

This project currently supports the following PHP versions:

- [PHP 7.4](https://www.php.net/releases/#7.4.0) (has reached its end of life on November 28, 2022)
- [PHP 8.0](https://www.php.net/releases/#8.0.0) (has reached its end of life on November 26, 2023)
- [PHP 8.1](https://www.php.net/releases/#8.1.0) (has reached its end of life on December 31, 2025)
- [PHP 8.2](https://www.php.net/releases/#8.2.0)
- [PHP 8.3](https://www.php.net/releases/#8.3.0)
- [PHP 8.4](https://www.php.net/releases/#8.4.0)
- [PHP 8.5](https://www.php.net/releases/#8.5.0)

The maintainers of this project add support for a PHP version following its initial release and _may_ drop support for a PHP version when it has reached its [end of life](https://www.php.net/supported-versions.php).

## Security Policy

This project has a [security policy](.github/SECURITY.md).

## License

This project uses the [MIT license](LICENSE.md).

## Social

Follow [@localheinz](https://twitter.com/intent/follow?screen_name=localheinz) and [@ergebnis](https://twitter.com/intent/follow?screen_name=ergebnis) on Twitter.

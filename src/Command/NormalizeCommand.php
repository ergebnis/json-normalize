<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021-2026 Andreas Möller
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/ergebnis/json-normalize
 */

namespace Ergebnis\Json\Normalize\Command;

use Ergebnis\Json\Exception;
use Ergebnis\Json\Json;
use Ergebnis\Json\Normalize\Version;
use Ergebnis\Json\Normalizer;
use Ergebnis\Json\Pointer;
use Ergebnis\Json\Printer;
use Ergebnis\Json\SchemaValidator;
use JsonSchema\SchemaStorage;
use Localheinz\Diff;
use Symfony\Component\Console;

final class NormalizeCommand extends Console\Command\Command
{
    private Printer\PrinterInterface $printer;
    private Diff\Differ $differ;

    public function __construct(
        Printer\PrinterInterface $printer,
        Diff\Differ $differ
    ) {
        $this->printer = $printer;
        $this->differ = $differ;

        parent::__construct('normalize');
    }

    protected function configure(): void
    {
        $this->setDescription('Normalizes a JSON file');
        $this->setDefinition([
            new Console\Input\InputArgument(
                'path',
                Console\Input\InputArgument::REQUIRED,
                'Path to a JSON file',
            ),
            new Console\Input\InputOption(
                'diff',
                null,
                Console\Input\InputOption::VALUE_NONE,
                'Show the results of normalizing',
            ),
            new Console\Input\InputOption(
                'dry-run',
                null,
                Console\Input\InputOption::VALUE_NONE,
                'Show the results of normalizing, but do not modify the file',
            ),
            new Console\Input\InputOption(
                'indent-size',
                null,
                Console\Input\InputOption::VALUE_REQUIRED,
                'Indent size (an integer greater than 0); should be used with the --indent-style option',
            ),
            new Console\Input\InputOption(
                'indent-style',
                null,
                Console\Input\InputOption::VALUE_REQUIRED,
                \sprintf(
                    'Indent style (one of "%s"); should be used with the --indent-size option',
                    \implode(
                        '", "',
                        \array_keys(Normalizer\Format\Indent::CHARACTERS),
                    ),
                ),
            ),
            new Console\Input\InputOption(
                'no-schema',
                null,
                Console\Input\InputOption::VALUE_NONE,
                'Do not use the "$schema" property of the JSON file',
            ),
            new Console\Input\InputOption(
                'schema',
                null,
                Console\Input\InputOption::VALUE_REQUIRED,
                'URI or path to a JSON schema; when not used, the command uses the "$schema" property of the JSON file',
            ),
        ]);
    }

    protected function execute(
        Console\Input\InputInterface $input,
        Console\Output\OutputInterface $output
    ): int {
        $output->writeln([
            \sprintf(
                'Running %s.',
                Version::long(),
            ),
            '',
        ]);

        try {
            $indent = null;

            if (self::hasIndentOptions($input)) {
                $indent = self::indentFromInput($input);
            }
        } catch (\RuntimeException $exception) {
            $output->writeln(\sprintf(
                '<error>%s</error>',
                $exception->getMessage(),
            ));

            return Console\Command\Command::INVALID;
        }

        $path = $input->getArgument('path');

        if (!\is_string($path)) {
            $output->writeln('<error>Path needs to be a string.</error>');

            return Console\Command\Command::INVALID;
        }

        if (\is_dir($path)) {
            $output->writeln(\sprintf(
                '<error>Path "%s" is a directory, expected a file. A future release may support directories.</error>',
                $path,
            ));

            return Console\Command\Command::INVALID;
        }

        try {
            $json = Json::fromFile($path);
        } catch (Exception\FileDoesNotExist $exception) {
            $output->writeln(\sprintf(
                '<error>File "%s" does not exist.</error>',
                $path,
            ));

            return Console\Command\Command::INVALID;
        } catch (Exception\FileCanNotBeRead $exception) {
            $output->writeln(\sprintf(
                '<error>File "%s" can not be read.</error>',
                $path,
            ));

            return Console\Command\Command::INVALID;
        } catch (Exception\FileDoesNotContainJson $exception) {
            $output->writeln(\sprintf(
                '<error>File "%s" does not contain valid JSON.</error>',
                $path,
            ));

            return Console\Command\Command::INVALID;
        }

        if (
            true !== $input->getOption('dry-run')
            && !\is_writable($path)
        ) {
            $output->writeln(\sprintf(
                '<error>File "%s" is not writable.</error>',
                $path,
            ));

            return Console\Command\Command::INVALID;
        }

        $format = Normalizer\Format\Format::fromJson($json)->withJsonEncodeOptions(Normalizer\Format\JsonEncodeOptions::fromInt(\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION));

        if ($indent instanceof Normalizer\Format\Indent) {
            $format = $format->withIndent($indent);
        }

        try {
            $normalizers = self::schemaNormalizers(
                $input,
                $json,
                $path,
            );
        } catch (\RuntimeException $exception) {
            $output->writeln(\sprintf(
                '<error>%s</error>',
                $exception->getMessage(),
            ));

            return Console\Command\Command::INVALID;
        }

        $normalizers[] = new Normalizer\FormatNormalizer(
            $this->printer,
            $format,
        );

        $normalizer = new Normalizer\ChainNormalizer(...$normalizers);

        try {
            $normalized = $normalizer->normalize($json);
        } catch (Normalizer\Exception\Exception $exception) {
            $output->writeln(\sprintf(
                '<error>%s</error>',
                $exception->getMessage(),
            ));

            return Console\Command\Command::INVALID;
        }

        if ($json->encoded() === $normalized->encoded()) {
            $output->writeln(\sprintf(
                '<info>File "%s" is already normalized.</info>',
                $path,
            ));

            return Console\Command\Command::SUCCESS;
        }

        if (
            true === $input->getOption('diff')
            || true === $input->getOption('dry-run')
        ) {
            $diff = $this->differ->diff(
                $json->encoded(),
                $normalized->encoded(),
            );

            $output->writeln([
                '',
                '<fg=yellow>---------- begin diff ----------</>',
                self::formatDiff($diff),
                '<fg=yellow>----------- end diff -----------</>',
                '',
            ]);
        }

        if (true === $input->getOption('dry-run')) {
            $output->writeln(\sprintf(
                '<error>File "%s" is not normalized.</error>',
                $path,
            ));

            return Console\Command\Command::FAILURE;
        }

        if (false === \file_put_contents(
            $path,
            $normalized->encoded(),
        )) {
            $output->writeln(\sprintf(
                '<error>File "%s" can not be written.</error>',
                $path,
            ));

            return Console\Command\Command::INVALID;
        }

        $output->writeln(\sprintf(
            '<info>Successfully normalized "%s".</info>',
            $path,
        ));

        return Console\Command\Command::SUCCESS;
    }

    /**
     * Creates a schema normalizer when a JSON schema has been specified, and none otherwise.
     *
     * The --schema option wins over the "$schema" property of the JSON file, and --no-schema disables
     * using the "$schema" property.
     *
     * @throws \RuntimeException
     *
     * @return list<Normalizer\Normalizer>
     */
    private static function schemaNormalizers(
        Console\Input\InputInterface $input,
        Json $json,
        string $path
    ): array {
        $schema = $input->getOption('schema');

        if (\is_string($schema)) {
            $currentWorkingDirectory = \getcwd();

            if (!\is_string($currentWorkingDirectory)) {
                throw new \RuntimeException('Unable to determine the current working directory.');
            }

            // The --schema option is typed in a shell, so a relative path resolves from there.
            return [
                self::schemaNormalizer(self::resolveSchemaUri(
                    $schema,
                    $currentWorkingDirectory,
                )),
            ];
        }

        if (true === $input->getOption('no-schema')) {
            return [];
        }

        $decoded = $json->decoded();

        if (!$decoded instanceof \stdClass) {
            return [];
        }

        if (!\property_exists($decoded, '$schema')) {
            return [];
        }

        $schema = $decoded->{'$schema'};

        if (!\is_string($schema)) {
            return [];
        }

        // The "$schema" property is authored in the JSON file, so a relative path resolves from there.
        return [
            self::schemaNormalizer(self::resolveSchemaUri(
                $schema,
                \dirname($path),
            )),
        ];
    }

    private static function schemaNormalizer(string $schemaUri): Normalizer\SchemaNormalizer
    {
        return new Normalizer\SchemaNormalizer(
            $schemaUri,
            new SchemaStorage(),
            new SchemaValidator\SchemaValidator(),
            Pointer\Specification::never(),
        );
    }

    /**
     * @throws \RuntimeException
     */
    private static function resolveSchemaUri(
        string $schema,
        string $baseDirectory
    ): string {
        if ('' === $schema) {
            throw new \RuntimeException('Schema needs to be a non-empty string.');
        }

        // Anything carrying a scheme is a URI already, and is used as is.
        if (1 === \preg_match('{^[A-Za-z][A-Za-z0-9+.-]*://}', $schema)) {
            return $schema;
        }

        $absolutePath = $schema;

        if (!self::isAbsolutePath($schema)) {
            $absolutePath = $baseDirectory . \DIRECTORY_SEPARATOR . $schema;
        }

        $resolvedPath = \realpath($absolutePath);

        if (!\is_string($resolvedPath)) {
            throw new \RuntimeException(\sprintf(
                'Schema "%s" does not exist.',
                $schema,
            ));
        }

        return 'file://' . $resolvedPath;
    }

    private static function isAbsolutePath(string $path): bool
    {
        // A leading slash, or a Windows drive letter, e.g. C:\schema.json
        return 1 === \preg_match('{^(?:/|[A-Za-z]:[\\\\/])}', $path);
    }

    private static function hasIndentOptions(Console\Input\InputInterface $input): bool
    {
        return null !== $input->getOption('indent-size')
            || null !== $input->getOption('indent-style');
    }

    /**
     * @throws \RuntimeException
     */
    private static function indentFromInput(Console\Input\InputInterface $input): Normalizer\Format\Indent
    {
        /** @var null|string $indentSize */
        $indentSize = $input->getOption('indent-size');

        /** @var null|string $indentStyle */
        $indentStyle = $input->getOption('indent-style');

        if (null === $indentSize) {
            throw new \RuntimeException('When using the indent-style option, an indent size needs to be specified using the indent-size option.');
        }

        if (null === $indentStyle) {
            throw new \RuntimeException(\sprintf(
                'When using the indent-size option, an indent style (one of "%s") needs to be specified using the indent-style option.',
                \implode(
                    '", "',
                    \array_keys(Normalizer\Format\Indent::CHARACTERS),
                ),
            ));
        }

        if (
            (string) (int) $indentSize !== $indentSize
            || 1 > $indentSize
        ) {
            throw new \RuntimeException(\sprintf(
                'Indent size needs to be an integer greater than 0, but "%s" is not.',
                $indentSize,
            ));
        }

        if (!\array_key_exists(
            $indentStyle,
            Normalizer\Format\Indent::CHARACTERS,
        )) {
            throw new \RuntimeException(\sprintf(
                'Indent style needs to be one of "%s", but "%s" is not.',
                \implode(
                    '", "',
                    \array_keys(Normalizer\Format\Indent::CHARACTERS),
                ),
                $indentStyle,
            ));
        }

        return Normalizer\Format\Indent::fromSizeAndStyle(
            (int) $indentSize,
            $indentStyle,
        );
    }

    private static function formatDiff(string $diff): string
    {
        $lines = \explode(
            "\n",
            $diff,
        );

        $formatted = \array_map(static function (string $line): string {
            $replaced = \preg_replace(
                [
                    '/^(\+.*)$/',
                    '/^(-.*)$/',
                ],
                [
                    '<fg=green>$1</>',
                    '<fg=red>$1</>',
                ],
                $line,
            );

            if (!\is_string($replaced)) {
                return $line;
            }

            return $replaced;
        }, $lines);

        return \implode(
            "\n",
            $formatted,
        );
    }
}

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

namespace Ergebnis\Json\Normalize\Test\Unit\Command;

use Ergebnis\Json\Normalize\Command;
use Ergebnis\Json\Printer;
use Localheinz\Diff;
use PHPUnit\Framework;
use Symfony\Component\Console;

/**
 * @covers \Ergebnis\Json\Normalize\Command\NormalizeCommand
 */
final class NormalizeCommandTest extends Framework\TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (\file_exists($temporaryFile)) {
                \chmod(
                    $temporaryFile,
                    0644,
                );

                \unlink($temporaryFile);
            }
        }
    }

    public function testExecuteFailsWhenIndentSizeIsUsedWithoutIndentStyle(): void
    {
        $path = $this->createTemporaryFile('{}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--indent-size' => '2',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'an indent style (one of "space", "tab") needs to be specified',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenIndentStyleIsUsedWithoutIndentSize(): void
    {
        $path = $this->createTemporaryFile('{}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--indent-style' => 'space',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'an indent size needs to be specified',
            $commandTester->getDisplay(),
        );
    }

    /**
     * @dataProvider provideInvalidIndentSize
     */
    public function testExecuteFailsWhenIndentSizeIsNotAnIntegerGreaterThanZero(string $indentSize): void
    {
        $path = $this->createTemporaryFile('{}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--indent-size' => $indentSize,
            '--indent-style' => 'space',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'Indent size needs to be an integer greater than 0',
            $commandTester->getDisplay(),
        );
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function provideInvalidIndentSize(): iterable
    {
        $values = [
            'string-arbitrary' => 'foo',
            'int-zero' => '0',
            'int-minus-one' => '-1',
            'float' => '1.5',
        ];

        foreach ($values as $key => $value) {
            yield $key => [
                $value,
            ];
        }
    }

    public function testExecuteFailsWhenIndentStyleIsUnknown(): void
    {
        $path = $this->createTemporaryFile('{}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--indent-size' => '2',
            '--indent-style' => 'unknown',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'Indent style needs to be one of "space", "tab"',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenPathDoesNotExist(): void
    {
        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => __DIR__ . '/does-not-exist.json',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'does not exist',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenPathIsDirectory(): void
    {
        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => __DIR__,
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'is a directory',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenFileDoesNotContainValidJson(): void
    {
        $path = $this->createTemporaryFile('{"name":');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'does not contain valid JSON',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenFileIsNotWritable(): void
    {
        $path = $this->createTemporaryFile('{"name":"example"}');

        \chmod(
            $path,
            0444,
        );

        if (\is_writable($path)) {
            self::markTestSkipped('File is still writable, probably running as root.');
        }

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'is not writable',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenFileIsNotNormalizedAndDryRunIsUsed(): void
    {
        $contents = '{"name":"example","url":"https:\/\/example.com"}';

        $path = $this->createTemporaryFile($contents);

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--dry-run' => true,
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString(
            'is not normalized',
            $commandTester->getDisplay(),
        );
        self::assertStringEqualsFile(
            $path,
            $contents,
        );
    }

    public function testExecuteSucceedsWhenFileIsAlreadyNormalized(): void
    {
        $contents = <<<'JSON'
{
    "name": "example",
    "url": "https://example.com"
}

JSON;

        $path = $this->createTemporaryFile($contents);

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString(
            'is already normalized',
            $commandTester->getDisplay(),
        );
        self::assertStringEqualsFile(
            $path,
            $contents,
        );
    }

    public function testExecuteNormalizesFile(): void
    {
        $path = $this->createTemporaryFile('{"name":"example","url":"https:\/\/example.com"}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
        ]);

        $expected = <<<'JSON'
{
    "name": "example",
    "url": "https://example.com"
}
JSON;

        self::assertSame(0, $exitCode);
        self::assertStringContainsString(
            'Successfully normalized',
            $commandTester->getDisplay(),
        );
        self::assertStringEqualsFile(
            $path,
            $expected,
        );
    }

    public function testExecuteNormalizesFilePreservingSniffedIndent(): void
    {
        $contents = <<<'JSON'
{
  "name": "example",
  "url": "https:\/\/example.com"
}

JSON;

        $path = $this->createTemporaryFile($contents);

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
        ]);

        $expected = <<<'JSON'
{
  "name": "example",
  "url": "https://example.com"
}

JSON;

        self::assertSame(0, $exitCode);
        self::assertStringEqualsFile(
            $path,
            $expected,
        );
    }

    public function testExecuteNormalizesFileWithIndentSizeAndIndentStyle(): void
    {
        $path = $this->createTemporaryFile('{"name":"example"}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--indent-size' => '2',
            '--indent-style' => 'space',
        ]);

        $expected = <<<'JSON'
{
  "name": "example"
}
JSON;

        self::assertSame(0, $exitCode);
        self::assertStringEqualsFile(
            $path,
            $expected,
        );
    }

    public function testExecuteNormalizesFilePreservingUnicodeAndZeroFraction(): void
    {
        $path = $this->createTemporaryFile('{"name":"Jürgen","price":1.0}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
        ]);

        $expected = <<<'JSON'
{
    "name": "Jürgen",
    "price": 1.0
}
JSON;

        self::assertSame(0, $exitCode);
        self::assertStringEqualsFile(
            $path,
            $expected,
        );
    }

    public function testExecuteSucceedsWhenFileIsAlreadyNormalizedAndDryRunIsUsed(): void
    {
        $contents = <<<'JSON'
{
    "name": "example"
}

JSON;

        $path = $this->createTemporaryFile($contents);

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--dry-run' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString(
            'is already normalized',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteShowsDiffWhenDiffIsUsed(): void
    {
        $path = $this->createTemporaryFile('{"name":"example"}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--diff' => true,
        ]);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString(
            '---------- begin diff ----------',
            $display,
        );
        self::assertStringContainsString(
            '-{"name":"example"}',
            $display,
        );
        self::assertStringContainsString(
            '+    "name": "example"',
            $display,
        );
    }

    public function testExecuteShowsDiffWhenDryRunIsUsedAndFileIsNotNormalized(): void
    {
        $path = $this->createTemporaryFile('{"name":"example"}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--dry-run' => true,
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString(
            '---------- begin diff ----------',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenSchemaDoesNotExist(): void
    {
        $path = $this->createTemporaryFile('{}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--schema' => 'does-not-exist.json',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'Schema "does-not-exist.json" does not exist.',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenSchemaIsEmpty(): void
    {
        $path = $this->createTemporaryFile('{}');

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--schema' => '',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'Schema needs to be a non-empty string.',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteFailsWhenJsonIsInvalidAccordingToSchema(): void
    {
        $path = $this->createTemporaryFile(<<<'EOD'
{
    "unknown": 1
}

EOD);

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--schema' => self::pathToSchema(),
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'Original JSON is not valid according to schema',
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteNormalizesAccordingToSchemaWhenSchemaOptionIsUsed(): void
    {
        $path = $this->createTemporaryFile(<<<'EOD'
{
    "zebra": 1,
    "banana": 2,
    "apple": 3
}

EOD);

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--schema' => self::pathToSchema(),
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame(<<<'EOD'
{
    "apple": 3,
    "banana": 2,
    "zebra": 1
}

EOD, \file_get_contents($path));
    }

    public function testExecuteNormalizesAccordingToSchemaReferencedInJson(): void
    {
        $path = $this->createTemporaryFile(\sprintf(
            <<<'EOD'
{
    "zebra": 1,
    "apple": 2,
    "$schema": %s
}

EOD,
            \json_encode(
                self::pathToSchema(),
                \JSON_UNESCAPED_SLASHES,
            ),
        ));

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
        ]);

        self::assertSame(0, $exitCode);

        $contents = \file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith(<<<'EOD'
{
    "$schema":
EOD, $contents);
        self::assertStringContainsString('"apple": 2,', $contents);
        self::assertMatchesRegularExpression(
            '/"apple": 2,\s+"zebra": 1/',
            $contents,
        );
    }

    public function testExecuteDoesNotNormalizeAccordingToSchemaReferencedInJsonWhenNoSchemaOptionIsUsed(): void
    {
        $original = \sprintf(
            <<<'EOD'
{
    "zebra": 1,
    "apple": 2,
    "$schema": %s
}

EOD,
            \json_encode(
                self::pathToSchema(),
                \JSON_UNESCAPED_SLASHES,
            ),
        );

        $path = $this->createTemporaryFile($original);

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--no-schema' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame($original, \file_get_contents($path));
    }

    public function testExecuteUsesSchemaOptionInsteadOfSchemaReferencedInJson(): void
    {
        $path = $this->createTemporaryFile(<<<'EOD'
{
    "zebra": 1,
    "apple": 2,
    "$schema": "does-not-exist.json"
}

EOD);

        $commandTester = self::createCommandTester();

        $exitCode = $commandTester->execute([
            'path' => $path,
            '--schema' => self::pathToSchema(),
        ]);

        self::assertSame(0, $exitCode);
    }

    private static function pathToSchema(): string
    {
        $path = \realpath(__DIR__ . '/../../Fixture/Command/NormalizeCommand/schema.json');

        if (!\is_string($path)) {
            throw new \RuntimeException('Unable to resolve the path to the schema fixture.');
        }

        return $path;
    }

    private static function createCommandTester(): Console\Tester\CommandTester
    {
        $command = new Command\NormalizeCommand(
            new Printer\Printer(),
            new Diff\Differ(new Diff\Output\StrictUnifiedDiffOutputBuilder([
                'fromFile' => 'original',
                'toFile' => 'normalized',
            ])),
        );

        return new Console\Tester\CommandTester($command);
    }

    private function createTemporaryFile(string $contents): string
    {
        $temporaryFile = \tempnam(
            \sys_get_temp_dir(),
            'json-normalize-',
        );

        if (!\is_string($temporaryFile)) {
            throw new \RuntimeException('Unable to create a temporary file.');
        }

        \file_put_contents(
            $temporaryFile,
            $contents,
        );

        $this->temporaryFiles[] = $temporaryFile;

        return $temporaryFile;
    }
}

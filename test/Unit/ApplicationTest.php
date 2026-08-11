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

namespace Ergebnis\Json\Normalize\Test\Unit;

use Ergebnis\Json\Normalize\Application;
use Ergebnis\Json\Normalize\Command;
use Ergebnis\Json\Normalize\Version;
use PHPUnit\Framework;

/**
 * @covers \Ergebnis\Json\Normalize\Application
 *
 * @uses \Ergebnis\Json\Normalize\Command\NormalizeCommand
 * @uses \Ergebnis\Json\Normalize\Version
 */
final class ApplicationTest extends Framework\TestCase
{
    public function testDefaultsToName(): void
    {
        $application = new Application();

        self::assertSame('json-normalize', $application->getName());
    }

    public function testHasNormalizeCommand(): void
    {
        $application = new Application();

        self::assertTrue($application->has('normalize'));
        self::assertInstanceOf(
            Command\NormalizeCommand::class,
            $application->find('normalize'),
        );
    }

    public function testLongVersionIsVersion(): void
    {
        $application = new Application();

        self::assertSame(
            Version::long(),
            $application->getLongVersion(),
        );
    }
}

<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Andreas Möller
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/ergebnis/json-normalize
 */

namespace Ergebnis\Json\Normalize\Test\Unit;

use Ergebnis\Json\Normalize\Version;
use PHPUnit\Framework;

/**
 * @internal
 *
 * @covers \Ergebnis\Json\Normalize\Version
 */
final class VersionTest extends Framework\TestCase
{
    public function testLongReturnsVersion(): void
    {
        $expected = '<info>ergebnis/json-normalize</info> by <info>Andreas Möller</info> and contributors';

        self::assertSame($expected, Version::long());
    }
}

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

namespace Ergebnis\Json\Normalize;

use Ergebnis\Json\Printer;
use Localheinz\Diff;
use Symfony\Component\Console;

final class Application extends Console\Application
{
    public function __construct()
    {
        parent::__construct('json-normalize');

        $this->add(new Command\NormalizeCommand(
            new Printer\Printer(),
            new Diff\Differ(new Diff\Output\StrictUnifiedDiffOutputBuilder([
                'fromFile' => 'original',
                'toFile' => 'normalized',
            ])),
        ));
    }

    public function getLongVersion(): string
    {
        return Version::long();
    }
}

<?php

declare(strict_types=1);

arch('source files declare strict types')
    ->expect('Siberfx\LaraMeta')
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

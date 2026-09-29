<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Siberfx\LaraMeta\Facades\MetaTag;
use Siberfx\LaraMeta\MetaTagsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [MetaTagsServiceProvider::class];
    }

    /**
     * @return array<string, class-string>
     */
    #[Override]
    protected function getPackageAliases($app): array
    {
        return ['MetaTag' => MetaTag::class];
    }
}

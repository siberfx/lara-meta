<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Override;
use Siberfx\LaraMeta\MetaTag;

/**
 * `<x-meta-tags />` — renders the complete head block.
 */
final class MetaTags extends Component
{
    public function __construct(private readonly MetaTag $metaTag) {}

    #[Override]
    public function render(): Htmlable
    {
        return new HtmlString($this->metaTag->toHtml());
    }
}

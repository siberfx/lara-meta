<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\Enums;

/**
 * Twitter (X) card properties rendered by MetaTag::twitterCard(), in output order.
 *
 * @see https://developer.x.com/en/docs/x-for-websites/cards/overview/markup
 */
enum TwitterCardTag: string
{
    case Card = 'card';
    case Site = 'site';
    case Title = 'title';
    case Description = 'description';
    case Creator = 'creator';
    case ImageSrc = 'image:src';
    case Domain = 'domain';

    public function property(): string
    {
        return "twitter:{$this->value}";
    }
}

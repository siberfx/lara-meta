<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\Enums;

/**
 * OpenGraph properties rendered by MetaTag::openGraph(), in output order.
 *
 * @see https://ogp.me
 */
enum OpenGraphTag: string
{
    case Title = 'title';
    case Description = 'description';
    case Type = 'type';
    case Image = 'image';
    case Url = 'url';
    case Audio = 'audio';
    case Determiner = 'determiner';
    case Locale = 'locale';
    case SiteName = 'site_name';
    case Video = 'video';

    public function property(): string
    {
        return "og:{$this->value}";
    }
}

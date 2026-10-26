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
    case ImageWidth = 'image:width';
    case ImageHeight = 'image:height';
    case ImageAlt = 'image:alt';
    case Url = 'url';
    case Audio = 'audio';
    case Determiner = 'determiner';
    case Locale = 'locale';
    case SiteName = 'site_name';
    case Video = 'video';
    case ArticlePublishedTime = 'article:published_time';
    case ArticleModifiedTime = 'article:modified_time';
    case ArticleAuthor = 'article:author';
    case ArticleSection = 'article:section';

    /**
     * The `property` attribute; `article:*` properties are not prefixed with `og:`.
     */
    public function property(): string
    {
        return str_starts_with($this->value, 'article:') ? $this->value : "og:{$this->value}";
    }
}

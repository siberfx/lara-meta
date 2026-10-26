<?php

declare(strict_types=1);

namespace Siberfx\LaraMeta\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Siberfx\LaraMeta\MetaTag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered on the "web" group when `auto_middleware` is enabled. Applies the
 * `defaults` config and a `WebSite` JSON-LD document (`json_ld`) before the
 * controller runs, so anything the controller sets wins.
 */
final class InjectDefaultMetaTags
{
    public function __construct(private readonly MetaTag $metaTag) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $defaults = config('meta-tags.defaults');

        foreach (is_array($defaults) ? $defaults : [] as $key => $value) {
            if (is_string($key) && is_scalar($value) && (string) $value !== '') {
                $this->metaTag->set($key, (string) $value);
            }
        }

        if (config('meta-tags.json_ld') !== false) {
            $this->metaTag->addJsonLd([
                '@type' => 'WebSite',
                'name' => $this->metaTag->get('title') ?: config('app.name'),
                'url' => url('/'),
            ]);
        }

        return $next($request);
    }
}

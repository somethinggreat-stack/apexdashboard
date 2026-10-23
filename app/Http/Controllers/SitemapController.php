<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SitemapController extends Controller
{
    public function index(Request $request)
    {
        $host = strtolower($request->getHost());
        $root = strtolower((string) config('app.root_host'));

        // [path, changefreq, priority]
        if (config('app.site_split') && ($host === $root || $host === 'www.' . $root)) {
            // The root is only the front door now; the marketing pages live on partners.
            $urls = [['/', 'monthly', '1.0']];
        } elseif (config('app.site_split') && $host === strtolower((string) config('app.consumers_host'))) {
            // Nothing to index until the consumer site launches (the coming-soon page is noindex).
            $urls = [];
        } else {
            // The B2B marketing pages: on the partners host once the site is split, and on
            // the main host as before while it isn't. url() follows the request's own host.
            $urls = [
                ['/',                'weekly',  '1.0'],
                ['/trial',           'weekly',  '0.9'],
                ['/service-areas',   'monthly', '0.8'],
                ['/about',           'monthly', '0.7'],
                ['/results',         'monthly', '0.7'],
                ['/contact',         'monthly', '0.7'],
            ];
            foreach (config('service_areas') as $state) {
                $urls[] = ['/service-areas/' . $state['slug'], 'monthly', '0.6'];
            }
        }

        $today = now()->toDateString();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as [$path, $changefreq, $priority]) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars(url($path), ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$today}</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

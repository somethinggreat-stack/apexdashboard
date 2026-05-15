<?php

namespace App\Http\Controllers;

class SitemapController extends Controller
{
    public function index()
    {
        $states = config('service_areas');
        $today = now()->toDateString();

        // [path, changefreq, priority]
        $urls = [
            ['/',                'weekly',  '1.0'],
            ['/trial',           'weekly',  '0.9'],
            ['/service-areas',   'monthly', '0.8'],
            ['/about',           'monthly', '0.7'],
            ['/results',         'monthly', '0.7'],
            ['/contact',         'monthly', '0.7'],
        ];

        foreach ($states as $state) {
            $urls[] = ['/service-areas/' . $state['slug'], 'monthly', '0.6'];
        }

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

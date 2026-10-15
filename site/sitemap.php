<?php
require __DIR__ . '/lib.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');
$eps = episodes();
$latest = $eps ? substr((string)($eps[0]['published_at'] ?? $eps[0]['date'] ?? ''), 0, 10) : gmdate('Y-m-d');
$u = function ($path, $mod, $freq, $pri) { return '<url><loc>' . h(SITE . $path) . '</loc><lastmod>' . h($mod) . '</lastmod><changefreq>' . $freq . '</changefreq><priority>' . $pri . '</priority></url>'; };
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
echo $u('/', $latest, 'hourly', '1.0'), $u('/realm', $latest, 'weekly', '0.6'), $u('/about', $latest, 'monthly', '0.4'), $u('/support', $latest, 'monthly', '0.3');
foreach (array_keys($CLANS) as $k) echo $u('/clan/' . $k, $latest, 'daily', '0.7');
foreach ($eps as $e) echo $u('/episode/' . (int)$e['number'], substr((string)($e['published_at'] ?? $e['date'] ?? $latest), 0, 10), 'monthly', '0.8');
echo '</urlset>';

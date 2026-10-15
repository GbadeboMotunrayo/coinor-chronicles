<?php // local test only: emulates .htaccess for php -S. Not deployed.
$u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/episode/(\d+)/?$#', $u, $m)) { $_GET['n'] = $m[1]; require 'episode.php'; return true; }
if (preg_match('#^/clan/([a-z_]+)/?$#', $u, $m)) { $_GET['k'] = $m[1]; require 'clan.php'; return true; }
if ($u === '/realm') { require 'realm.php'; return true; }
if ($u === '/about') { require 'about.php'; return true; }
if ($u === '/sitemap.xml') { require 'sitemap.php'; return true; }
return false;

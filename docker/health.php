#!/usr/bin/env php
<?php
$host = parse_url(getenv('APP_URL') ?: 'http://localhost', PHP_URL_HOST);
$context = stream_context_create(['http' => ['timeout' => 5, 'header' => "Host: $host\r\n"]]);
$body = @file_get_contents('http://127.0.0.1/files/health', false, $context);
exit($body !== false && json_decode($body, true)['status'] === 'ok' ? 0 : 1);

<?php
// config.php

// Normalize base path from env
$basePath = getenv('BASE_PATH') ?: ($_ENV['BASE_PATH'] ?? '');
$basePath = rtrim($basePath, '/');

define('BASE_PATH', $basePath);

// Helpers
function url(string $path = ''): string {
    $path = ltrim($path, '/');
    return BASE_PATH . '/' . $path;
}
function asset(string $path = ''): string {
    return url($path);
}

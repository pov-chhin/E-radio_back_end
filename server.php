<?php

$publicPath = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// PHP's built-in server ignores Range requests for static files. Browsers and
// native audio players rely on byte ranges for long media, so serve public
// storage files here with proper 206 responses during local/LAN development.
if (str_starts_with($uri, '/storage/')) {
    $storageRoot = realpath($publicPath.'/storage');
    $filePath = realpath($publicPath.$uri);

    if ($storageRoot && $filePath && is_file($filePath) && str_starts_with($filePath, $storageRoot.DIRECTORY_SEPARATOR)) {
        $size = filesize($filePath);
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $range = $_SERVER['HTTP_RANGE'] ?? null;

        if ($range && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $matches)) {
            if ($matches[1] === '' && $matches[2] !== '') {
                $suffixLength = min((int) $matches[2], $size);
                $start = $size - $suffixLength;
            } else {
                $start = (int) $matches[1];
                $end = $matches[2] === '' ? $end : min((int) $matches[2], $end);
            }

            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */{$size}");
                exit;
            }

            $status = 206;
        }

        http_response_code($status);
        header('Accept-Ranges: bytes');
        header('Content-Type: '.(mime_content_type($filePath) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="'.basename($filePath).'"');
        header('Content-Length: '.($end - $start + 1));

        if ($status === 206) {
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }

        if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
            exit;
        }

        $handle = fopen($filePath, 'rb');
        fseek($handle, $start);
        $remaining = $end - $start + 1;

        while ($remaining > 0 && ! feof($handle)) {
            $chunk = fread($handle, min(8192, $remaining));

            if ($chunk === false) {
                break;
            }

            echo $chunk;
            $remaining -= strlen($chunk);
            flush();
        }

        fclose($handle);
        exit;
    }
}

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';

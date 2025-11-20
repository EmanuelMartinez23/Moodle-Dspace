<?php
// blocks/dspace_integration/serve_epub.php
// Sirve archivos internos de un EPUB extraído a /temp con inyección de <base> y reescritura de rutas.

require_once(__DIR__ . '/../../config.php');

require_login();

global $CFG, $USER;

$uuid = optional_param('uuid', '', PARAM_ALPHANUMEXT);
$serve = optional_param('file', '', PARAM_RAW_TRIMMED);

if (empty($uuid)) {
    http_response_code(400);
    echo 'Missing uuid';
    exit;
}

// Directorios
$basedir = rtrim($CFG->tempdir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dspace_epub';
$userdir = $basedir . DIRECTORY_SEPARATOR . intval($USER->id);
$bookdir = $userdir . DIRECTORY_SEPARATOR . $uuid;

function dspace_epub_safepath(string $rel): string {
    $rel = str_replace(['\\\\', '\\'], '/', $rel);
    $rel = rawurldecode($rel);
    $rel = preg_replace('~^/+~', '', $rel);
    $parts = [];
    foreach (explode('/', $rel) as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { array_pop($parts); continue; }
        $parts[] = $p;
    }
    return implode('/', $parts);
}

if ($serve === '') {
    http_response_code(400);
    echo 'Missing file parameter';
    exit;
}

$rel = dspace_epub_safepath($serve);
$full = $bookdir . DIRECTORY_SEPARATOR . $rel;

if (!file_exists($full) || !is_file($full)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

// Tipos
$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$mimes = [
    'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8', 'xhtml' => 'application/xhtml+xml; charset=utf-8',
    'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    'xml' => 'application/xml', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
    'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'audio/ogg'
];
$ctype = $mimes[$ext] ?? 'application/octet-stream';

// Si es HTML: inyectar base y reescribir rutas relativas
if (in_array($ext, ['html', 'htm', 'xhtml'])) {
    header('Content-Type: ' . $ctype);
    $html = file_get_contents($full);

    // Base href al directorio actual
    $reldir = trim(str_replace('\\', '/', dirname($rel)), './');
    if ($reldir === '.' || $reldir === '') { $reldir = ''; }
    $dirparam = ($reldir === '') ? '' : ($reldir . '/');
    $baseurl = new moodle_url('/blocks/dspace_integration/serve_epub.php', ['uuid' => $uuid, 'file' => $dirparam]);
    // Importante: escapar la URL para evitar errores XML (entityref) en XHTML por caracteres como &
    $base = '<base href="' . s($baseurl->out(false)) . '">';
    if (stripos($html, '<head') !== false) {
        $html = preg_replace('/<head(\b[^>]*)>/i', '<head$1>' . "\n    $base\n", $html, 1);
    } else {
        $html = $base . "\n" . $html;
    }

    // Reescritura href/src/data relativos -> volver a este script
    $rewrite = function($matches) use ($uuid, $dirparam) {
        $attr = $matches[1];
        $url  = $matches[2];
        $trim = trim($url);
        if ($trim === '' || $trim[0] === '#' || preg_match('~^(?:https?:)?//~i', $trim) || preg_match('~^(?:data:|javascript:|mailto:)~i', $trim)) {
            return $matches[0];
        }
        $rel = $dirparam . $trim;
        $rel = preg_replace('~^\./~', '', $rel);
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $rel)) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($segments); continue; }
            $segments[] = $seg;
        }
        $normalized = implode('/', $segments);
        $murl = new moodle_url('/blocks/dspace_integration/serve_epub.php', ['uuid' => $uuid, 'file' => $normalized]);
        // Escapar para que & se convierta a &amp; y no rompa XHTML
        return $attr . '="' . s($murl->out(false)) . '"';
    };

    $patterns = [
        '/\b(href)\s*=\s*"([^"]*)"/i',
        '/\b(href)\s*=\s*\'([^\']*)\'/i',
        '/\b(src)\s*=\s*"([^"]*)"/i',
        '/\b(src)\s*=\s*\'([^\']*)\'/i',
        '/\b(data)\s*=\s*"([^"]*)"/i',
        '/\b(data)\s*=\s*\'([^\']*)\'/i',
    ];
    foreach ($patterns as $pat) {
        $html = preg_replace_callback($pat, $rewrite, $html);
    }

    echo $html;
    exit;
}

// Otros: servir directo
header('Content-Type: ' . $ctype);
header('Content-Length: ' . filesize($full));
readfile($full);
exit;
?>

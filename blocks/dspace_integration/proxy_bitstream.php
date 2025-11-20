<?php
// Proxy sencillo para servir bitstreams de DSpace con el dominio de Moodle (evita CORS).
// Uso: /blocks/dspace_integration/proxy_bitstream.php?uuid=<bitstream_uuid>

require_once(__DIR__ . '/../../config.php');
require_login();

$uuid = optional_param('uuid', '', PARAM_ALPHANUMEXT);
if (empty($uuid)) {
    http_response_code(400);
    echo 'Missing uuid';
    exit;
}

// Configuración del bloque.
$apiUrl  = get_config('block_dspace_integration', 'server');
$user    = get_config('block_dspace_integration', 'email');
$pass    = get_config('block_dspace_integration', 'password');

if (empty($apiUrl) || empty($user) || empty($pass)) {
    http_response_code(500);
    echo 'DSpace configuration missing';
    exit;
}

// Autenticación con DSpace (copiada del bloque con mínimos cambios)
function dspace_get_token($apiUrl, $user, $pass) {
    try {
        $ch = curl_init("$apiUrl/security/csrf");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $csrf_token = '';
        if ($response !== false) {
            if (preg_match('/DSPACE-XSRF-COOKIE=([^;]+)/', $response, $m)) {
                $csrf_token = $m[1];
            }
        }
        curl_close($ch);

        $ch = curl_init("$apiUrl/authn/login");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "user=$user&password=$pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-XSRF-TOKEN: $csrf_token",
            "Cookie: DSPACE-XSRF-COOKIE=$csrf_token",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return null;
        }
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        curl_close($ch);
        if (preg_match('/Authorization:\s*Bearer\s+([^\s]+)/i', $header, $m)) {
            return $m[1];
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

$token = dspace_get_token($apiUrl, $user, $pass);
if (!$token) {
    http_response_code(502);
    echo 'Unable to authenticate with DSpace';
    exit;
}

$url = rtrim($apiUrl, '/') . "/bitstreams/{$uuid}/download";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
// Seguir redirecciones que suelen devolver los endpoints de descarga
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    // Aceptar binarios genéricos
    "Accept: */*"
]);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    echo 'Error fetching bitstream';
    curl_close($ch);
    exit;
}

$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$raw_header = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($ch);

// Propagar cabeceras relevantes
http_response_code($status ?: 200);

$headers = [];
foreach (explode("\r\n", $raw_header) as $h) {
    if (stripos($h, 'Content-Type:') === 0 ||
        stripos($h, 'Content-Disposition:') === 0 ||
        stripos($h, 'Content-Length:') === 0 ||
        stripos($h, 'Accept-Ranges:') === 0) {
        $headers[] = $h;
    }
}

// Si no hay Content-Type, asumir EPUB por defecto
$hasctype = false;
foreach ($headers as $h) {
    if (stripos($h, 'Content-Type:') === 0) { $hasctype = true; break; }
}
if (!$hasctype) {
    header('Content-Type: application/epub+zip');
}
foreach ($headers as $h) {
    header($h);
}

echo $body;
exit;

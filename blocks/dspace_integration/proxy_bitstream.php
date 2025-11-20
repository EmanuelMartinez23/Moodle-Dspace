<?php
// blocks/dspace_integration/proxy_bitstream.php
require_once(__DIR__ . '/../../config.php');
require_login();

$uuid = optional_param('uuid', '', PARAM_ALPHANUMEXT);
$action = optional_param('action', 'download', PARAM_ALPHA);

if (empty($uuid)) {
    http_response_code(400);
    echo 'Missing uuid';
    exit;
}

// Configuración del bloque
$apiUrl  = get_config('block_dspace_integration', 'server');
$user    = get_config('block_dspace_integration', 'email');
$pass    = get_config('block_dspace_integration', 'password');

if (empty($apiUrl) || empty($user) || empty($pass)) {
    http_response_code(500);
    echo 'DSpace configuration missing';
    exit;
}

// Función de autenticación con DSpace
function dspace_get_token($apiUrl, $user, $pass) {
    try {
        // Obtener token CSRF
        $ch = curl_init("$apiUrl/security/csrf");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $csrf_token = '';

        if ($response !== false) {
            if (preg_match('/DSPACE-XSRF-COOKIE=([^;]+)/', $response, $m)) {
                $csrf_token = $m[1];
            }
        }
        curl_close($ch);

        // Autenticación
        $ch = curl_init("$apiUrl/authn/login");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "user=$user&password=$pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
        error_log("DSpace auth error: " . $e->getMessage());
        return null;
    }
}

$token = dspace_get_token($apiUrl, $user, $pass);
if (!$token) {
    http_response_code(502);
    echo 'Unable to authenticate with DSpace';
    exit;
}

// Construir URL del bitstream
$apibase = rtrim($apiUrl, '/');
if (preg_match('~/server/api$~', $apibase)) {
    $url = $apibase . "/core/bitstreams/{$uuid}/content";
} else if (preg_match('~/api$~', $apibase)) {
    $url = $apibase . "/core/bitstreams/{$uuid}/content";
} else {
    $downloadbase = $apibase;
    $url = $downloadbase . "/bitstreams/{$uuid}/download";
}

// Obtener el archivo
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    "Accept: */*"
]);

$file_content = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($status !== 200 || empty($file_content)) {
    http_response_code(502);
    echo "Error fetching bitstream (Status: $status)";
    exit;
}

// Crear directorio temporal si no existe
$temp_dir = __DIR__ . '/temp';
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

// Generar nombre de archivo único
$filename = "epub_{$uuid}_" . time() . ".epub";
$filepath = $temp_dir . '/' . $filename;

// Guardar archivo
if (file_put_contents($filepath, $file_content) === false) {
    http_response_code(500);
    echo 'Error saving temporary file';
    exit;
}

// Para visualización - devolver JSON con URL
if ($action === 'view') {
    header('Content-Type: application/json; charset=utf-8');
    $file_url = $CFG->wwwroot . '/blocks/dspace_integration/temp/' . $filename;
    echo json_encode([
        'url' => $file_url,
        'filename' => $filename,
        'status' => 'success'
    ]);
    exit;
}

// Para descarga directa
header('Content-Type: ' . ($content_type ?: 'application/epub+zip'));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
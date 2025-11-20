<?php
// Previsualización SCORM "Opción A": sin crear actividad de Moodle.
// Descarga el ZIP desde DSpace, lo extrae a un directorio temporal y sirve el contenido
// en un iframe, exponiendo un API SCORM mínimo (stub) para evitar errores de JS.

require_once(__DIR__ . '/../../config.php');

require_login();

global $CFG, $USER, $PAGE, $OUTPUT;

$uuid = optional_param('uuid', '', PARAM_ALPHANUMEXT);
$serve = optional_param('file', '', PARAM_RAW_TRIMMED); // ruta relativa dentro del paquete a servir

if (empty($uuid)) {
    throw new moodle_exception('missingparam', 'error', '', 'uuid');
}

// Directorio de trabajo por usuario y UUID
$basedir = rtrim($CFG->tempdir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dspace_scorm';
$userdir = $basedir . DIRECTORY_SEPARATOR . intval($USER->id);
$packdir = $userdir . DIRECTORY_SEPARATOR . $uuid;

// Asegurar directorios
@mkdir($basedir, $CFG->directorypermissions, true);
@mkdir($userdir, $CFG->directorypermissions, true);
@mkdir($packdir, $CFG->directorypermissions, true);

// Función utilitaria para limpiar rutas relativas y prevenir traversal
function dspace_scorm_safepath(string $rel): string {
    $rel = str_replace(['\\\\', '\\'], '/', $rel);
    $rel = preg_replace('~^/+~', '', $rel);
    $parts = [];
    foreach (explode('/', $rel) as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { array_pop($parts); continue; }
        $parts[] = $p;
    }
    return implode('/', $parts);
}

// Descarga y extracción si hace falta
$manifestpath = $packdir . DIRECTORY_SEPARATOR . 'imsmanifest.xml';
if (!file_exists($manifestpath)) {
    // Descargar ZIP desde DSpace
    $apiUrl  = get_config('block_dspace_integration', 'server');
    $user    = get_config('block_dspace_integration', 'email');
    $pass    = get_config('block_dspace_integration', 'password');
    if (empty($apiUrl) || empty($user) || empty($pass)) {
        throw new moodle_exception('configdata', 'error', '', 'block_dspace_integration');
    }

    // Construir URL preferente al endpoint REST de contenido; si no hay /api, usar pública.
    $apibase = rtrim($apiUrl, '/');
    if (preg_match('~/server/api$~', $apibase) || preg_match('~/api$~', $apibase)) {
        $url = $apibase . "/core/bitstreams/{$uuid}/content";
    } else {
        $url = $apibase . "/bitstreams/{$uuid}/download";
    }

    // Autenticación a DSpace
    $token = null;
    $ch = curl_init($apiUrl . '/security/csrf');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $resp = curl_exec($ch);
    $csrf = '';
    if ($resp !== false && preg_match('/DSPACE-XSRF-COOKIE=([^;]+)/', $resp, $m)) {
        $csrf = $m[1];
    }
    curl_close($ch);
    $ch = curl_init($apiUrl . '/authn/login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "user=$user&password=$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-XSRF-TOKEN: $csrf",
        "Cookie: DSPACE-XSRF-COOKIE=$csrf",
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    $resp = curl_exec($ch);
    if ($resp !== false) {
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $hdr = substr($resp, 0, $hs);
        if (preg_match('/Authorization:\s*Bearer\s+([^\s]+)/i', $hdr, $m)) {
            $token = $m[1];
        }
    }
    curl_close($ch);
    if (empty($token)) {
        throw new moodle_exception('generalexceptionmessage', 'error', '', 'No se pudo autenticar en DSpace');
    }

    // Descargar ZIP
    $zipfile = $packdir . DIRECTORY_SEPARATOR . 'package.zip';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        'Accept: */*'
    ]);
    $data = curl_exec($ch);
    if ($data === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new moodle_exception('errorreadingfile', 'error', '', 'Error cURL en descarga SCORM: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status >= 400) {
        throw new moodle_exception('errorreadingfile', 'error', '', 'DSpace respondió ' . $status);
    }
    file_put_contents($zipfile, $data);

    // Extraer
    $zip = new ZipArchive();
    if ($zip->open($zipfile) === true) {
        $zip->extractTo($packdir);
        $zip->close();
    } else {
        throw new moodle_exception('errorunzippingfiles', 'error');
    }
}

// Petición de servir archivos internos del paquete
if ($serve !== '') {
    // Aceptar rutas URL-encoded desde los recursos internos del paquete (espacios, acentos, etc.)
    $decoded = rawurldecode($serve);
    $rel = dspace_scorm_safepath($decoded);
    $full = $packdir . DIRECTORY_SEPARATOR . $rel;
    if (!file_exists($full) || !is_file($full)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    // Content-Type simple por extensión
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $mimes = [
        'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
        'xml' => 'application/xml', 'xhtml' => 'application/xhtml+xml',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
        'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'audio/ogg'
    ];
    $ctype = $mimes[$ext] ?? 'application/octet-stream';
    // Para archivos HTML: inyectar <base> y reescribir rutas relativas en href/src a través de este mismo script.
    if (in_array($ext, ['html','htm','xhtml'])) {
        header('Content-Type: ' . $ctype);
        $html = file_get_contents($full);

        // Calcular base href apuntando al directorio del archivo actual
        $reldir = trim(str_replace('\\', '/', dirname($rel)), './');
        if ($reldir === '.' || $reldir === '') { $reldir = ''; }
        $dirparam = ($reldir === '') ? '' : ($reldir . '/');
        $baseurl = new moodle_url('/blocks/dspace_integration/preview_scorm.php', ['uuid' => $uuid, 'file' => $dirparam]);
        // Detectar XHTML para autocerrar correctamente la etiqueta base
        $isxhtml = (stripos($html, '<?xml') !== false)
            || (stripos($html, 'xmlns="http://www.w3.org/1999/xhtml"') !== false)
            || (preg_match('~<\s*html[^>]*\bxmlns\s*=\s*"http://www.w3.org/1999/xhtml"~i', $html) === 1);
        $basehref = s($baseurl->out(false));
        $basetag = $isxhtml ? '<base href="' . $basehref . '"/>' : '<base href="' . $basehref . '">';

        // Reemplazar un <base> existente o inyectar si no existe
        $hadbase = false;
        if (preg_match('/<\s*base\b[^>]*>/i', $html)) {
            $hadbase = true;
            $html = preg_replace('/<\s*base\b[^>]*>/i', $basetag, $html, 1);
        }
        if (!$hadbase) {
            if (stripos($html, '<head') !== false) {
                $html = preg_replace('/<head(\b[^>]*)>/i', '<head$1>' . "\n    $basetag\n", $html, 1);
            } else {
                $html = $basetag . "\n" . $html;
            }
        }

        // Reescritura de rutas relativas en atributos href/src a través de este mismo script
        // Evitar reescribir URLs absolutas (http, https, //), data:, javascript:, mailto:, #
        $rewrite = function($matches) use ($uuid, $dirparam) {
            $attr = $matches[1];
            $url  = $matches[2];
            $trim = trim($url);
            if ($trim === '' || $trim[0] === '#' || preg_match('~^(?:https?:)?//~i', $trim) ||
                preg_match('~^(?:data:|javascript:|mailto:)~i', $trim)) {
                return $matches[0];
            }
            // Resolver ./ y ../ sobre el directorio actual (dirparam)
            $rel = $dirparam . $trim;
            $rel = preg_replace('~^\./~', '', $rel);
            // Normalizar ../ de forma segura
            $segments = [];
            foreach (explode('/', str_replace('\\', '/', $rel)) as $seg) {
                if ($seg === '' || $seg === '.') continue;
                if ($seg === '..') { array_pop($segments); continue; }
                $segments[] = $seg;
            }
            $normalized = implode('/', $segments);
            $murl = new moodle_url('/blocks/dspace_integration/preview_scorm.php', ['uuid' => $uuid, 'file' => $normalized]);
            $out = s($murl->out(false));
            return $attr . '="' . $out . '"';
        };

        // Reescribir en tags comunes
        $patterns = [
            // href en link/a/area
            '/\b(href)\s*=\s*"([^"]*)"/i',
            '/\b(href)\s*=\s*\'([^\']*)\'/i',
            // src en script/img/audio/video/source/iframe/object/embed
            '/\b(src)\s*=\s*"([^"]*)"/i',
            '/\b(src)\s*=\s*\'([^\']*)\'/i',
            // data en object
            '/\b(data)\s*=\s*"([^"]*)"/i',
            '/\b(data)\s*=\s*\'([^\']*)\'/i',
        ];
        foreach ($patterns as $pat) {
            $html = preg_replace_callback($pat, $rewrite, $html);
        }

        echo $html;
        exit;
    } else {
        header('Content-Type: ' . $ctype);
        header('Content-Length: ' . filesize($full));
        readfile($full);
        exit;
    }
}

// Determinar el launch file (index.html o el href del primer resource en imsmanifest.xml)
$launch = 'index.html';
if (file_exists($manifestpath)) {
    $xml = @simplexml_load_file($manifestpath);
    if ($xml) {
        $resources = $xml->resources->resource ?? null;
        if ($resources) {
            foreach ($resources as $res) {
                $href = (string)$res['href'];
                if (!empty($href)) { $launch = (string)$href; break; }
            }
        }
    }
}
$launch = dspace_scorm_safepath($launch);

// Limpiar temporales antiguos (TTL 1 día) solo en el directorio del usuario
$ttl = 60 * 60 * 24;
$now = time();
foreach (glob($userdir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $dir) {
    if (@filemtime($dir) && ($now - filemtime($dir)) > $ttl) {
        // Eliminar dir recursivamente
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}

// Renderizar el visor con iframe
$PAGE->set_url(new moodle_url('/blocks/dspace_integration/preview_scorm.php', ['uuid' => $uuid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Previsualización SCORM');
$PAGE->set_pagelayout('popup');

echo $OUTPUT->header();
?>
<style>
  html, body { height: 100%; margin: 0; background: #111; }
  #bar { background:#fff; border-bottom:1px solid #ddd; padding:8px 12px; font-size:14px; display:flex; gap:10px; align-items:center; }
  #player { position: fixed; top: 40px; bottom: 0; left: 0; right: 0; }
  #scoframe { width: 100%; height: 100%; border: 0; background: #fff; }
  .badge { font-size: 12px; color: #6c757d; }
</style>
<div id="bar">
  <strong>SCORM (vista previa)</strong>
  <span class="badge">No guarda progreso ni calificaciones</span>
  <span class="badge">Fuente: DSpace UUID <?php echo s($uuid); ?></span>
  <a class="btn btn-sm btn-secondary" target="_blank" rel="noopener" href="<?php echo new moodle_url('/blocks/dspace_integration/preview_scorm.php', ['uuid' => $uuid, 'file' => $launch]); ?>">Abrir en pestaña nueva</a>
  <span class="badge">Archivo inicial: <?php echo s($launch); ?></span>
</div>
<div id="player">
  <iframe id="scoframe" src="<?php echo new moodle_url('/blocks/dspace_integration/preview_scorm.php', ['uuid' => $uuid, 'file' => $launch]); ?>" allowfullscreen></iframe>
</div>
<script>
// API SCORM mínimo (1.2 y 2004) para evitar errores en modo vista previa.
(function(){
  function noop(){ return "0"; }
  var data = {};
  var API12 = {
    LMSInitialize: function(){ return "true"; },
    LMSFinish: function(){ return "true"; },
    LMSGetValue: function(el){ return data[el] || ""; },
    LMSSetValue: function(el, val){ data[el]=String(val); return "true"; },
    LMSCommit: function(){ return "true"; },
    LMSGetLastError: noop, LMSGetErrorString: function(){ return ""; }, LMSGetDiagnostic: function(){ return ""; }
  };
  var API2004 = {
    Initialize: function(){ return "true"; },
    Terminate: function(){ return "true"; },
    GetValue: function(el){ return data[el] || ""; },
    SetValue: function(el, val){ data[el]=String(val); return "true"; },
    Commit: function(){ return "true"; },
    GetLastError: noop, GetErrorString: function(){ return ""; }, GetDiagnostic: function(){ return ""; }
  };
  // Exponer en el parent (esta página) para que el SCO en el iframe lo encuentre al subir en la jerarquía.
  window.API = API12;
  window.API_1484_11 = API2004;
})();
</script>
<?php
echo $OUTPUT->footer();

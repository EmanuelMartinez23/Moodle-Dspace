<?php
// blocks/dspace_integration/preview_epub.php
// Visor EPUB sin librería JS: descarga y extrae el EPUB en /temp, parsea OPF y sirve capítulos vía proxy local.

require_once(__DIR__ . '/../../config.php');

require_login();

global $CFG, $USER, $PAGE, $OUTPUT;

$uuid = optional_param('uuid', '', PARAM_ALPHANUMEXT); // UUID del bitstream EPUB en DSpace
$nav = optional_param('nav', 0, PARAM_INT);            // índice del spine a mostrar

if (empty($uuid)) {
    throw new moodle_exception('missingparam', 'error', '', 'uuid');
}

// Directorios temporales por usuario
$basedir = rtrim($CFG->tempdir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dspace_epub';
$userdir = $basedir . DIRECTORY_SEPARATOR . intval($USER->id);
$bookdir = $userdir . DIRECTORY_SEPARATOR . $uuid; // carpeta de extracción
@mkdir($basedir, $CFG->directorypermissions, true);
@mkdir($userdir, $CFG->directorypermissions, true);
@mkdir($bookdir, $CFG->directorypermissions, true);

// Utilidades
function dspace_epub_safepath(string $rel): string {
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

// Descargar y extraer si no existe container.xml
$containerxml = $bookdir . DIRECTORY_SEPARATOR . 'META-INF' . DIRECTORY_SEPARATOR . 'container.xml';
if (!file_exists($containerxml)) {
    // Config de DSpace
    $apiUrl  = get_config('block_dspace_integration', 'server');
    $user    = get_config('block_dspace_integration', 'email');
    $pass    = get_config('block_dspace_integration', 'password');
    if (empty($apiUrl) || empty($user) || empty($pass)) {
        throw new moodle_exception('configdata', 'error', '', 'block_dspace_integration');
    }

    // Construir URL
    $apibase = rtrim($apiUrl, '/');
    if (preg_match('~/server/api$~', $apibase) || preg_match('~/api$~', $apibase)) {
        $url = $apibase . "/core/bitstreams/{$uuid}/content";
    } else {
        $url = $apibase . "/bitstreams/{$uuid}/download";
    }

    // Autenticación (similar a otros scripts)
    $ch = curl_init($apiUrl . '/security/csrf');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $resp = curl_exec($ch);
    $csrf = '';
    if ($resp !== false && preg_match('/DSPACE-XSRF-COOKIE=([^;]+)/', $resp, $m)) { $csrf = $m[1]; }
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
    $token = '';
    if ($resp !== false) {
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $hdr = substr($resp, 0, $hs);
        if (preg_match('/Authorization:\s*Bearer\s+([^\s]+)/i', $hdr, $m)) { $token = $m[1]; }
    }
    curl_close($ch);
    if (empty($token)) {
        throw new moodle_exception('generalexceptionmessage', 'error', '', 'No se pudo autenticar en DSpace');
    }

    // Descargar EPUB
    $zipfile = $bookdir . DIRECTORY_SEPARATOR . 'book.epub';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        'Accept: */*'
    ]);
    $data = curl_exec($ch);
    if ($data === false) { $err = curl_error($ch); curl_close($ch); throw new moodle_exception('errorreadingfile', 'error', '', $err); }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status >= 400) { throw new moodle_exception('errorreadingfile', 'error', '', 'HTTP ' . $status); }
    file_put_contents($zipfile, $data);

    // Extraer
    $zip = new ZipArchive();
    if ($zip->open($zipfile) === true) {
        $zip->extractTo($bookdir);
        $zip->close();
    } else {
        throw new moodle_exception('errorunzippingfiles', 'error');
    }
}

// Parsear container.xml para localizar OPF
if (!file_exists($containerxml)) {
    throw new moodle_exception('errorreadingfile', 'error', '', 'META-INF/container.xml no encontrado');
}

$container = @simplexml_load_file($containerxml);
if (!$container) {
    throw new moodle_exception('errorreadingfile', 'error', '', 'container.xml inválido');
}

$rootfile = '';
if (isset($container->rootfiles->rootfile)) {
    foreach ($container->rootfiles->rootfile as $rf) {
        $path = (string)$rf['full-path'];
        if ($path) { $rootfile = $path; break; }
    }
}
if (empty($rootfile)) {
    throw new moodle_exception('errorreadingfile', 'error', '', 'No se encontró el OPF en container.xml');
}

$rootfile = dspace_epub_safepath($rootfile);
$opfpath = $bookdir . DIRECTORY_SEPARATOR . $rootfile;
if (!file_exists($opfpath)) {
    throw new moodle_exception('errorreadingfile', 'error', '', 'OPF no existe');
}

// Parsear OPF: manifest y spine
$opf = @simplexml_load_file($opfpath);
if (!$opf) {
    throw new moodle_exception('errorreadingfile', 'error', '', 'OPF inválido');
}

// Namespaces posibles
$ns = $opf->getNamespaces(true);
if (isset($ns['opf'])) { $opf = $opf->children($ns['opf']); }

$manifest = [];
if (isset($opf->manifest->item)) {
    foreach ($opf->manifest->item as $it) {
        $id = (string)$it['id'];
        $href = (string)$it['href'];
        if ($id && $href) { $manifest[$id] = dspace_epub_safepath($href); }
    }
}

$spine = [];
if (isset($opf->spine->itemref)) {
    foreach ($opf->spine->itemref as $ref) {
        $idref = (string)$ref['idref'];
        if (isset($manifest[$idref])) { $spine[] = $manifest[$idref]; }
    }
}

if (empty($spine)) {
    // Fallback: intentar primer HTML del manifest
    foreach ($manifest as $h) {
        if (preg_match('~\.(x?html?)$~i', $h)) { $spine[] = $h; break; }
    }
}

if (empty($spine)) {
    throw new moodle_exception('errorreadingfile', 'error', '', 'No hay capítulos en el EPUB');
}

// Normalizar base del OPF para resolver rutas relativas de capítulos
$opfdir = trim(str_replace('\\', '/', dirname($rootfile)), './');
if ($opfdir !== '' && $opfdir !== '.') {
    $spine = array_map(function($p) use ($opfdir){ return dspace_epub_safepath($opfdir . '/' . $p); }, $spine);
}

$count = count($spine);
$nav = max(0, min($count - 1, $nav));

// Construir URL al servidor de recursos
$serveurl = new moodle_url('/blocks/dspace_integration/serve_epub.php', ['uuid' => $uuid]);
$chapter = $spine[$nav];
$chapterurl = new moodle_url($serveurl, ['file' => $chapter]);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('popup');
$PAGE->set_url(new moodle_url('/blocks/dspace_integration/preview_epub.php', ['uuid' => $uuid, 'nav' => $nav]));
$PAGE->set_title('Visor EPUB | Moodle');

echo $OUTPUT->header();
?>
<style>
    html, body { height: 100%; margin:0; padding:0; overflow:hidden; background:#f8f9fa; }
    #viewer-container { position:fixed; inset:0; display:flex; flex-direction:column; background:#fff; }
    #toolbar { padding:12px 16px; border-bottom:1px solid #dee2e6; display:flex; gap:12px; align-items:center; background:#fff; z-index:2; }
    #iframewrap { position:relative; flex:1; }
    #pageframe { position:absolute; inset:0; width:100%; height:100%; border:0; background:#fff; }
    .btn { padding:6px 12px; border:1px solid #ced4da; background:#fff; border-radius:4px; cursor:pointer; }
    .btn:disabled { opacity:.5; cursor:not-allowed; }
    #status { margin-left:auto; color:#6c757d; }
    #toc { max-width:320px; }
    @media (max-width: 768px) { #toc { max-width:200px; } }
</style>
<div id="viewer-container">
    <div id="toolbar">
        <?php
        $prevurl = new moodle_url('/blocks/dspace_integration/preview_epub.php', ['uuid' => $uuid, 'nav' => max(0, $nav-1)]);
        $nexturl = new moodle_url('/blocks/dspace_integration/preview_epub.php', ['uuid' => $uuid, 'nav' => min($count-1, $nav+1)]);
        ?>
        <a class="btn" href="<?php echo $prevurl->out(false); ?>" <?php echo $nav<=0 ? 'aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '' ?>>◀ Anterior</a>
        <a class="btn" href="<?php echo $nexturl->out(false); ?>" <?php echo $nav>=$count-1 ? 'aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '' ?>>▶ Siguiente</a>
        <select id="toc" class="btn" onchange="location.href=this.value;">
            <?php
            for ($i=0; $i<$count; $i++) {
                $u = new moodle_url('/blocks/dspace_integration/preview_epub.php', ['uuid' => $uuid, 'nav' => $i]);
                $sel = ($i === $nav) ? ' selected' : '';
                $label = 'Capítulo ' . ($i+1);
                echo '<option value="' . s($u->out(false)) . '"' . $sel . '>' . s($label) . '</option>';
            }
            ?>
        </select>
        <span id="status">EPUB cargado (<?php echo ($nav+1) . '/'.$count; ?>)</span>
    </div>
    <div id="iframewrap">
        <iframe id="pageframe" src="<?php echo s($chapterurl->out(false)); ?>" title="Capítulo"></iframe>
    </div>
</div>
<?php
echo $OUTPUT->footer();
// Limpieza de temporales viejos (TTL 1 día)
$ttl = 60*60*24; $now=time();
foreach (glob($userdir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $dir) {
    if (basename($dir) === basename($bookdir)) continue;
    if ($now - @filemtime($dir) > $ttl) {
        // eliminar recursivo
        $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }
}
?>

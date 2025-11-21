
<?php
defined('MOODLE_INTERNAL') || die();

class block_dspace_integration extends block_base {

    public function init() {
        $this->title = 'DSpace Integration';
    }

    public function get_content() {
        global $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();

        // CSS del bloque (alineaciones y soporte de scroll para DataTables)
        $customcss = '
            .block_dspace_integration .dspace-table-wrap{ overflow-x:auto; }
            .block_dspace_integration table.dspace-table{ width:100%; }
            .block_dspace_integration table.dspace-table th,
            .block_dspace_integration table.dspace-table td{ vertical-align: middle !important; }
            .block_dspace_integration .dspace-col-item,
            .block_dspace_integration .dspace-col-bitstreams{ text-align:left; vertical-align:middle; }
            .block_dspace_integration .dspace-col-preview,
            .block_dspace_integration .dspace-col-add{ text-align:center; }
        ';

        // Asegurar que el bloque no imprima JS “en crudo”.
        // Encapsulamos el script dentro de un nowdoc y lo pasamos a js_init_code más abajo.
        $customjs = <<<'JS'
document.addEventListener('DOMContentLoaded', function(){
                // Funciones auxiliares de previsualización
                window.openPreviewWindow = function(url) {
                    // Abre en una nueva ventana/pestaña para evitar problemas de sandboxing en iframes
                    window.open(url, '_blank', 'noopener');
                };
                window.previewScormNotice = function(url){
                    // Mensaje informativo mínimo si no es posible previsualizar directamente
                    alert('Para previsualizar un paquete SCORM es necesario que el paquete esté desplegado en un servidor web. Intentaremos abrir el archivo, pero si no se muestra, contacte con el administrador.');
                    window.open(url, '_blank', 'noopener');
                };

                // Toast mínimo por si no existe showToast (evitar errores en páginas sin bloque visible)
                if (typeof window.showToast !== 'function') {
                    window.showToast = function(msg, type){
                        try {
                            // Crear contenedor si no existe
                            var c = document.getElementById('dspace-toast-container');
                            if (!c) {
                                c = document.createElement('div');
                                c.id = 'dspace-toast-container';
                                c.style.position = 'fixed';
                                c.style.zIndex = '99999';
                                c.style.top = '16px';
                                c.style.right = '16px';
                                c.style.display = 'flex';
                                c.style.flexDirection = 'column';
                                c.style.gap = '8px';
                                document.body.appendChild(c);
                            }
                            var t = document.createElement('div');
                            t.textContent = msg || '';
                            t.style.padding = '10px 12px';
                            t.style.borderRadius = '6px';
                            t.style.color = '#fff';
                            t.style.fontSize = '14px';
                            t.style.boxShadow = '0 2px 8px rgba(0,0,0,.2)';
                            t.style.background = (type === 'error') ? '#d9534f' : '#28a745';
                            c.appendChild(t);
                            setTimeout(function(){ try { c.removeChild(t); } catch(e){} }, 2500);
                        } catch(e) {
                            // Último recurso
                            if (type === 'error') {
                                console.error(msg);
                            } else {
                                console.log(msg);
                            }
                        }
                    };
                }

                // Utilidades DataTables: carga garantizada e inicialización
                (function ensureDataTables(){
                    function loadScript(src){ return new Promise(function(res, rej){ var s=document.createElement('script'); s.src=src; s.onload=res; s.onerror=rej; document.head.appendChild(s); }); }
                    function loadCSS(href){ var l=document.createElement('link'); l.rel='stylesheet'; l.href=href; document.head.appendChild(l); }
                    // Cargar jQuery si no existe (DataTables requiere jQuery)
                    var needJQ = (typeof window.jQuery === 'undefined');
                    var jqPromise = needJQ ? loadScript('https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js') : Promise.resolve();
                    // Cargar DataTables CSS/JS
                    loadCSS('https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.1/css/dataTables.bootstrap5.min.css');
                    Promise.resolve().then(function(){ return jqPromise; }).then(function(){
                        if (typeof jQuery.fn.DataTable === 'function') { return; }
                        return loadScript('https://cdn.jsdelivr.net/npm/datatables.net@1.13.1/js/jquery.dataTables.min.js')
                          .then(function(){ return loadScript('https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.1/js/dataTables.bootstrap5.min.js'); });
                    }).then(function(){ try { initDspaceTables(); } catch(e){} });
                })();

                function initDspaceTables(context){
                    var $ = window.jQuery; if (!$) return;
                    var root = context || document;
                    $(root).find('table.dspace-table').each(function(){
                        var $tbl = $(this);
                        if ($tbl.hasClass('dt-initialized')) return;
                        try {
                            $tbl.addClass('dt-initialized').DataTable({
                                paging: true,
                                searching: true,
                                ordering: true,
                                info: true,
                                lengthChange: true,
                                autoWidth: false,
                                scrollX: true,
                                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.1/i18n/es-ES.json' },
                                columnDefs: [
                                    { targets: [0,1], className: 'text-start align-middle' },
                                    { targets: [2,3], className: 'text-center align-middle' }
                                ]
                            });
                        } catch(e) {
                            // Si falla, quitar marca para intentar nuevamente más tarde
                            $tbl.removeClass('dt-initialized');
                        }
                    });
                }

                // Iniciar tablas al hacer clic en toggles que muestran colecciones
                document.addEventListener('click', function(ev){
                    var t = ev.target;
                    if (t && (t.classList.contains('toggle') || t.closest('.toggle'))){
                        // Dar tiempo a que se despliegue el contenedor
                        setTimeout(function(){ initDspaceTables(document); }, 100);
                    }
                });

                // Agregar URL+Nombre de bitstream a los detalles de la tarea únicamente en descripción (intro/description)
                window.addToAssignmentDetails = async function(url, name){
                    try {
                        // 1) TinyMCE (buscar el editor asociado a intro/description)
                        var targetTiny = null;
                        if (window.tinymce && tinymce.editors && tinymce.editors.length){
                            for (var i=0;i<tinymce.editors.length;i++){
                                var ed = tinymce.editors[i];
                                var el = ed && ed.targetElm;
                                var id = (el && el.id ? el.id : '').toLowerCase();
                                var nm = (el && el.name ? el.name : '').toLowerCase();
                                if (/intro|description/.test(id+' '+nm)) { targetTiny = ed; break; }
                            }
                        }
                        if (targetTiny) {
                            var ed = targetTiny;
                            let content = ed.getContent({format:'html'}) || '';
                            if (content.indexOf(url) !== -1) { showToast('Este recurso ya está agregado.', ''); return; }
                            // Asegurar sección "Recursos externos"
                            if (content.indexOf('id="dspace-external-resources"') === -1) {
                                content += '\n<div id="dspace-external-resources"><h3>Recursos externos</h3><ul></ul></div>';
                            }
                            // Insertar <li> dentro del UL de la sección
                            content = content.replace(/(<div[^>]*id="dspace-external-resources"[^>]*>[\s\S]*?<ul[^>]*>)([\s\S]*?)(<\/ul>)/, function(m, a, b, c){
                                return a + b + '<li><a href="'+url+'" target="_blank" rel="noopener">'+(name||url)+'</a></li>' + c;
                            });
                            ed.setContent(content);
                            showToast('Recurso agregado a la tarea.', '');
                            return;
                        }
                        // 2) Atto (buscar el wrapper asociado a intro/description)
                        var attoWrapper = null;
                        var wrappers = document.querySelectorAll('.editor_atto');
                        for (var j=0;j<wrappers.length;j++){
                            var w = wrappers[j];
                            var ta = w.querySelector('textarea');
                            var id2 = (ta && ta.id ? ta.id : '').toLowerCase();
                            var nm2 = (ta && ta.name ? ta.name : '').toLowerCase();
                            if (/intro|description/.test(id2+' '+nm2)) { attoWrapper = w; break; }
                        }
                        if (attoWrapper) {
                            var atto = attoWrapper.querySelector('.editor_atto_content');
                            var html = (atto && atto.innerHTML) ? atto.innerHTML : '';
                            if (html.indexOf(url) !== -1) { showToast('Este recurso ya está agregado.', ''); return; }
                            if (html.indexOf('id="dspace-external-resources"') === -1) {
                                html += '\n<div id="dspace-external-resources"><h3>Recursos externos</h3><ul></ul></div>';
                            }
                            html = html.replace(/(<div[^>]*id="dspace-external-resources"[^>]*>[\s\S]*?<ul[^>]*>)([\s\S]*?)(<\/ul>)/, function(m, a, b, c){
                                return a + b + '<li><a href="'+url+'" target="_blank" rel="noopener">'+(name||url)+'</a></li>' + c;
                            });
                            if (atto) { atto.innerHTML = html; }
                            var ta = attoWrapper.querySelector('textarea');
                            if (ta) { ta.value = html; }
                            showToast('Recurso agregado a la tarea.', '');
                            return;
                        }
                        // 3) Textareas comunes
                        var candidates = Array.from(document.querySelectorAll('textarea'))
                          .filter(function(el){
                              var id = (el.id||'').toLowerCase();
                              var name = (el.name||'').toLowerCase();
                              return /intro|description/.test(id+" "+name) || /\bintro\b/.test(name);
                          });
                        var target = candidates[0] || document.querySelector('textarea');
                        if (target) {
                            var val = target.value || '';
                            if (val.indexOf(url) !== -1) { showToast('Este recurso ya está agregado.', ''); return; }
                            var blockStart = '\n\nRecursos externos:\n';
                            var line = '- ' + (name||'Recurso') + ' — ' + url + '\n';
                            if (val.indexOf('Recursos externos:') === -1) {
                                target.value = (val ? (val+blockStart) : ('Recursos externos:\n')) + line;
                            } else {
                                target.value = val + line;
                            }
                            showToast('Recurso agregado a la tarea.', '');
                            return;
                        }
                        // 4) Fallback: copiar al portapapeles
                        await navigator.clipboard.writeText(url);
                        showToast('No se encontró el editor. Copiamos la URL al portapapeles.', 'error');
                    } catch(e){
                        showToast('No fue posible agregar automáticamente. Copiamos la URL al portapapeles.', 'error');
                        try { await navigator.clipboard.writeText(url); } catch(_){}
                    }
                };
            });
JS;
        $PAGE->requires->js_init_code($customjs);

        $dspaceApiUrl = get_config('block_dspace_integration', 'server');
        $username = get_config('block_dspace_integration', 'email');
        $password = get_config('block_dspace_integration', 'password');

        try {
            $token = $this->authenticateWithDSpace($dspaceApiUrl, $username, $password);
            if (!$token) {
                error_log("DSpace Error: Error de autenticación en DSpace para usuario $username");
                $this->content->text = "<p class='error-message'>❌ Error de autenticación en DSpace.</p>";
                return $this->content;
            }

            $communities = $this->getDSpaceCommunities($dspaceApiUrl, $token);
            if (!$communities || !isset($communities['_embedded']['communities'])) {
                error_log("DSpace Error: No se pudieron obtener comunidades desde $dspaceApiUrl");
                $this->content->text = "<p class='error-message'>⚠️ No se pudieron obtener comunidades.</p>";
                return $this->content;
            }

            $allItemsData = $this->makeGetRequest("$dspaceApiUrl/core/items?size=1000", $token);
            if ($allItemsData === null) {
                error_log("DSpace Error: Error al obtener items desde $dspaceApiUrl/core/items");
            }
            $allItems = $allItemsData['_embedded']['items'] ?? [];

            // Inyectamos CSS inline para evitar depender de $PAGE->requires->css_code() (no presente en algunas versiones)
            $this->content->text = "<style>" . $customcss . "</style>";
            $this->content->text .= "<div class='block_dspace_integration'>";
            $this->content->text .= "<h3>📚 Comunidades DSpace</h3>";
            $this->content->text .= "<ul class='list_community'>";

            foreach ($communities['_embedded']['communities'] as $community) {
                $communityName = htmlspecialchars($community['name'], ENT_QUOTES, 'UTF-8');
                $communityUuid = $community['uuid'];
                $this->content->text .= "<li class='toggle'><span class='arrow'>➤</span><span class='toggle'>$communityName</span><ul class='nested'>";

                try {
                    $collections = $this->getDSpaceCollectionsForCommunity($dspaceApiUrl, $token, $communityUuid);
                } catch (Exception $e) {
                    error_log("DSpace Error: Error al obtener colecciones para comunidad $communityUuid: " . $e->getMessage());
                    $collections = [];
                }

                if ($collections && isset($collections['_embedded']['collections'])) {
                    foreach ($collections['_embedded']['collections'] as $collection) {
                        $colName = htmlspecialchars($collection['name'], ENT_QUOTES, 'UTF-8');
                        $colUuid = $collection['uuid'];

                        $this->content->text .= "<li class='toggle'><span class='arrow'>➤</span><span class='toggle'>$colName</span>";

                        try {
                            $collectionItems = array_filter($allItems, function($item) use ($colUuid, $dspaceApiUrl, $token) {
                                if (!isset($item['_links']['owningCollection']['href'])) return false;
                                $owning = $this->makeGetRequest($item['_links']['owningCollection']['href'], $token);
                                return isset($owning['uuid']) && $owning['uuid'] === $colUuid;
                            });
                        } catch (Exception $e) {
                            error_log("DSpace Error: Error al filtrar items para colección $colUuid: " . $e->getMessage());
                            $collectionItems = [];
                        }

                        $this->content->text .= "<div class='nested' style='display:none; margin-top:15px; '>";
                        if (!empty($collectionItems)) {
                            $this->content->text .= "
                                <div class='dspace-table-wrap'>
                                <table class='table table-striped table-bordered dspace-table display' style='width:100%;'>
                                    <thead>
                                        <tr>
                                            <th class='dspace-col-item' style='width:220px; word-wrap:break-word;'>Item</th>
                                            <th class='dspace-col-bitstreams' style='width:360px; word-wrap:break-word;'>Bitstreams</th>
                                            <th class='dspace-col-preview' style='width:260px; word-wrap:break-word;'>Previsualizar</th>
                                            <th class='dspace-col-add' style='width:160px; word-wrap:break-word;'>Agregar a tarea</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            ";
                            foreach ($collectionItems as $item) {
                                $title = htmlspecialchars($item['metadata']['dc.title'][0]['value'] ?? 'Sin título', ENT_QUOTES, 'UTF-8');

                                $bitstreamHtml = '';
                                $previewHtml = '';
                                $addHtml = '';
                                if (isset($item['_links']['bundles']['href'])) {
                                    try {
                                        $bundlesData = $this->makeGetRequest($item['_links']['bundles']['href']."?size=1000", $token);
                                    } catch (Exception $e) {
                                        error_log("DSpace Error: Error al obtener bundles de item: " . $e->getMessage());
                                        $bundlesData = [];
                                    }

                                    $bundles = $bundlesData['_embedded']['bundles'] ?? [];
                                    foreach ($bundles as $bundle) {
                                        if (strtoupper($bundle['name']) !== 'ORIGINAL') continue;
                                        try {
                                            $bitstreamsData = $this->makeGetRequest($bundle['_links']['bitstreams']['href']."?size=1000", $token);
                                        } catch (Exception $e) {
                                            error_log("DSpace Error: Error al obtener bitstreams de bundle {$bundle['uuid']}: " . $e->getMessage());
                                            $bitstreamsData = [];
                                        }

                                        $bitstreams = $bitstreamsData['_embedded']['bitstreams'] ?? [];
                                        foreach ($bitstreams as $bitstream) {
                                            $bitUuid = $bitstream['uuid'];
                                            $bitName = htmlspecialchars($bitstream['name'] ?? 'Sin nombre', ENT_QUOTES, 'UTF-8');
                                            // URL de descarga directa desde DSpace REST (usar el mismo host configurado para descargas)
                                            $downloadUrl = "http://192.168.1.27:4000" . "/bitstreams/{$bitUuid}/download";
                                            $downloadBadge = "<a href='{$downloadUrl}' target='_blank' class='text-decoration-none'>{$bitName}</a>";
                                            // Checkbox + texto por fila, alineado al inicio
                                            $bitstreamHtml .= "<div class='dspace-bit-row'><input type='checkbox' name='bitstreams[]' value='{$bitUuid}'> <span>{$bitName}</span></div>";

                                            // Construcción de enlaces de previsualización por tipo
                                            $ext = strtolower(pathinfo($bitstream['name'] ?? '', PATHINFO_EXTENSION));
                                            $mime = strtolower($bitstream['mimeType'] ?? '');
                                            $safeUrl = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');

                                            if ($ext === 'epub' || $mime === 'application/epub+zip') {
                                                // Visor EPUB server-side (sin dependencia de epub.js)
                                                $localReader = new moodle_url('/blocks/dspace_integration/preview_epub.php', ['uuid' => $bitUuid]);
                                                $previewUrlEsc = htmlspecialchars($localReader->out(false), ENT_QUOTES, 'UTF-8');
                                                $previewHtml .= "<span class='dspace-preview-cell'><button type='button' class='btn btn-sm btn-primary' onclick=\"openPreviewWindow('{$previewUrlEsc}')\">EPUB</button></span><br>";
                                            } else if ($ext === 'zip' || $ext === 'scorm' || strpos($mime, 'zip') !== false) {
                                                // Previsualización SCORM (Opción A): visor ligero sin crear actividad
                                                $launchurl = new moodle_url('/blocks/dspace_integration/preview_scorm.php', ['uuid' => $bitUuid]);
                                                $launchurlEsc = htmlspecialchars($launchurl->out(false), ENT_QUOTES, 'UTF-8');
                                                $previewHtml .= "<span class='dspace-preview-cell'><button type='button' class='btn btn-sm btn-secondary' title='Vista previa sin calificaciones' onclick=\"openPreviewWindow('{$launchurlEsc}')\">SCORM</button></span><br>";
                                            } else {
                                                // Otros tipos: sin previsualización disponible
                                                $previewHtml .= "<span class='badge bg-light text-dark'>Sin vista previa</span><br>";
                                            }

                                            // Botón para agregar URL del bitstream a los detalles de la tarea
                                            $safeAddUrl = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');
                                            $safeNameJs = htmlspecialchars($bitName, ENT_QUOTES, 'UTF-8');
                                            $addHtml .= "<div class='dspace-bit-row'><button type='button' class='btn btn-sm btn-success' onclick=\"addToAssignmentDetails('{$safeAddUrl}', '{$safeNameJs}')\">Agregar</button></div>";
                                        }
                                    }
                                }

                                $this->content->text .= "
                                    <tr>
                                        <td class='dspace-col-item'><span class='badge bg-light text-dark'>{$title}</span></td>
                                        <td class='dspace-col-bitstreams' style='word-break: break-word; white-space: normal; overflow-wrap: break-word;'>
                                            <span class='badge bg-light text-dark dspace-bit-badge text-center'>{$bitstreamHtml}</span>
                                        </td>
                                        <td class='dspace-col-preview' style='word-break: break-word; white-space: normal; overflow-wrap: break-word;'>
                                            {$previewHtml}
                                        </td>
                                        <td class='dspace-col-add' style='word-break: break-word; white-space: normal; overflow-wrap: break-word;'>
                                            {$addHtml}
                                        </td>
                                    </tr>
                                ";
                            }

                            $this->content->text .= "</tbody></table></div>";
                        } else {
                            $this->content->text .= "<p>⚠️ No hay items en esta colección.</p>";
                        }

                        $this->content->text .= "</div>";
                        $this->content->text .= "</li>";
                    }
                } else {
                    $this->content->text .= "<li>⚠️ No hay colecciones en esta comunidad.</li>";
                    error_log("DSpace Error: Comunidad $communityUuid no tiene colecciones o no se pudo obtener.");
                }

                $this->content->text .= "</ul></li>";
            }

            $this->content->text .= "</ul></div>";

        } catch (Exception $e) {
            error_log("DSpace Error: Excepción al cargar datos: " . $e->getMessage());
            $this->content->text = "<p class='error-message'>❌ Se produjo un error al cargar los datos de DSpace.</p>";
        }

        return $this->content;
    }

    private function authenticateWithDSpace($apiUrl, $username, $password) {
        try {
            $ch = curl_init("$apiUrl/security/csrf");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $response = curl_exec($ch);
            if ($response === false) {
                error_log("DSpace Error: Error cURL CSRF: " . curl_error($ch));
            }
            preg_match('/DSPACE-XSRF-COOKIE=([^;]+)/', $response, $matches);
            $csrf_token = $matches[1] ?? '';
            curl_close($ch);

            $ch = curl_init("$apiUrl/authn/login");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "user=$username&password=$password");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "X-XSRF-TOKEN: $csrf_token",
                "Cookie: DSPACE-XSRF-COOKIE=$csrf_token",
                "Content-Type: application/x-www-form-urlencoded"
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                error_log("DSpace Error: Error cURL Login: " . curl_error($ch));
            }
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            curl_close($ch);

            preg_match('/Authorization:\s*Bearer\s+([^\s]+)/i', $header, $matches);
            if (empty($matches[1])) {
                error_log("DSpace Error: No se obtuvo token Bearer para usuario $username");
            }
            return $matches[1] ?? null;

        } catch (Exception $e) {
            error_log("DSpace Error: Excepción en authenticateWithDSpace: " . $e->getMessage());
            return null;
        }
    }

    private function getDSpaceCommunities($apiUrl, $token) {
        try {
            return $this->makeGetRequest("{$apiUrl}/core/communities?size=1000", $token);
        } catch (Exception $e) {
            error_log("DSpace Error: Error al obtener comunidades: " . $e->getMessage());
            return [];
        }
    }

    private function getDSpaceCollectionsForCommunity($apiUrl, $token, $communityId) {
        try {
            return $this->makeGetRequest("{$apiUrl}/core/communities/{$communityId}/collections?size=1000", $token);
        } catch (Exception $e) {
            error_log("DSpace Error: Error al obtener colecciones para comunidad $communityId: " . $e->getMessage());
            return [];
        }
    }

    private function makeGetRequest($url, $token) {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$token}",
                "Accept: application/json"
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                error_log("DSpace Error: Error cURL GET $url: " . curl_error($ch));
                curl_close($ch);
                return null;
            }
            curl_close($ch);
            return json_decode($response, true);
        } catch (Exception $e) {
            error_log("DSpace Error: Excepción makeGetRequest $url: " . $e->getMessage());
            return null;
        }
    }
}

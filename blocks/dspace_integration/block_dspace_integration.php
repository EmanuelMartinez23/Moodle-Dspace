<?php
class block_dspace_integration extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_dspace_integration');
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        $PAGE->requires->jquery();
        // $PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.1/css/dataTables.bootstrap5.min.css'));
        // $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/datatables.net@1.13.1/js/jquery.dataTables.min.js'), true);
        // $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.1/js/dataTables.bootstrap5.min.js'), true);

        // Estilos mínimos para evitar desbordes en tabla y buscador (inyectados inline para compatibilidad)
        $customcss = "
            .block_dspace_integration .dspace-table-wrap { width: 100%; overflow-x: auto; }
            .block_dspace_integration .dataTables_wrapper { width: 100%; overflow-x: auto; }
            /* Permitimos el wrap natural; el scroll aparece si el contenido supera el ancho */
            .block_dspace_integration table.dspace-table { table-layout: auto; }
            .block_dspace_integration table.dspace-table th, .block_dspace_integration table.dspace-table td { white-space: normal; vertical-align: middle; }
            .block_dspace_integration .dataTables_filter { float: right; }
            /* Alineación solicitada: Item y Bitstreams a la izquierda y centrado vertical */
            .block_dspace_integration .dspace-col-item,
            .block_dspace_integration .dspace-col-bitstreams { text-align: left; vertical-align: middle; }
            /* Previsualizar al centro (visual) */
            .block_dspace_integration .dspace-col-preview,
            .block_dspace_integration .dspace-col-add { text-align: center; vertical-align: middle; }
            .block_dspace_integration .dspace-bit-row { display: inline-flex; align-items: center; gap: 6px; margin: 2px 0; }
            .block_dspace_integration .dspace-bit-row input[type=checkbox] { margin: 0; }
            .block_dspace_integration .dspace-bit-badge { display: inline-flex; align-items: center; justify-content: flex-start; padding: 6px 8px; }
            .block_dspace_integration .dspace-preview-cell { display: inline-flex; flex-direction: column; align-items: center; gap: 6px; }
        ";

        $customjs = <<<'JS'
            $(document).ready(function() {
                if ($.fn && $.fn.DataTable) {
                  $('.dspace-table').DataTable({
                    pageLength: 5,
                    lengthMenu: [ [5, 10, 15, 25, 50], [5, 10, 15, 25, 50] ],
                    lengthChange: true,
                    searching: true,
                    ordering: true,
                    autoWidth: false,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.1/i18n/es-ES.json'
                    },
                    columnDefs: [
                        { width: '220px', targets: 0 },
                        { width: '360px', targets: 1 },
                        { width: '260px', targets: 2 },
                        { width: '160px', targets: 3 }
                    ]
                  });
                }

                // Funciones auxiliares de previsualización
                window.openPreviewWindow = function(url) {
                    window.open(url, '_blank', 'noopener');
                };
                window.previewScormNotice = function(url){
                    alert('Para previsualizar un paquete SCORM es necesario que el paquete esté desplegado en un servidor web. Intentaremos abrir el archivo, pero si no se muestra, contacte con el administrador.');
                    window.open(url, '_blank', 'noopener');
                };

                // Toast mínimo
                if (typeof window.showToast !== 'function') {
                    window.showToast = function(msg, type){
                        try {
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
                            if (type === 'error') { console.error(msg); } else { console.log(msg); }
                        }
                    };
                }

                // Agregar URL+Nombre de bitstream SIEMPRE a la descripción de la tarea (intro)
                // Mejora: reintentos automáticos si el editor (TinyMCE/Atto) aún no terminó de inicializarse
                window.addToAssignmentDetails = async function(url, name){
                    try {
                        if (!url) { showToast('URL inválida.', 'error'); return; }

                        // Inserta en modo HTML (TinyMCE/Atto)
                        function injectListHTML(html){
                            if ((html||'').indexOf(url) !== -1) { return {html: html, added: false, dup: true}; }
                            if ((html||'').indexOf('id="dspace-external-resources"') === -1) {
                                html = (html||'') + '\n<div id="dspace-external-resources"><h3>Recursos Externos</h3><ul style="list-style:none;padding-left:0;margin-left:0;"></ul></div>';
                            }
                            html = html.replace(/(<div[^>]*id=\"dspace-external-resources\"[^>]*>[\s\S]*?<ul[^>]*>)([\s\S]*?)(<\/ul>)/, function(m, a, b, c){
                                return a + b + '<li style="margin:4px 0; list-style-type: circle;"><a href="'+url+'" target="_blank" rel="noopener">'+(name||url)+'</a></li>' + c;
                            });
                            return {html: html, added: true, dup: false};
                        }

                        // Inserta en modo texto plano (textarea sin editor), evitando HTML "en seco" en la edición
                        function injectListText(text){
                            var current = String(text||'');
                            if (current.indexOf(url) !== -1) { return {text: current, added: false, dup: true}; }
                            var header = 'Recursos Externos';
                            var block = '';
                            if (current.indexOf(header) === -1) {
                                // Crear una nueva sección al final
                                block = (current.trim().length ? '\n\n' : '') + header + '\n\n';
                            }
                            // Agregar ítem en formato legible sin HTML
                            block += '* ' + (name || url) + ' - ' + url + '\n';
                            return {text: current + block, added: true, dup: false};
                        }

                        function matchesIntroIdName(el){
                            var id = (el && el.id || '').toLowerCase();
                            var nm = (el && el.name || '').toLowerCase();
                            return /(\b|_)intro(editor)?(\b|$)/.test(id) || /(\b|_)intro(editor)?(\b|$)/.test(nm);
                        }

                        // 1 y 2) Espera unificada hasta ~3s intentando TinyMCE y Atto antes de caer a textarea
                        var attempt = 0;
                        var maxAttempts = 15; // ~15 * 200ms = 3s
                        var foundAndInserted = false;
                        while (attempt < maxAttempts && !foundAndInserted) {
                            // TinyMCE
                            if (window.tinymce) {
                                var targetEditor = null;
                                if (tinymce.editors && tinymce.editors.length) {
                                    for (var i=0;i<tinymce.editors.length;i++){
                                        var ed = tinymce.editors[i];
                                        if (!ed) continue;
                                        var t = ed.targetElm;
                                        if (t && matchesIntroIdName(t)) { targetEditor = ed; break; }
                                    }
                                }
                                if (!targetEditor && tinymce.activeEditor && tinymce.activeEditor.targetElm && matchesIntroIdName(tinymce.activeEditor.targetElm)) {
                                    targetEditor = tinymce.activeEditor;
                                }
                                if (targetEditor) {
                                    var content = targetEditor.getContent({format:'html'}) || '';
                                    var res = injectListHTML(content);
                                    if (res.dup) { showToast('Este recurso ya está agregado.', ''); return; }
                                    try { targetEditor.focus(); } catch(_){ }
                                    targetEditor.setContent(res.html);
                                    if (typeof targetEditor.save === 'function') { try { targetEditor.save(); } catch(_){ } }
                                    var ta = targetEditor.targetElm;
                                    if (ta) {
                                        try { ta.dispatchEvent(new Event('input', {bubbles:true})); } catch(_){}
                                        try { ta.dispatchEvent(new Event('change', {bubbles:true})); } catch(_){}
                                    }
                                    showToast('Recurso agregado a la descripción de la tarea.', '');
                                    return;
                                }
                            }

                            // Atto
                            var introTextareaProbe = Array.from(document.querySelectorAll('textarea')).find(function(el){ return matchesIntroIdName(el); });
                            var attoWrapper = introTextareaProbe ? introTextareaProbe.closest('.editor_atto') : null;
                            var atto = attoWrapper ? attoWrapper.querySelector('.editor_atto_content') : null;
                            if (atto) {
                                var html = atto.innerHTML || '';
                                var res2 = injectListHTML(html);
                                if (res2.dup) { showToast('Este recurso ya está agregado.', ''); return; }
                                atto.innerHTML = res2.html;
                                var ta2 = attoWrapper.querySelector('textarea');
                                if (ta2) {
                                    ta2.value = atto.innerHTML;
                                    try { ta2.dispatchEvent(new Event('input', {bubbles:true})); } catch(_){}
                                    try { ta2.dispatchEvent(new Event('change', {bubbles:true})); } catch(_){}
                                }
                                showToast('Recurso agregado a la descripción de la tarea.', '');
                                return;
                            }

                            attempt++;
                            await new Promise(function(r){ setTimeout(r, 200); });
                        }

                        // 3) Fallback: textarea intro/introeditor en texto plano (sin HTML crudo) tras agotar la espera
                        var introTextarea = Array.from(document.querySelectorAll('textarea')).find(function(el){ return matchesIntroIdName(el); });
                        if (introTextarea) {
                            var val = introTextarea.value || '';
                            var res3 = injectListText(val);
                            if (res3.dup) { showToast('Este recurso ya está agregado.', ''); return; }
                            introTextarea.value = res3.text;
                            try { introTextarea.dispatchEvent(new Event('input', {bubbles:true})); } catch(_){}
                            try { introTextarea.dispatchEvent(new Event('change', {bubbles:true})); } catch(_){}
                            showToast('Recurso agregado a la descripción de la tarea.', '');
                            return;
                        }

                        // 4) Fallback: copiar al portapapeles
                        await navigator.clipboard.writeText(url);
                        showToast('No se encontró la descripción de la tarea. Copiamos la URL al portapapeles.', 'error');
                    } catch(e){
                        showToast('No fue posible agregar automáticamente. Copiamos la URL al portapapeles.', 'error');
                        try { await navigator.clipboard.writeText(url); } catch(_){}
                    }
                };

                // Delegación para botones Agregar: usan data-url y data-name
                document.addEventListener('click', async function(ev){
                    var btn = ev.target && ev.target.closest('.btn-dspace-add');
                    if (!btn) return;
                    // Evitar que cualquier comportamiento por defecto o burbujeo afecte el primer clic
                    try { ev.preventDefault(); } catch(_){}
                    try { ev.stopPropagation(); } catch(_){}
                    var url = btn.getAttribute('data-url') || '';
                    var name = btn.getAttribute('data-name') || '';
                    if (!url) return;
                    // Deshabilitar botón mientras se procesa para evitar dobles clics
                    var prevDisabled = btn.disabled;
                    var prevAriaBusy = btn.getAttribute('aria-busy');
                    var prevText = btn.innerHTML;
                    try {
                        btn.disabled = true;
                        btn.setAttribute('aria-busy', 'true');
                        // Opcional: feedback visual mínimo
                        btn.innerHTML = 'Agregando…';
                        await window.addToAssignmentDetails(url, name);
                    } finally {
                        btn.disabled = prevDisabled;
                        if (prevAriaBusy == null) { btn.removeAttribute('aria-busy'); } else { btn.setAttribute('aria-busy', prevAriaBusy); }
                        btn.innerHTML = prevText;
                    }
                });
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
                                            $bitstreamHtml .= "<div class='dspace-bit-row'><input type='checkbox' name='bitstreams[]' value='{$bitUuid}'> <span>{$bitName}</span></div><br>";

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

                                            // Botón para agregar URL del bitstream a los detalles de la tarea (delegación + data-attrs)
                                            $safeAddUrl = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');
                                            $safeNameJs = htmlspecialchars($bitName, ENT_QUOTES, 'UTF-8');
                                            $addHtml .= "<div class='dspace-bit-row'><button type='button' class='btn btn-sm btn-success btn-dspace-add' data-url='{$safeAddUrl}' data-name='{$safeNameJs}'>Agregar</button></div>";
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

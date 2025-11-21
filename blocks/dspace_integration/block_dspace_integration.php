
                        // 3) Fallback a textarea del campo intro/introeditor exclusivamente
                        if (introTextarea) {
                            var val = introTextarea.value || '';
                            if (val.indexOf(url) !== -1) { showToast('Este recurso ya está agregado.', ''); return; }
                            var blockStart = '\n\nRecursos externos:\n';
                            var line = '- ' + (name||'Recurso') + ' — ' + url + '\n';
                            if (val.indexOf('Recursos externos:') === -1) {
                                introTextarea.value = (val ? (val+blockStart) : ('Recursos externos:\n')) + line;
                            } else {
                                introTextarea.value = val + line;
                            }
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

                // Delegación para botones Agregar usando data-attrs
                document.addEventListener('click', function(ev){
                    var btn = ev.target && ev.target.closest('.btn-dspace-add');
                    if (!btn) return;
                    var url = btn.getAttribute('data-url') || '';
                    var name = btn.getAttribute('data-name') || '';
                    if (url) { window.addToAssignmentDetails(url, name); }
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

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

        // Estilos mínimos para evitar desbordes en tabla y buscador
        $customcss = "
            .block_dspace_integration .dspace-table-wrap { width: 100%; overflow-x: auto; }
            .block_dspace_integration .dataTables_wrapper { width: 100%; overflow-x: auto; }
            .block_dspace_integration table.dspace-table { white-space: nowrap; }
            .block_dspace_integration .dataTables_filter { float: right; }
        ";
        $PAGE->requires->css_code($customcss);

        $customjs = "
            \$(document).ready(function() {
                \$('.dspace-table').DataTable({
                    pageLength: 5,
                    lengthMenu: [ [5, 10, 15, 25, 50], [5, 10, 15, 25, 50] ],
                    lengthChange: true,
                    searching: true,
                    ordering: true,
                    autoWidth: false,
                    scrollX: true,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.1/i18n/es-ES.json'
                    },
                    columnDefs: [
                        { width: '220px', targets: 0 },
                        { width: '220px', targets: 1 },
                        { width: '180px', targets: 2 }
                    ]
                });

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
            });
        ";
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

            $this->content->text = "<div class='block_dspace_integration'>";
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
                                <table class='table table-striped table-bordered dspace-table display nowrap' style='width:100%;'>
                                    <thead>
                                        <tr>
                                            <th style='width:220px; word-wrap:break-word;'>Item</th>
                                            <th style='width:220px; word-wrap:break-word;'>Bitstreams</th>
                                            <th style='width:180px; word-wrap:break-word;'>Previsualizar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            ";
                            foreach ($collectionItems as $item) {
                                $title = htmlspecialchars($item['metadata']['dc.title'][0]['value'] ?? 'Sin título', ENT_QUOTES, 'UTF-8');

                                $bitstreamHtml = '';
                                $previewHtml = '';
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
                                            $downloadUrl = "http://192.168.135.5:4000" . "/bitstreams/{$bitUuid}/download";
                                            $downloadBadge = "<a href='{$downloadUrl}' target='_blank' class='text-decoration-none'>{$bitName}</a>";
				    // for download
				    $bitstreamHtml .= "<label><input type='checkbox' name='bitstreams[]' value='{$bitUuid}'> {$bitName}</label> <br>";
				    //$bitstreamHtml .= "[{$downloadBadge}] <label> {$bitName}</label> <br>";

                                            // Construcción de enlaces de previsualización por tipo
                                            $ext = strtolower(pathinfo($bitstream['name'] ?? '', PATHINFO_EXTENSION));
                                            $mime = strtolower($bitstream['mimeType'] ?? '');
                                            $safeUrl = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');

                                            if ($ext === 'epub' || $mime === 'application/epub+zip') {
                                                // Usamos un visor EPUB local para evitar bloqueos CORS del lector público
                                                $localReader = new moodle_url('/blocks/dspace_integration/epub_reader.php', ['uuid' => $bitUuid]);
                                                $previewUrlEsc = htmlspecialchars($localReader->out(false), ENT_QUOTES, 'UTF-8');
                                                $previewHtml .= "<button type='button' class='btn btn-sm btn-primary' onclick=\"openPreviewWindow('{$previewUrlEsc}')\">EPUB</button> <span class='text-muted small'>{$bitName}</span><br>";
                                            } else if ($ext === 'zip' || $ext === 'scorm' || strpos($mime, 'zip') !== false) {
                                                // Previsualización SCORM (Opción A): visor ligero sin crear actividad
                                                $launchurl = new moodle_url('/blocks/dspace_integration/preview_scorm.php', ['uuid' => $bitUuid]);
                                                $launchurlEsc = htmlspecialchars($launchurl->out(false), ENT_QUOTES, 'UTF-8');
                                                $previewHtml .= "<button type='button' class='btn btn-sm btn-secondary' title='Vista previa sin calificaciones' onclick=\"openPreviewWindow('{$launchurlEsc}')\">SCORM</button> <span class='text-muted small'>{$bitName}</span><br>";
                                            } else {
                                                // Otros tipos: sin previsualización disponible
                                                $previewHtml .= "<span class='badge bg-light text-dark'>Sin vista previa</span> <span class='text-muted small'>{$bitName}</span><br>";
                                            }
                                        }
                                    }
                                }

                                $this->content->text .= "
                                    <tr>
					<td>{$title}</td>
                                          <td style='word-break: break-word; white-space: normal; overflow-wrap: break-word;'>
					    {$bitstreamHtml}
					</td>
                                          <td style='word-break: break-word; white-space: normal; overflow-wrap: break-word;'>
                                                {$previewHtml}
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

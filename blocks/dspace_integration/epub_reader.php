<?php
// blocks/dspace_integration/epub_reader.php
require_once(__DIR__ . '/../../config.php');
require_login();

global $PAGE, $OUTPUT;

$uuid = optional_param('uuid', '', PARAM_ALPHANUMEXT);
if (empty($uuid)) {
    print_error('missingparam', 'error', '', 'uuid');
}

$PAGE->set_url(new moodle_url('/blocks/dspace_integration/epub_reader.php', ['uuid' => $uuid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Visor EPUB');
$PAGE->set_pagelayout('popup');

echo $OUTPUT->header();
?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Visor EPUB - Moodle</title>
        <style>
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
                overflow: hidden;
                background: #f8f9fa;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }

            #viewer-container {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                display: flex;
                flex-direction: column;
                background: white;
            }

            #toolbar {
                padding: 12px 16px;
                background: #fff;
                border-bottom: 1px solid #dee2e6;
                display: flex;
                align-items: center;
                gap: 12px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                z-index: 100;
            }

            #area {
                flex: 1;
                position: relative;
                overflow: hidden;
            }

            #viewer {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: white;
            }

            .btn {
                padding: 8px 16px;
                border: 1px solid #ced4da;
                background: #fff;
                cursor: pointer;
                border-radius: 4px;
                font-size: 14px;
                transition: all 0.2s;
            }

            .btn:hover {
                background: #e9ecef;
                border-color: #adb5bd;
            }

            .btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            #toc {
                max-width: 300px;
                padding: 6px 12px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                background: white;
            }

            #status {
                margin-left: auto;
                color: #6c757d;
                font-size: 0.9em;
                padding: 6px 12px;
            }

            #loading {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100%;
                flex-direction: column;
                gap: 16px;
            }

            .spinner {
                border: 3px solid #f3f3f3;
                border-top: 3px solid #007bff;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            .error-message {
                color: #dc3545;
                text-align: center;
                padding: 20px;
            }

            @media (max-width: 768px) {
                #toc {
                    max-width: 200px;
                    font-size: 12px;
                }
                .btn {
                    padding: 6px 12px;
                    font-size: 12px;
                }
            }
        </style>
    </head>
    <body>
    <div id="viewer-container">
        <div id="toolbar">
            <button id="prev" class="btn" title="Página anterior" disabled>◀ Anterior</button>
            <button id="next" class="btn" title="Página siguiente" disabled>▶ Siguiente</button>
            <select id="toc" class="btn" disabled>
                <option value="">Navegar a...</option>
            </select>
            <span id="status">Cargando EPUB…</span>
        </div>
        <div id="area">
            <div id="loading">
                <div class="spinner"></div>
                <div>Cargando contenido EPUB...</div>
            </div>
            <div id="viewer" style="display: none;"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.88/dist/epub.min.js"></script>
    <script>
        (function(){
            const uuid = '<?php echo $uuid; ?>';
            let book, rendition;

            // Elementos DOM
            const viewer = document.getElementById('viewer');
            const loading = document.getElementById('loading');
            const status = document.getElementById('status');
            const prevBtn = document.getElementById('prev');
            const nextBtn = document.getElementById('next');
            const tocSelect = document.getElementById('toc');

            // Función para mostrar error
            function showError(message) {
                loading.innerHTML = `<div class="error-message">
                    <strong>Error:</strong> ${message}
                    <br><br>
                    <button class="btn" onclick="location.reload()">Reintentar</button>
                </div>`;
                status.textContent = 'Error';
            }

            // Función para habilitar controles
            function enableControls() {
                prevBtn.disabled = false;
                nextBtn.disabled = false;
                tocSelect.disabled = false;
            }

            // Cargar el EPUB
            async function loadEPUB() {
                try {
                    status.textContent = 'Obteniendo archivo EPUB...';

                    // Obtener URL del archivo temporal
                    const response = await fetch(`proxy_bitstream.php?uuid=${uuid}&action=view`);

                    if (!response.ok) {
                        throw new Error(`Error ${response.status}: ${response.statusText}`);
                    }

                    const data = await response.json();

                    if (!data.url) {
                        throw new Error('No se pudo obtener la URL del EPUB');
                    }

                    status.textContent = 'Cargando EPUB...';

                    // Inicializar libro EPUB
                    book = ePub(data.url);

                    // Configurar renderizado
                    rendition = book.renderTo("viewer", {
                        width: "100%",
                        height: "100%",
                        spread: "auto",
                        flow: "paginated"
                    });

                    // Mostrar primera página
                    await rendition.display();

                    // Ocultar loading y mostrar viewer
                    loading.style.display = 'none';
                    viewer.style.display = 'block';

                    // Habilitar controles
                    enableControls();
                    status.textContent = 'EPUB cargado';

                    // Cargar tabla de contenidos
                    book.loaded.navigation.then(function(nav){
                        const toc = nav.toc || [];

                        if (toc.length > 0) {
                            tocSelect.innerHTML = '<option value="">Navegar a...</option>';
                            toc.forEach(function(chapter){
                                const option = document.createElement('option');
                                option.textContent = chapter.label;
                                option.value = chapter.href;
                                tocSelect.appendChild(option);
                            });
                        } else {
                            tocSelect.innerHTML = '<option value="">Sin tabla de contenidos</option>';
                        }
                    }).catch(function(err){
                        console.warn('Error loading TOC:', err);
                        tocSelect.innerHTML = '<option value="">Error cargando índice</option>';
                    });

                    // Manejar eventos de navegación
                    rendition.on('relocated', function(location){
                        // Actualizar estado de botones si es necesario
                    });

                    // Manejar errores del libro
                    book.on('openFailed', function(error){
                        console.error('Error opening EPUB:', error);
                        showError('Error al abrir el archivo EPUB');
                    });

                } catch (error) {
                    console.error('Error loading EPUB:', error);
                    showError(error.message);
                    status.textContent = 'Error al cargar';
                }
            }

            // Event listeners para controles
            prevBtn.addEventListener('click', function() {
                if (rendition) {
                    rendition.prev();
                }
            });

            nextBtn.addEventListener('click', function() {
                if (rendition) {
                    rendition.next();
                }
            });

            tocSelect.addEventListener('change', function() {
                const href = this.value;
                if (href && rendition) {
                    rendition.display(href);
                }
            });

            // Iniciar carga cuando el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', loadEPUB);
            } else {
                loadEPUB();
            }

        })();
    </script>
    </body>
    </html>
<?php
echo $OUTPUT->footer();
?>
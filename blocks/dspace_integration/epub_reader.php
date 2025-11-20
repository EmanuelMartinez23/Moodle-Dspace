<?php
// Página simple de visor EPUB basado en epub.js, cargando el libro vía proxy local.

require_once(__DIR__ . '/../../config.php');

require_login();

global $PAGE, $OUTPUT;

$uuid = optional_param('uuid', '', PARAM_ALPHANUMEXT);
if (empty($uuid)) {
    print_error('missingparam', 'error', '', 'uuid');
}

$bookurl = new moodle_url('/blocks/dspace_integration/proxy_bitstream.php', ['uuid' => $uuid]);

$PAGE->set_url(new moodle_url('/blocks/dspace_integration/epub_reader.php', ['uuid' => $uuid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Visor EPUB');
$PAGE->set_pagelayout('popup');

echo $OUTPUT->header();
?>
<style>
    html, body {
        height: 100%;
        margin: 0;
        overflow: hidden;
        background: #f8f9fa;
    }
    #viewer-container {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        display: flex;
        flex-direction: column;
    }
    #toolbar {
        padding: 8px 12px;
        background: #fff;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #area {
        flex: 1;
        position: relative;
    }
    #viewer {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
    }
    .btn { padding: 6px 10px; border: 1px solid #ccc; background: #fff; cursor: pointer; }
    .btn:hover { background: #f1f1f1; }
    #toc { max-width: 40%; }
    #status { margin-left: auto; color: #6c757d; font-size: 0.9em; }
    @media (max-width: 768px) {
        #toc { display:none; }
    }
  </style>
  <div id="viewer-container">
    <div id="toolbar">
      <button id="prev" class="btn" title="Página anterior">◀</button>
      <button id="next" class="btn" title="Página siguiente">▶</button>
      <select id="toc" class="btn"></select>
      <span id="status">Cargando EPUB…</span>
    </div>
    <div id="area">
      <div id="viewer"></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3/dist/epub.min.js"></script>
  <script>
    (function(){
      const bookUrl = <?php echo json_encode($bookurl->out(false)); ?>;

      // Inicializar libro y render en el contenedor
      const book = ePub(bookUrl);
      const rendition = book.renderTo("viewer", { width: "100%", height: "100%" });
      rendition.display();

      // Controles navegación
      document.getElementById('prev').addEventListener('click', () => rendition.prev());
      document.getElementById('next').addEventListener('click', () => rendition.next());

      // Tabla de contenidos
      book.loaded.navigation.then(function(toc){
        const select = document.getElementById('toc');
        select.innerHTML = '';
        (toc.toc || []).forEach(function(ch){
          const opt = document.createElement('option');
          opt.textContent = ch.label;
          opt.value = ch.href;
          select.appendChild(opt);
        });
        select.addEventListener('change', function(){
          const href = this.value;
          if (href) book.rendition.display(href);
        });
      });

      // Estado
      const status = document.getElementById('status');
      book.ready.then(() => { status.textContent = 'Listo'; });
      book.on('openFailed', (err) => { status.textContent = 'Error al abrir EPUB'; console.error(err); });
    })();
  </script>
<?php
echo $OUTPUT->footer();

<?php
require_once('../../config.php');
require_once($CFG->libdir . '/accesslib.php');
// Verifica que el usuario esté logueado
require_login();

// Define el contexto y los permisos necesarios
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Define la URL de la página
$PAGE->set_url(new moodle_url('/blocks/dspace_integration/view.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'block_dspace_integration'));


// Incluye los estilos CSS del bloque
$PAGE->requires->css(new moodle_url('/blocks/dspace_integration/styles/styles.css'));

// Muestra el encabezado
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'block_dspace_integration'));

// Código del bloque para mostrar contenido
try {
    // URL y credenciales de la API REST de DSpace
    $dspaceApiUrl = 'http://192.168.100.200:8080/rest';
    $username = 'developeresteban8@gmail.com';
    $password = '12345';


    $token = authenticateWithDSpace($dspaceApiUrl, $username, $password);

    if ($token) {

        $communities = getDSpaceCommunities($dspaceApiUrl, $token);

        if ($communities) {
            echo "<div class='block_dspace_integration'>";
            echo "<h3>Comunidades en DSpace</h3>";
            echo "<ul>";
            foreach ($communities as $community) {
                echo "<li><strong>" . htmlspecialchars($community['name']) . "</strong>";

                $collections = getDSpaceCollectionsForCommunity($dspaceApiUrl, $token, $community['uuid']);

                if ($collections) {
                    echo "<ul>";
                    foreach ($collections as $collection) {
                        echo "<li>" . htmlspecialchars($collection['name']);

                        $items = getDSpaceItemsForCollection($dspaceApiUrl, $token, $collection['uuid']);

                        if ($items) {
                            echo "<ul>";
                            foreach ($items as $item) {
                                echo "<li>" . htmlspecialchars($item['name']) . "</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo "<ul><li>No hay ítems disponibles en esta colección.</li></ul>";
                        }

                        echo "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<ul><li>No se encontraron colecciones en esta comunidad.</li></ul>";
                }
                echo "</li>";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<p class='error-message'>No se pudieron obtener las comunidades de DSpace.</p>";
        }
    } else {
        echo "<p class='error-message'>Error de autenticación: Verifique sus credenciales.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error-message'>Ocurrió un error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo $OUTPUT->footer();

function authenticateWithDSpace($apiUrl, $username, $password) {
    $ch = curl_init("{$apiUrl}/login");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "email={$username}&password={$password}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Error de cURL: ' . curl_error($ch));
        curl_close($ch);
        throw new Exception('Error de cURL: ' . curl_error($ch));
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    preg_match("/^Set-Cookie:\s*JSESSIONID=([^;]+)/mi", $header, $matches);
    $token = $matches[1] ?? null;

    curl_close($ch);
    return $token;
}

function getDSpaceCommunities($apiUrl, $token) {
    return makeGetRequest("{$apiUrl}/communities", $token);
}

function getDSpaceCollectionsForCommunity($apiUrl, $token, $communityId) {
    return makeGetRequest("{$apiUrl}/communities/{$communityId}/collections", $token);
}

function getDSpaceItemsForCollection($apiUrl, $token, $collectionId) {
    return makeGetRequest("{$apiUrl}/collections/{$collectionId}/items", $token);
}

function makeGetRequest($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Cookie: JSESSIONID={$token}"
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Error de cURL: ' . curl_error($ch));
        curl_close($ch);
        throw new Exception('Error de cURL: ' . curl_error($ch));
    }

    $data = json_decode($response, true);
    curl_close($ch);
    return $data;
}

<?php
defined('MOODLE_INTERNAL') || die();

class dspace_client {
    private $server;
    private $email;
    private $password;
    private $token;
    private $csrf;
    private $cookieFile;

    public function __construct($server, $email, $password) {
        $this->server = rtrim($server, '/');
        $this->email = $email;
        $this->password = $password;
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'dspace_cookie');
        try {
            $this->authenticate();
        } catch (Exception $e) {
            error_log('DSpace Error (Auth): ' . $e->getMessage());
            throw $e; 
        }
    }

    /**
     * Autenticación para DSpace 9 
     */
    private function authenticate() {
        // Archivo temporal para almacenar cookies
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'dspace_cookie');

        //Obtener CSRF token y cookie
        $ch = curl_init("{$this->server}/security/csrf");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); 
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        $response = curl_exec($ch);
        if ($response === false) {
            $msg = curl_error($ch);
            error_log("DSpace Error (CSRF request): $msg");
            throw new moodle_exception("❌ Error al conectar con DSpace /security/csrf: $msg");
        }

        // Extraemos el CSRF token de la cookie
        preg_match('/DSPACE-XSRF-COOKIE=([^;]+)/', $response, $matches);
        $this->csrf = $matches[1] ?? '';
        if (empty($this->csrf)) {
            error_log("DSpace Error: No se pudo obtener CSRF token");
            throw new moodle_exception("❌ No se pudo obtener CSRF token de DSpace.");
        }

        // Login con el token y cookie
        $ch = curl_init("{$this->server}/authn/login");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "user={$this->email}&password={$this->password}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-XSRF-TOKEN: {$this->csrf}",
            "Cookie: DSPACE-XSRF-COOKIE={$this->csrf}",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $msg = curl_error($ch);
            error_log("DSpace Error (Login request): $msg");
            throw new moodle_exception("❌ Error al hacer login en DSpace: $msg");
        }

        // Cpnseguimos el Bearer token del header
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        curl_close($ch);

        preg_match('/Authorization:\s*Bearer\s+([^\s]+)/i', $header, $matches);
        $this->token = $matches[1] ?? null;

        if (empty($this->token)) {
            error_log("DSpace Error: No se obtuvo Bearer token");
            throw new moodle_exception("❌ Error al autenticar en DSpace: no se obtuvo Bearer token.");
        }
    }


    /**
     * Request genérico  para peticiones(GET / POST / PUT / DELETE)
     */
    private function request($method, $endpoint, $data = null, $extraHeaders = []) {
        $url = (strpos($endpoint, 'http') === 0) ? $endpoint : "{$this->server}/$endpoint";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);

        $headers = [
            "Authorization: Bearer {$this->token}",
            "X-XSRF-TOKEN: {$this->csrf}",
            "Cookie: DSPACE-XSRF-COOKIE={$this->csrf}",
            "Accept: application/json",
            "Content-Type: application/json"
        ];


        if ($data !== null) {
            if (is_string($data)) {
                $headers[] = "Content-Type: application/json";
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }

        if (!empty($extraHeaders)) {
            $headers = array_merge($headers, $extraHeaders);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            "code" => $http_code,
            "body" => $response,
            "json" => json_decode($response, true)
        ];
    }

    /**
     * Funcionar para refrescar el Token
     * */
    private function refresh_csrf() {
        $ch = curl_init("{$this->server}/security/csrf");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        $response = curl_exec($ch);
        preg_match('/DSPACE-XSRF-COOKIE=([^;]+)/', $response, $matches);
        $this->csrf = $matches[1] ?? '';
    }


    /**
     * Crear WorkspaceItem
     */
    public function create_item($collection_id, $metadata) {
        $data = json_encode(["metadata" => $metadata]);
        $this->refresh_csrf();
        $res = $this->request(
            "POST",
            "submission/workspaceitems?owningCollection=$collection_id",
            $data,
            [
                "X-XSRF-TOKEN: {$this->csrf}",
                "Cookie: DSPACE-XSRF-COOKIE={$this->csrf}",
                "Content-Type: application/json"
            ]
        );

        if ($res['code'] !== 201) {
            throw new moodle_exception("❌ Error al crear WorkspaceItem: " . $res['body']);
        }

        $json = $res['json'];
        if (!isset($json['id'])) {
            throw new moodle_exception("❌ No se pudo obtener el ID del WorkspaceItem.");
        }
        // Retornamos el ID del WorkspaceItem
        return $json['id'];
    }



    /**
     * Subir Bitstream (archivo) a Bundle Original
     */
    public function upload_bitstream($original_bundle_uuid, $filepath, $filename) {
        $this->refresh_csrf(); 

        // Subimos el archivo al bundle ORIGINAL
        $postFields = [
            'file' => new CURLFile($filepath, mime_content_type($filepath), $filename),
            'properties' => json_encode(['name' => $filename])
        ];

        $headers = [
            "Authorization: Bearer {$this->token}",
            "X-XSRF-TOKEN: {$this->csrf}"
        ];

        $url = "{$this->server}/core/bundles/{$original_bundle_uuid}/bitstreams";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = [
            "code" => $http_code,
            "body" => $response,
            "json" => json_decode($response, true)
        ];

        if ($res['code'] !== 201) {
            throw new moodle_exception("❌ Error al subir bitstream: " . $res['body']);
        }

        return $res['json'];
    }

    /** Obtener item_id desde workspace */
    public function get_item_from_workspace($workspace_id) {
        // print_r($workspace_id);
        $res = $this->request("GET", "submission/workspaceitems/$workspace_id/item");
    
        return $res['json']['id'] ?? null;
    }

    /** Obtener bundle ORIGINAL de un item */
    public function get_original_bundle($item_id) {
        $res = $this->request("GET", "core/items/$item_id/bundles");
        if (!isset($res['json']['_embedded']['bundles'])) {
            return null;
        }
        foreach ($res['json']['_embedded']['bundles'] as $bundle) {
            if ($bundle['name'] === 'ORIGINAL') {
                return $bundle['uuid'];
            }
        }
        return null;
    }

    /**
     * Crear bundle ORIGINAL en un item
     */
    public function create_original_bundle($item_id) {
        $this->refresh_csrf();

        $data = json_encode(["name" => "ORIGINAL"]);

        $res = $this->request(
            "POST",
            "core/items/$item_id/bundles",
            $data,
            [
                "X-XSRF-TOKEN: {$this->csrf}",
                "Cookie: DSPACE-XSRF-COOKIE={$this->csrf}",
                "Content-Type: application/json"
            ]
        );

        if ($res['code'] !== 201) {
            throw new moodle_exception("❌ Error al crear bundle ORIGINAL: " . $res['body']);
        }

        return $res['json']['uuid'] ?? null;
    }

    /**
     * Crear un item archivado directamente (sin workflow ni workspace)
     */
    public function create_archived_item($collection_id, $metadata) {
        $this->refresh_csrf();

        $data = json_encode([
            "name" => $metadata['dc.title'][0]['value'] ?? "Nuevo Item",
            "metadata" => $metadata,
            "inArchive" => true,
            "discoverable" => true,
            "withdrawn" => false,
            "type" => "item"
        ]);

        $res = $this->request(
            "POST",
            "core/items?owningCollection={$collection_id}",
            $data,
            [
                "X-XSRF-TOKEN: {$this->csrf}",
                "Cookie: DSPACE-XSRF-COOKIE={$this->csrf}",
                "Content-Type: application/json"
            ]
        );

        if ($res['code'] !== 201) {
            throw new moodle_exception("❌ Error al crear item archivado: " . $res['body']);
        }

        return $res['json']['uuid'] ?? null;
    }




    /** Comunidades */
    public function get_communities() {
        $res = $this->request("GET", "core/communities?size=1000");
        return $res['json']['_embedded']['communities'] ?? [];
    }

    /** Colecciones de comunidad */
    public function get_collections($community_id) {
        $res = $this->request("GET", "core/communities/$community_id/collections?size=1000");
        return $res['json']['_embedded']['collections'] ?? [];
    }

    /** Items de colección */
    public function get_items($collection_id) {
        $res = $this->request("GET", "core/collections/$collection_id/items?size=1000");
        return $res['json']['_embedded']['items'] ?? [];
    }

    /** Bitstreams de item */
    public function get_bitstreams($item_id) {
        $res = $this->request("GET", "core/items/$item_id/bitstreams?size=1000");
        return $res['json']['_embedded']['bitstreams'] ?? [];
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

/**
 * Fredki client class.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fredki_client {
    /** @var string FredKI base URL. */
    protected $baseurl;

    /** @var string FredKI workspace. */
    protected $workspace;

    /** @var string FredKI username. */
    protected $username;

    /** @var string FredKI password. */
    protected $password;

    /** @var string Login path of the FredKI API. */
    protected $loginpath;

    /** @var bool Whether debug logging is enabled. */
    protected $debuglog;

    /** @var string|null Cached access token. */
    protected $token = null;

    /**
     * Constructor.
     *
     * @param string $baseurl The FredKI base URL.
     * @param string $workspace The FredKI workspace.
     * @param string $username The FredKI username.
     * @param string $password The FredKI password.
     * @param string $loginpath The login path.
     * @param bool $debuglog Whether debug logging is enabled.
     */
    public function __construct(
        string $baseurl,
        string $workspace,
        string $username,
        string $password,
        string $loginpath = '/admin/api/user/login',
        bool $debuglog = false
    ) {
        $baseurl = rtrim($baseurl, '/');
        $parts = parse_url($baseurl);
        if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https' || empty($parts['host'])) {
            throw new \moodle_exception('invalidbaseurl', 'mod_fredkicourseimport');
        }
        $this->baseurl = $baseurl;
        $this->workspace = $workspace ?: 'default';
        $this->username = $username;
        $this->password = $password;
        $this->loginpath = $loginpath ?: '/admin/api/user/login';
        $this->debuglog = $debuglog;
    }

    /**
     * Log debug.
     *
     * @param string $message The message.
     */
    protected function log_debug(string $message): void {
        global $CFG;
        if ($this->debuglog && $CFG->debug >= DEBUG_DEVELOPER) {
            debugging('[mod_fredkicourseimport] ' . $message, DEBUG_DEVELOPER);
        }
    }

    /**
     * Set token.
     *
     * @param string $token The access token.
     */
    public function set_token(string $token): void {
        $this->token = $token;
    }

    /**
     * Login.
     *
     * @return string The result.
     */
    public function login(): string {
        if (!empty($this->token)) {
            return $this->token;
        }
        $url = $this->baseurl . $this->loginpath;
        $payload = ['username' => $this->username, 'password' => $this->password];
        $response = $this->request_json('POST', $url, $payload, false);
        if (empty($response['data']['token'])) {
            throw new \moodle_exception('externalapiinvalidresponse', 'mod_fredkicourseimport');
        }
        $this->token = $response['data']['token'];
        return $this->token;
    }

    /**
     * List knowledge bases.
     *
     * @return array The result.
     */
    public function list_knowledge_bases(): array {
        $this->login();
        $url = $this->baseurl . '/admin/api/workspace/' . rawurlencode($this->workspace) . '/knowledge';
        $response = $this->request_json('GET', $url, null, true);

        $items = $response['data'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? $item['knowledge_id'] ?? $item['knowledgeId'] ?? $item['uuid'] ?? null;
            $name = $item['name'] ?? $item['title'] ?? $item['knowledge_name'] ?? $item['knowledgeName'] ?? null;

            if (empty($id) || empty($name)) {
                continue;
            }

            $result[] = [
                'id' => (string)$id,
                'name' => (string)$name,
            ];
        }

        return $result;
    }

    /**
     * List agents.
     *
     * @return array The result.
     */
    public function list_agents(): array {
        $this->login();

        $paths = [
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/1/100',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/applications',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/agent',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/agents',
            '/admin/api/application/workspace/' . rawurlencode($this->workspace),
        ];

        $lasterror = null;
        foreach ($paths as $path) {
            try {
                $url = $this->baseurl . $path;
                $response = $this->request_json('GET', $url, null, true);
                $items = $this->extract_list_items($response);
                $result = $this->normalise_agent_items($items);
                if (!empty($result)) {
                    return $result;
                }
            } catch (\Throwable $e) {
                $lasterror = $e;
                continue;
            }
        }

        if ($lasterror) {
            $this->log_debug('FredKI list_agents fallback failed: ' . get_class($lasterror));
        }
        return [];
    }

    /**
     * Get agent embed code.
     *
     * @param string $agentid The agentid.
     * @return string The result.
     */
    public function get_agent_embed_code(string $agentid): string {
        $agentid = trim($agentid);
        if ($agentid === '') {
            return '';
        }
        $this->login();

        $detail = $this->get_agent_detail_for_embed($agentid);
        if (!empty($detail)) {
            $code = $this->build_embed_code_from_agent_item($detail);
            if ($code !== '') {
                return $code;
            }
        }

        $access = $this->get_agent_public_access_for_embed($agentid);
        if (!empty($access)) {
            $code = $this->build_embed_code_from_agent_item($access);
            if ($code !== '') {
                return $code;
            }
        }

        foreach ($this->list_agents() as $agent) {
            if ((string)($agent['id'] ?? '') === $agentid && !empty($agent['embedcode'])) {
                return (string)$agent['embedcode'];
            }
        }

        return '';
    }

    /**
     * Extract list items.
     *
     * @param array $response The response.
     * @return array The result.
     */
    protected function extract_list_items(array $response): array {
        $candidates = [
            $response['data'] ?? null,
            $response['data']['items'] ?? null,
            $response['data']['list'] ?? null,
            $response['data']['records'] ?? null,
            $response['items'] ?? null,
            $response['list'] ?? null,
            $response['records'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }
        return [];
    }

    /**
     * Normalise agent items.
     *
     * @param array $items The items.
     * @return array The result.
     */
    protected function normalise_agent_items(array $items): array {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? $item['application_id'] ?? $item['applicationId'] ?? $item['agent_id']
                ?? $item['agentId'] ?? $item['uuid'] ?? null;
            $name = $item['name'] ?? $item['title'] ?? $item['app_name'] ?? $item['appName'] ?? $item['agent_name']
                ?? $item['agentName'] ?? null;

            if (empty($id)) {
                continue;
            }
            if (empty($name)) {
                $name = $id;
            }

            $type = $item['type'] ?? $item['app_type'] ?? $item['appType'] ?? $item['mode'] ?? '';
            if ($type !== '') {
                $name .= ' (' . $type . ')';
            }

            $merged = $item;
            $embedcode = $this->build_embed_code_from_agent_item($merged);
            if ($embedcode === '') {
                $detail = $this->get_agent_detail_for_embed((string)$id);
                if (!empty($detail)) {
                    $merged = array_merge($merged, $detail);
                    $embedcode = $this->build_embed_code_from_agent_item($merged);
                }
            }

            if ($embedcode === '') {
                $access = $this->get_agent_public_access_for_embed((string)$id);
                if (!empty($access)) {
                    $merged = array_merge($merged, $access);
                    $embedcode = $this->build_embed_code_from_agent_item($merged);
                }
            }

            $result[] = [
                'id' => (string)$id,
                'name' => (string)$name,
                'embedcode' => $embedcode,
                'chaturl' => $this->build_chat_url_from_agent_item($merged),
            ];
        }

        usort($result, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Get agent detail for embed.
     *
     * @param string $agentid The agentid.
     * @return array The result.
     */
    protected function get_agent_detail_for_embed(string $agentid): array {
        if ($agentid === '') {
            return [];
        }

        $paths = [
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' . rawurlencode($agentid),
            '/admin/api/application/' . rawurlencode($agentid),
        ];

        foreach ($paths as $path) {
            try {
                $response = $this->request_json('GET', $this->baseurl . $path, null, true);
                $data = $response['data'] ?? $response;
                return is_array($data) ? $data : [];
            } catch (\Throwable $e) {
                $this->log_debug('FredKI agent detail failed: ' . get_class($e));
                continue;
            }
        }

        return [];
    }

    /**
     * Try known FredKI/MaxKB public access endpoints. Some installations do not expose
     * the public chat token in the normal application list/detail response.
     *
     * @param string $agentid FredKI agent id.
     * @return array
     */
    protected function get_agent_public_access_for_embed(string $agentid): array {
        if ($agentid === '') {
            return [];
        }

        $paths = [
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' . rawurlencode($agentid) . '/access_token',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' . rawurlencode($agentid) . '/access-token',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' .
                rawurlencode($agentid) . '/access_token/1/100',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' .
                rawurlencode($agentid) . '/access-token/1/100',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' . rawurlencode($agentid) . '/public',
            '/admin/api/workspace/' . rawurlencode($this->workspace) . '/application/' . rawurlencode($agentid) . '/profile',
            '/admin/api/application/' . rawurlencode($agentid) . '/access_token',
            '/admin/api/application/' . rawurlencode($agentid) . '/access-token',
        ];

        foreach ($paths as $path) {
            try {
                $response = $this->request_json('GET', $this->baseurl . $path, null, true);
                $data = $response['data'] ?? $response;
                if (!is_array($data)) {
                    continue;
                }

                $items = $this->extract_list_items($response);
                if (!empty($items)) {
                    foreach ($items as $item) {
                        if (is_array($item) && $this->extract_embed_token($item) !== '') {
                            return $item;
                        }
                    }
                }

                if ($this->extract_embed_token($data) !== '') {
                    return $data;
                }
            } catch (\Throwable $e) {
                $this->log_debug('FredKI agent public access failed: ' . get_class($e));
                continue;
            }
        }

        return [];
    }
    /**
     * Build chat url from agent item.
     *
     * @param array $item The item.
     * @return string The result.
     */
    protected function build_chat_url_from_agent_item(array $item): string {
        $token = $this->extract_embed_token($item);
        if ($token === '') {
            return '';
        }
        $parts = parse_url($this->baseurl);
        if (empty($parts['host'])) {
            return '';
        }
        $scheme = 'https';
        $host = strtolower($parts['host']);
        return $scheme . '://' . $host . '/chat/' . rawurlencode($token);
    }

    /**
     * Build embed code from agent item.
     *
     * @param array $item The item.
     * @return string The result.
     */
    protected function build_embed_code_from_agent_item(array $item): string {
        $token = $this->extract_embed_token($item);
        if ($token === '') {
            return '';
        }

        $parts = parse_url($this->baseurl);
        if (empty($parts['host'])) {
            return '';
        }

        $host = strtolower($parts['host']);
        $scheme = 'https';
        $src = $scheme . '://' . $host . '/chat/api/embed?protocol=' . rawurlencode($scheme) .
        '&host=' . rawurlencode($host) . '&token=' . rawurlencode($token);

        return '<script async defer src="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>';
    }

    /**
     * Extract embed token.
     *
     * @param array $item The item.
     * @return string The result.
     */
    protected function extract_embed_token(array $item): string {
        $directkeys = [
            'embed_token', 'embedToken', 'public_token', 'publicToken', 'share_token', 'shareToken',
            'chat_token', 'chatToken', 'access_token', 'accessToken', 'token',
            'client_id', 'clientId', 'public_client_id', 'publicClientId',
            'public_access_client_id', 'publicAccessClientId', 'access_token_value', 'accessTokenValue',
            'access_num', 'accessNum', 'access_id', 'accessId', 'key',
        ];

        foreach ($directkeys as $key) {
            if (!empty($item[$key]) && is_scalar($item[$key])) {
                $candidate = trim((string)$item[$key]);
                if ($candidate !== '' && !preg_match('/^eyJ/', $candidate)) {
                    return $candidate;
                }
            }
        }

        $urlkeys = [
            'public_url', 'publicUrl', 'share_url', 'shareUrl', 'chat_url', 'chatUrl', 'url',
            'web_url', 'webUrl', 'embed_url', 'embedUrl', 'client_url', 'clientUrl',
            'access_url', 'accessUrl', 'public_access_url', 'publicAccessUrl',
        ];

        foreach ($urlkeys as $key) {
            if (empty($item[$key]) || !is_scalar($item[$key])) {
                continue;
            }
            $token = $this->extract_token_from_url((string)$item[$key]);
            if ($token !== '') {
                return $token;
            }
        }

        foreach ($item as $value) {
            if (is_array($value)) {
                $token = $this->extract_embed_token($value);
                if ($token !== '') {
                    return $token;
                }
            }
        }

        return '';
    }

    /**
     * Extract token from url.
     *
     * @param string $url The URL.
     * @return string The result.
     */
    protected function extract_token_from_url(string $url): string {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['token']) && is_scalar($query['token'])) {
                return trim((string)$query['token']);
            }
        }

        if (!empty($parts['path']) && preg_match('~/(?:chat|application)/([^/?#]+)~', $parts['path'], $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Split upload.
     *
     * @param string $knowledgeid The knowledge base id.
     * @param string $filepath The filepath.
     * @param string $filename The file name.
     * @return array The result.
     */
    public function split_upload(string $knowledgeid, string $filepath, string $filename): array {
        global $CFG;

        $this->login();
        require_once($CFG->libdir . '/filelib.php');

        if (!is_readable($filepath) || !is_file($filepath)) {
            throw new \moodle_exception('filenotfound', 'mod_fredkicourseimport');
        }

        $url = $this->baseurl . '/admin/api/workspace/' . rawurlencode($this->workspace)
            . '/knowledge/' . rawurlencode($knowledgeid) . '/document/split';
        $mimetype = (string)mimeinfo('type', $filename);
        if ($mimetype === '' || $mimetype === 'application/x-empty') {
            $mimetype = 'application/octet-stream';
        }

        $safeuploadname = clean_param($filename, PARAM_FILE);
        if ($safeuploadname === '') {
            $safeuploadname = 'document.' . strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        }

        $attempts = [
            [$filename, $mimetype],
        ];
        if ($safeuploadname !== $filename) {
            $attempts[] = [$safeuploadname, $mimetype];
        }
        if ($mimetype !== 'application/octet-stream') {
            $attempts[] = [$safeuploadname, 'application/octet-stream'];
        }

        $lastcode = 0;
        $lasterrno = 0;
        foreach ($attempts as [$uploadname, $uploadtype]) {
            $curl = new \curl();
            $options = [
                'CURLOPT_TIMEOUT' => 180,
                'CURLOPT_CONNECTTIMEOUT' => 20,
                'CURLOPT_FOLLOWLOCATION' => false,
            ];
            $headers = [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ];
            $post = [
                'file' => new \CURLFile($filepath, $uploadtype, $uploadname),
            ];
            $raw = $curl->post($url, $post, $options + ['CURLOPT_HTTPHEADER' => $headers]);
            $info = $curl->get_info();
            $lastcode = (int)($info['http_code'] ?? 0);
            $lasterrno = (int)$curl->get_errno();
            $response = json_decode((string)$raw, true);
            $item = $this->extract_split_item($response);

            if ($lasterrno === 0 && $lastcode < 400 && $item !== null) {
                return $item;
            }
        }

        $this->log_debug(
            'FredKI split upload failed: HTTP ' . $lastcode
            . ', cURL errno ' . $lasterrno
            . ', extension ' . strtolower(pathinfo($filename, PATHINFO_EXTENSION))
            . ', size ' . filesize($filepath)
        );
        throw new \moodle_exception('documentextractfailed', 'mod_fredkicourseimport');
    }

    /**
     * Extract one split result from the response shapes used by FredKI versions.
     *
     * @param mixed $response Decoded JSON response.
     * @return array|null Split item or null.
     */
    protected function extract_split_item($response): ?array {
        if (!is_array($response)) {
            return null;
        }
        if (!empty($response['data'][0]) && is_array($response['data'][0])) {
            return $response['data'][0];
        }
        if (!empty($response['data']) && is_array($response['data'])) {
            if (isset($response['data']['content']) || isset($response['data']['source_file_id'])) {
                return $response['data'];
            }
            $first = reset($response['data']);
            if (is_array($first)) {
                return $first;
            }
        }
        if (isset($response['content']) || isset($response['source_file_id'])) {
            return $response;
        }
        return null;
    }

    /**
     * Batch create.
     *
     * @param string $knowledgeid The knowledge base id.
     * @param array $documents The documents.
     * @return array The result.
     */
    public function batch_create(string $knowledgeid, array $documents): array {
        $this->login();
        $url = $this->baseurl . '/admin/api/workspace/' . rawurlencode($this->workspace)
            . '/knowledge/' . rawurlencode($knowledgeid) . '/document/batch_create';
        return $this->request_json('PUT', $url, $documents, true);
    }

    /**
     * Return all tags configured for a FredKI knowledge base.
     *
     * FredKI/MaxKB returns tags grouped by key. This method normalises the
     * response to a flat list containing id, key and value.
     *
     * @param string $knowledgeid Knowledge base id.
     * @return array<int,array{id:string,key:string,value:string}>
     */
    public function list_tags(string $knowledgeid): array {
        $this->login();
        $url = $this->baseurl . '/admin/api/workspace/' . rawurlencode($this->workspace)
            . '/knowledge/' . rawurlencode($knowledgeid) . '/tags';
        $response = $this->request_json('GET', $url, null, true);
        $groups = $response['data'] ?? [];
        if (!is_array($groups)) {
            return [];
        }

        $result = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $key = trim((string)($group['key'] ?? ''));
            $values = $group['values'] ?? [];
            if ($key === '' || !is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                if (!is_array($value) || empty($value['id'])) {
                    continue;
                }
                $result[] = [
                    'id' => (string)$value['id'],
                    'key' => $key,
                    'value' => (string)($value['value'] ?? ''),
                ];
            }
        }
        return $result;
    }

    /**
     * Ensure tag key/value pairs exist and return their FredKI tag ids.
     *
     * @param string $knowledgeid Knowledge base id.
     * @param array $tags Tags to create/find.
     * @return string[] Tag ids.
     */
    public function ensure_tags(string $knowledgeid, array $tags): array {
        $normalised = [];
        foreach ($tags as $tag) {
            if (!is_array($tag)) {
                continue;
            }
            $key = trim((string)($tag['key'] ?? ''));
            $value = trim((string)($tag['value'] ?? ''));
            if ($key === '' || $value === '') {
                continue;
            }
            // FredKI limits tag key/value lengths server-side. Truncate safely here
            // so a single long Moodle custom-field value cannot break the import.
            $key = \core_text::substr($key, 0, 64);
            $value = \core_text::substr($value, 0, 128);
            $normalised[$key . "\0" . $value] = ['key' => $key, 'value' => $value];
        }
        if (!$normalised) {
            return [];
        }

        $existing = $this->list_tags($knowledgeid);
        $index = [];
        foreach ($existing as $tag) {
            $index[$tag['key'] . "\0" . $tag['value']] = $tag['id'];
        }

        $missing = [];
        foreach ($normalised as $pair => $tag) {
            if (!isset($index[$pair])) {
                $missing[] = $tag;
            }
        }

        if ($missing) {
            $url = $this->baseurl . '/admin/api/workspace/' . rawurlencode($this->workspace)
                . '/knowledge/' . rawurlencode($knowledgeid) . '/tags';
            // FredKI expects a raw JSON list of key/value objects.
            $this->request_json('POST', $url, $missing, true);

            // The create endpoint intentionally has no useful response payload.
            // Re-read the tag list to obtain ids for the newly created tags.
            $existing = $this->list_tags($knowledgeid);
            $index = [];
            foreach ($existing as $tag) {
                $index[$tag['key'] . "\0" . $tag['value']] = $tag['id'];
            }
        }

        $ids = [];
        foreach ($normalised as $pair => $tag) {
            if (!empty($index[$pair])) {
                $ids[] = (string)$index[$pair];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Add FredKI tags to a document.
     *
     * @param string $knowledgeid Knowledge base id.
     * @param string $documentid Document id.
     * @param string[] $tagids Tag ids.
     * @return array API response.
     */
    public function add_document_tags(string $knowledgeid, string $documentid, array $tagids): array {
        $tagids = array_values(array_unique(array_filter(array_map('strval', $tagids))));
        if (!$tagids) {
            return [];
        }
        $this->login();
        $url = $this->baseurl . '/admin/api/workspace/' . rawurlencode($this->workspace)
            . '/knowledge/' . rawurlencode($knowledgeid)
            . '/document/' . rawurlencode($documentid) . '/tags';
        // FredKI expects the raw JSON array of tag UUIDs.
        return $this->request_json('POST', $url, $tagids, true);
    }

    /**
     * Delete document.
     *
     * @param string $knowledgeid The knowledge base id.
     * @param string $documentid The documentid.
     * @return array The result.
     */
    public function delete_document(string $knowledgeid, string $documentid): array {
        global $CFG;

        $this->login();
        require_once($CFG->libdir . '/filelib.php');

        $url = $this->baseurl . '/admin/api/workspace/' . rawurlencode($this->workspace)
            . '/knowledge/' . rawurlencode($knowledgeid) . '/document/' . rawurlencode($documentid);
        $curl = new \curl();
        $raw = $curl->delete($url, [], [
            'CURLOPT_TIMEOUT' => 12,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_HTTPHEADER' => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);
        $info = $curl->get_info();
        $httpcode = (int)($info['http_code'] ?? 0);
        if ($curl->get_errno() || $httpcode < 200 || $httpcode >= 300) {
            throw new \moodle_exception('externalapierror', 'mod_fredkicourseimport');
        }

        // Several FredKI/API gateway versions correctly answer DELETE with 204 or an
        // otherwise empty body. Treat that as success instead of waiting for JSON.
        $trimmed = trim((string)$raw);
        if ($trimmed === '') {
            return [];
        }
        $response = json_decode($trimmed, true);
        return is_array($response) ? $response : [];
    }

    /**
     * Request json.
     *
     * @param string $method The method.
     * @param string $url The URL.
     * @param ?array $payload The payload.
     * @param bool $withauth The withauth.
     * @return array The result.
     */
    protected function request_json(string $method, string $url, ?array $payload = null, bool $withauth = true): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $headers = ['Accept: application/json'];
        if ($withauth) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        $options = [
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_HTTPHEADER' => $headers,
        ];
        $body = null;
        if ($payload !== null) {
            $options['CURLOPT_HTTPHEADER'][] = 'Content-Type: application/json';
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        switch (strtoupper($method)) {
            case 'GET':
                $raw = $curl->get($url, [], $options);
                break;
            case 'POST':
                $raw = $curl->post($url, $body ?? '', $options);
                break;
            case 'PUT':
                $raw = $curl->put($url, $body ?? '', $options);
                break;
            case 'DELETE':
                $raw = $curl->delete($url, [], $options);
                break;
            default:
                throw new \coding_exception('Unsupported HTTP method.');
        }
        $info = $curl->get_info();
        $httpcode = (int)($info['http_code'] ?? 0);
        if ($curl->get_errno() || $httpcode >= 400) {
            throw new \moodle_exception('externalapierror', 'mod_fredkicourseimport');
        }
        $response = json_decode((string)$raw, true);
        if (!is_array($response)) {
            throw new \moodle_exception('externalapiinvalidresponse', 'mod_fredkicourseimport');
        }
        return $response;
    }
}

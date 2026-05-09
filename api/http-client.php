<?php
// api/http-client.php - HTTP Client untuk request ke API eksternal (Midtrans, dll)
// Support both cURL dan Streams API

class HttpClient {
    /**
     * POST request ke URL dengan data JSON dan headers custom
     * 
     * @param string $url Target URL
     * @param array $data Data untuk dikirim (akan di-encode menjadi JSON)
     * @param array $headers Custom headers
     * @return array ['http_code' => int, 'body' => string]
     */
    public static function post($url, $data, $headers = []) {
        // Try cURL first
        if (function_exists('curl_init')) {
            return self::postWithCurl($url, $data, $headers);
        }
        // Fallback to Streams API
        elseif (ini_get('allow_url_fopen')) {
            return self::postWithStreams($url, $data, $headers);
        } else {
            throw new Exception('Tidak ada metode HTTP tersedia. Enable cURL atau allow_url_fopen di php.ini');
        }
    }

    /**
     * POST menggunakan cURL
     */
    private static function postWithCurl($url, $data, $headers) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array_merge(
                ['Content-Type: application/json'],
                $headers
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response_body = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        return ['http_code' => $http_code, 'body' => $response_body];
    }

    /**
     * POST menggunakan Streams API (file_get_contents)
     */
    private static function postWithStreams($url, $data, $headers) {
        // Convert associative array headers ke format string HTTP headers
        $header_lines = ['Content-Type: application/json'];
        
        if (is_array($headers)) {
            if (self::isAssociativeArray($headers)) {
                // Associative array: ['Authorization' => 'Basic ...', 'Accept' => '...']
                foreach ($headers as $key => $value) {
                    $header_lines[] = "$key: $value";
                }
            } else {
                // Indexed array: ['Content-Type: application/json', 'Authorization: ...']
                $header_lines = array_merge($header_lines, $headers);
            }
        }
        
        $context_options = [
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $header_lines),
                'content'       => json_encode($data),
                'timeout'       => 30,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false
            ]
        ];

        $context = stream_context_create($context_options);
        $response_body = file_get_contents($url, false, $context);
        
        // Extract HTTP response code from headers
        $http_code = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            if (preg_match('/HTTP\/\d+\.\d+ (\d+)/', $http_response_header[0], $matches)) {
                $http_code = (int)$matches[1];
            }
        }

        if ($response_body === false) {
            throw new Exception('Request failed using Streams API');
        }

        return ['http_code' => $http_code, 'body' => $response_body];
    }
    
    private static function isAssociativeArray($array) {
        if (!is_array($array) || count($array) === 0) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * GET request ke URL
     * 
     * @param string $url Target URL
     * @param array $headers Custom headers
     * @return array ['http_code' => int, 'body' => string]
     */
    public static function get($url, $headers = []) {
        if (function_exists('curl_init')) {
            return self::getWithCurl($url, $headers);
        } elseif (ini_get('allow_url_fopen')) {
            return self::getWithStreams($url, $headers);
        } else {
            throw new Exception('Tidak ada metode HTTP tersedia');
        }
    }

    private static function getWithCurl($url, $headers) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response_body = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        return ['http_code' => $http_code, 'body' => $response_body];
    }

    private static function getWithStreams($url, $headers) {
        // Convert associative array headers ke format string HTTP headers
        $header_lines = [];
        
        if (is_array($headers)) {
            if (self::isAssociativeArray($headers)) {
                foreach ($headers as $key => $value) {
                    $header_lines[] = "$key: $value";
                }
            } else {
                $header_lines = $headers;
            }
        }
        
        $context_options = [
            'http' => [
                'method'        => 'GET',
                'header'        => implode("\r\n", $header_lines),
                'timeout'       => 30,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false
            ]
        ];

        $context = stream_context_create($context_options);
        $response_body = file_get_contents($url, false, $context);

        $http_code = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            if (preg_match('/HTTP\/\d+\.\d+ (\d+)/', $http_response_header[0], $matches)) {
                $http_code = (int)$matches[1];
            }
        }

        if ($response_body === false) {
            throw new Exception('Request failed using Streams API');
        }

        return ['http_code' => $http_code, 'body' => $response_body];
    }
}
?>

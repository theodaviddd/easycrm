<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/keyyoapi.class.php
 * \ingroup reedcrm
 * \brief   Class to manage Keyyo API calls with OAuth2
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

/**
 * Class to manage Keyyo API with OAuth2
 */
class KeyyoAPI
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Client ID (hardcoded for now)
     */
    private $clientId = '68b6b2440fe6a';

    /**
     * @var string Client Secret (hardcoded for now)
     */
    private $clientSecret = '725a1c4d5333171121b35d67';

    /**
     * @var string OAuth2 Authorize URL
     */
    private $authorizeUrl = 'https://ssl.keyyo.com/oauth2/authorize.php';

    /**
     * @var string OAuth2 Token URL
     */
    private $tokenUrl = 'https://api.keyyo.com/oauth2/token.php';

    /**
     * @var string API Base URL
     */
    private $apiBase = 'https://api.keyyo.com/manager/1.0';

    /**
     * @var string Redirect URI (hardcoded for now)
     */
    private $redirectUri = 'https://critics-lightbox-robust-each.trycloudflare.com/dolibarr/htdocs/custom/reedcrm/view/keyyo_calls_sms.php?action=keyyo_callback';

    /**
     * @var string Access token
     */
    private $accessToken;

    /**
     * @var array Errors
     */
    public $errors = [];



    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;

        // Load token from Dolibarr configuration
        if (!empty($conf->global->REEDCRM_KEYYO_TOKEN)) {
            $tokenData = json_decode($conf->global->REEDCRM_KEYYO_TOKEN, true);
            if (is_array($tokenData) && !empty($tokenData['access_token'])) {
                $this->accessToken = $tokenData['access_token'];
            }
        }
    }

    /**
     * Check if we have a valid token
     *
     * @return bool True if token exists, false otherwise
     */
    public function hasToken()
    {
        return !empty($this->accessToken);
    }

    /**
     * Get OAuth2 authorization URL
     *
     * @return string Authorization URL to redirect user to
     */
    public function getAuthorizationUrl()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth2_state'] = $state;

        return $this->authorizeUrl . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'scope'         => '',
            'state'         => $state,
        ]);
    }

    /**
     * Handle OAuth2 callback and exchange code for token
     *
     * @param  string $code  Authorization code from callback
     * @param  string $state State parameter for verification
     * @return bool          True if token obtained successfully, false otherwise
     */
    public function handleCallback($code, $state)
    {
        global $conf;

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Verify state
        if (!isset($_SESSION['oauth2_state']) || $state !== $_SESSION['oauth2_state']) {
            $this->errors[] = 'Invalid OAuth state';
            return false;
        }

        try {
            $tokenData = $this->fetchAccessTokenWithCode($code);

            if (!empty($tokenData['access_token'])) {
                $this->accessToken = $tokenData['access_token'];

                // Save token in Dolibarr configuration
                dolibarr_set_const($this->db, 'REEDCRM_KEYYO_TOKEN', json_encode($tokenData), 'chaine', 0, '', $conf->entity);

                return true;
            }

            $this->errors[] = 'Failed to obtain access token';
            return false;

        } catch (Exception $e) {
            $this->errors[] = 'Authentication error: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Set access token manually (useful if token is stored elsewhere)
     *
     * @param string $token Access token
     */
    public function setAccessToken($token)
    {
        $this->accessToken = $token;
    }

    /**
     * Get incoming calls
     *
     * @param  int         $days   Number of days to look back (default 7)
     * @param  int         $limit  Max number of records (default 100)
     * @param  int         $offset Offset for pagination (default 0)
     * @return array|false         Array of calls or false on error
     */
    public function getIncomingCalls($days = 7, $limit = 100, $offset = 0)
    {
        if (empty($this->accessToken)) {
            $this->errors[] = 'No access token. Please authenticate first.';
            return false;
        }

        $dateStart = (new DateTime('now', new DateTimeZone('Europe/Paris')))
            ->modify("-$days days")
            ->format('Y-m-d 00:00:00');
        $dateEnd = (new DateTime('now', new DateTimeZone('Europe/Paris')))
            ->format('Y-m-d 23:59:59');

        return $this->apiGet('/incoming_call_detail', [
            'limit'      => $limit,
            'offset'     => $offset,
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
            'unit'       => 'second',
        ]);
    }

    /**
     * Get outgoing calls
     *
     * @param  int         $days   Number of days to look back (default 7)
     * @param  int         $limit  Max number of records (default 100)
     * @param  int         $offset Offset for pagination (default 0)
     * @return array|false         Array of calls or false on error
     */
    public function getOutgoingCalls($days = 7, $limit = 100, $offset = 0)
    {
        if (empty($this->accessToken)) {
            $this->errors[] = 'No access token. Please authenticate first.';
            return false;
        }

        $dateStart = (new DateTime('now', new DateTimeZone('Europe/Paris')))
            ->modify("-$days days")
            ->format('Y-m-d 00:00:00');
        $dateEnd = (new DateTime('now', new DateTimeZone('Europe/Paris')))
            ->format('Y-m-d 23:59:59');

        return $this->apiGet('/outgoing_call_detail', [
            'limit'      => $limit,
            'offset'     => $offset,
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
            'unit'       => 'second',
        ]);
    }

    /**
     * Get all calls (incoming + outgoing)
     *
     * @param  int         $days   Number of days to look back (default 7)
     * @param  int         $limit  Max number of records per type (default 100)
     * @return array|false         Array with 'incoming' and 'outgoing' keys or false on error
     */
    public function getAllCalls($days = 7, $limit = 100)
    {
        $incoming = $this->getIncomingCalls($days, $limit);
        $outgoing = $this->getOutgoingCalls($days, $limit);

        if ($incoming === false || $outgoing === false) {
            return false;
        }

        return [
            'incoming' => $incoming,
            'outgoing' => $outgoing,
        ];
    }

    /**
     * Get SMS (attempt via CDR with unit=sms)
     *
     * @param  int         $days   Number of days to look back (default 7)
     * @param  int         $limit  Max number of records (default 100)
     * @param  int         $offset Offset for pagination (default 0)
     * @return array|false         Array of SMS or false on error
     */
    public function getSMS($days = 7, $limit = 100, $offset = 0)
    {
        if (empty($this->accessToken)) {
            $this->errors[] = 'No access token. Please authenticate first.';
            return false;
        }

        $dateStart = (new DateTime('now', new DateTimeZone('Europe/Paris')))
            ->modify("-$days days")
            ->format('Y-m-d 00:00:00');
        $dateEnd = (new DateTime('now', new DateTimeZone('Europe/Paris')))
            ->format('Y-m-d 23:59:59');

        return $this->apiGet('/incoming_call_detail', [
            'limit'      => $limit,
            'offset'     => $offset,
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
            'unit'       => 'sms',
        ]);
    }

    /**
     * Get calls and SMS for a specific phone number
     *
     * @param  string      $phoneNumber Phone number to filter
     * @param  int         $days        Number of days to look back
     * @return array|false              Array with filtered calls and SMS or false on error
     */
    public function getCallsAndSMSForNumber($phoneNumber, $days = 7)
    {
        $allCalls = $this->getAllCalls($days);
        $sms = $this->getSMS($days);

        if ($allCalls === false) {
            return false;
        }

        // Format phone number for comparison
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        // Filter incoming calls
        $incomingFiltered = $this->filterByPhoneNumber($allCalls['incoming'], $phoneNumber);

        // Filter outgoing calls
        $outgoingFiltered = $this->filterByPhoneNumber($allCalls['outgoing'], $phoneNumber);

        // Filter SMS
        $smsFiltered = [];
        if ($sms !== false) {
            $smsFiltered = $this->filterByPhoneNumber($sms, $phoneNumber);
        }

        return [
            'incoming_calls' => $incomingFiltered,
            'outgoing_calls' => $outgoingFiltered,
            'sms' => $smsFiltered,
        ];
    }

    /**
     * Filter results by phone number (checks in 'from' and 'to' fields)
     *
     * @param  array  $results     Array of results
     * @param  string $phoneNumber Phone number to search for
     * @return array               Filtered results
     */
    private function filterByPhoneNumber($results, $phoneNumber)
    {
        if (!is_array($results)) {
            return [];
        }

        return array_filter($results, function ($item) use ($phoneNumber) {
            $from = isset($item['from']) ? $this->formatPhoneNumber($item['from']) : '';
            $to = isset($item['to']) ? $this->formatPhoneNumber($item['to']) : '';
            $caller = isset($item['caller']) ? $this->formatPhoneNumber($item['caller']) : '';
            $called = isset($item['called']) ? $this->formatPhoneNumber($item['called']) : '';

            return $from === $phoneNumber
                || $to === $phoneNumber
                || $caller === $phoneNumber
                || $called === $phoneNumber;
        });
    }

    /**
     * Format phone number for comparison
     *
     * @param  string $phoneNumber Phone number
     * @return string              Formatted phone number
     */
    private function formatPhoneNumber($phoneNumber)
    {
        // Remove all non-numeric characters except +
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // Ensure it starts with +
        if (!empty($phoneNumber) && substr($phoneNumber, 0, 1) !== '+') {
            // If it starts with 0, replace with +33 (France)
            if (substr($phoneNumber, 0, 1) === '0') {
                $phoneNumber = '+33' . substr($phoneNumber, 1);
            } else {
                $phoneNumber = '+' . $phoneNumber;
            }
        }

        return $phoneNumber;
    }

    /**
     * Fetch access token using authorization code
     *
     * @param  string $code Authorization code from callback
     * @return array        Token data
     * @throws RuntimeException If token cannot be obtained
     */
    private function fetchAccessTokenWithCode($code)
    {
        $response = $this->httpPostForm($this->tokenUrl, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Failed to obtain access_token: ' . $response);
        }

        return $data;
    }

    /**
     * Make API GET request
     *
     * @param  string      $endpoint Endpoint path (e.g., '/incoming_call_detail')
     * @param  array       $query    Query parameters
     * @return array|false           Response data or false on error
     * @throws Exception             If token is invalid (401)
     */
    private function apiGet($endpoint, $query = [])
    {
        $url = $this->apiBase . $endpoint;
        $queryString = $query ? ('?' . http_build_query($query)) : '';

        $ch = curl_init($url . $queryString);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->errors[] = "cURL error: $error";
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Token expired or invalid
        if ($httpCode === 401) {
            global $conf;
            // Clear invalid token
            dolibarr_del_const($this->db, 'REEDCRM_KEYYO_TOKEN', $conf->entity);
            $this->accessToken = null;
            throw new Exception('KEYYO_TOKEN_EXPIRED');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->errors[] = "HTTP $httpCode: $response";
            return false;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'JSON decode error: ' . json_last_error_msg();
            return false;
        }

        return $data;
    }

    /**
     * Make HTTP POST request with form data
     *
     * @param  string $url    URL to post to
     * @param  array  $fields Form fields
     * @return string         Response body
     * @throws RuntimeException If request fails
     */
    private function httpPostForm($url, $fields)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL error: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("HTTP $httpCode: $response");
        }

        return $response;
    }

    /**
     * Test API connection (requires existing token)
     *
     * @return bool True if connection successful, false otherwise
     */
    public function testConnection()
    {
        if (empty($this->accessToken)) {
            $this->errors[] = 'No access token. Please authenticate first.';
            return false;
        }

        // Try to get calls for the last day as a connection test
        $result = $this->getIncomingCalls(1, 1);

        return $result !== false;
    }
}


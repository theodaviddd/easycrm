<?php
/**
 * Keyyo → Dolibarr (PHP) — OAuth2 + CDR (appels/SMS)
 * Dépose ce fichier sur ton serveur web (ex: htdocs/custom/keyyo_dolibarr.php)
 * et règle la redirect URI dans Keyyo sur: https://tondomaine.tld/.../keyyo_dolibarr.php?action=callback
 *
 * ⚠️ Par sécurité, mets l’ID/secret dans des variables d’environnement en prod.
 */

// ====== CONFIG ======
$KEYYO_CLIENT_ID     = '68b6b2440fe6a';
$KEYYO_CLIENT_SECRET = '725a1c4d5333171121b35d67'; // <<< passe ça en env en prod
$KEYYO_AUTHORIZE_URL = 'https://ssl.keyyo.com/oauth2/authorize.php';
$KEYYO_TOKEN_URL     = 'https://api.keyyo.com/oauth2/token.php';

$KEYYO_API_BASE      = 'https://api.keyyo.com/manager/1.0'; // base API Manager

// Fenêtre de recherche (ex: derniers 7 jours)
$DEFAULT_DATE_START  = (new DateTime('now', new DateTimeZone('Europe/Paris')))->modify('-7 days')->format('Y-m-d 00:00:00');
$DEFAULT_DATE_END    = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d 23:59:59');
const KEYYO_REDIRECT_URI = 'https://critics-lightbox-robust-each.trycloudflare.com/dolibarr/htdocs/custom/reedcrm/test/keyyo_dolibarr.php?action=callback';

// ====== ROUTER SIMPLE ======
$action = $_GET['action'] ?? (isset($_GET['code']) ? 'callback' : 'start');

try {
    if ($action === 'start') {
        // Étape 1 : redirection vers le consentement Keyyo
        $state = bin2hex(random_bytes(16));
        session_start();
        $_SESSION['oauth2_state'] = $state;

        $authorizeUrl = $KEYYO_AUTHORIZE_URL . '?' . http_build_query([
                'response_type' => 'code',
                'client_id'     => $KEYYO_CLIENT_ID,
                'redirect_uri'  => KEYYO_REDIRECT_URI,
                'scope'         => '', // si Keyyo requiert un scope précis, mets-le ici
                'state'         => $state,
            ]);

        header('Location: ' . $authorizeUrl);
        exit;
    }

    if ($action === 'callback') {
        // Étape 2 : échange "code" → access_token
        session_start();
        if (!isset($_GET['state'], $_SESSION['oauth2_state']) || $_GET['state'] !== $_SESSION['oauth2_state']) {
            throw new RuntimeException('Invalid OAuth state.');
        }
        if (!isset($_GET['code'])) {
            throw new RuntimeException('Missing authorization code.');
        }

        $token = fetchAccessTokenWithCode(
            $KEYYO_TOKEN_URL,
            $KEYYO_CLIENT_ID,
            $KEYYO_CLIENT_SECRET,
            KEYYO_REDIRECT_URI,
            $_GET['code'],
        );

        // Stockage minimal en session (à remplacer par DB sécurisée en prod)
        $_SESSION['keyyo_token'] = $token;

        // Enchaîne sur un appel API de démonstration
        header('Location: ' . currentScriptUrl(['action' => 'demo']));
        exit;
    }

    if ($action === 'demo') {
        session_start();
        if (empty($_SESSION['keyyo_token']['access_token'])) {
            throw new RuntimeException('No access token in session. Relance /?action=start');
        }

        $accessToken = $_SESSION['keyyo_token']['access_token'];

        // Paramètres facultatifs via GET
        $dateStart = $_GET['date_start'] ?? $DEFAULT_DATE_START;
        $dateEnd   = $_GET['date_end']   ?? $DEFAULT_DATE_END;

        // 1) Récupérer appels entrants
        $incoming = keyyoApiGet(
            "$KEYYO_API_BASE/incoming_call_detail",
            $accessToken,
            [
                'limit'      => 100,
                'offset'     => 0,
                'date_start' => $dateStart,
                'date_end'   => $dateEnd,
                'unit'       => 'second', // pour appels (durée en secondes). Pour SMS, essaie 'sms'
            ]
        );

        // 2) Récupérer appels sortants
        $outgoing = keyyoApiGet(
            "$KEYYO_API_BASE/outgoing_call_detail",
            $accessToken,
            [
                'limit'      => 100,
                'offset'     => 0,
                'date_start' => $dateStart,
                'date_end'   => $dateEnd,
                'unit'       => 'second',
            ]
        );

        // 3) (Option) Essai pour logs SMS via CDR — si activé côté compte
        //    Beaucoup d’installations exposent les SMS dans les CDR avec unit=sms
        $smsCdr = keyyoApiGet(
            "$KEYYO_API_BASE/incoming_call_detail",
            $accessToken,
            [
                'limit'      => 100,
                'offset'     => 0,
                'date_start' => $dateStart,
                'date_end'   => $dateEnd,
                'unit'       => 'sms',
            ]
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
            'incoming_calls' => $incoming,
            'outgoing_calls' => $outgoing,
            'sms_cdr_attempt' => $smsCdr,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Fallback : lancer le flow OAuth
    header('Location: ' . currentScriptUrl(['action' => 'start']));
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Keyyo/Dolibarr error: " . $e->getMessage();
    exit;
}

// ====== HELPERS ======

function currentScriptUrl(array $params = []): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = strtok($_SERVER['REQUEST_URI'] ?? '/keyyo_dolibarr.php', '?');
    $base   = "$scheme://$host$path";
    if (!$params) return $base;
    return $base . '?' . http_build_query($params);
}

function fetchAccessTokenWithCode(string $tokenUrl, string $clientId, string $clientSecret, string $redirectUri, string $code): array {
    $resp = httpPostForm($tokenUrl, [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $redirectUri,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
//        CURLOPT_CAINFO => 'C:\wamp64\bin\php\cacert.pem', // <-- chemin vers ton cacert.pem

    ]);
    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('Failed to obtain access_token: ' . $resp);
    }
    return $data;
}

/**
 * Certains comptes Keyyo autorisent aussi "client_credentials".
 * Si besoin, tu peux utiliser cette fonction au lieu du flow "code".
 */
function fetchAccessTokenWithClientCredentials(string $tokenUrl, string $clientId, string $clientSecret): array {
    $resp = httpPostForm($tokenUrl, [
        'grant_type'    => 'client_credentials',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
//        CURLOPT_CAINFO => 'C:\wamp64\bin\php\cacert.pem', // <-- idem

    ]);
    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('Failed to obtain access_token: ' . $resp);
    }
    return $data;
}

function keyyoApiGet(string $url, string $accessToken, array $query = []) {
    $q = $query ? ('?' . http_build_query($query)) : '';
    $ch = curl_init($url . $q);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error: $err");
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("HTTP $code: $resp");
    }
    $data = json_decode($resp, true);
    // Certaines réponses peuvent être déjà des tableaux, d’autres un objet avec 'items'…
    return $data;
}

function httpPostForm(string $url, array $fields): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error: $err");
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("HTTP $code: $resp");
    }
    return $resp;
}

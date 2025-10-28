<?php
/**
 * Script de test pour la classe KeyyoAPI
 * Usage: Accéder à ce fichier via le navigateur pour tester l'API Keyyo
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

// Load KeyyoAPI class
require_once __DIR__ . '/../class/keyyoapi.class.php';

try {
    $keyyo = new KeyyoAPI($db);
    
    $action = GETPOST('action', 'alpha');
    $days = GETPOST('days', 'int') ?: 7;
    $phone = GETPOST('phone', 'alpha');
    
    // Handle OAuth2 callback
    if ($action === 'callback') {
        $code = GETPOST('code', 'alpha');
        $state = GETPOST('state', 'alpha');
        
        if (empty($code)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Missing authorization code'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($keyyo->handleCallback($code, $state)) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>✓ Authentification réussie !</h1>';
            echo '<p>Le token a été stocké en session.</p>';
            echo '<p><a href="?action=test">Tester la connexion</a></p>';
            echo '<p><a href="?action=incoming">Voir les appels entrants</a></p>';
            echo '<p><a href="?action=all">Voir tous les appels</a></p>';
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Authentication failed',
                'errors' => $keyyo->errors
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    // Check if we need to authenticate
    if (!$keyyo->hasToken() && $action !== 'auth') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>⚠️ Authentification requise</h1>';
        echo '<p>Vous devez d\'abord vous authentifier avec Keyyo.</p>';
        echo '<p><a href="?action=auth" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">S\'authentifier avec Keyyo</a></p>';
        exit;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    switch ($action) {
        case 'auth':
            // Redirect to Keyyo authorization page
            $authUrl = $keyyo->getAuthorizationUrl();
            header('Location: ' . $authUrl);
            exit;
            
        case 'logout':
            // Clear session token
            session_start();
            unset($_SESSION['keyyo_token']);
            unset($_SESSION['oauth2_state']);
            echo json_encode([
                'success' => true,
                'message' => 'Logged out successfully'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'test':
            // Test de connexion
            $result = $keyyo->testConnection();
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Connection successful' : 'Connection failed',
                'errors' => $keyyo->errors
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'incoming':
            // Appels entrants
            $calls = $keyyo->getIncomingCalls($days);
            echo json_encode([
                'success' => $calls !== false,
                'days' => $days,
                'count' => is_array($calls) ? count($calls) : 0,
                'data' => $calls,
                'errors' => $keyyo->errors
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'outgoing':
            // Appels sortants
            $calls = $keyyo->getOutgoingCalls($days);
            echo json_encode([
                'success' => $calls !== false,
                'days' => $days,
                'count' => is_array($calls) ? count($calls) : 0,
                'data' => $calls,
                'errors' => $keyyo->errors
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'all':
            // Tous les appels
            $calls = $keyyo->getAllCalls($days);
            echo json_encode([
                'success' => $calls !== false,
                'days' => $days,
                'incoming_count' => is_array($calls) && isset($calls['incoming']) ? count($calls['incoming']) : 0,
                'outgoing_count' => is_array($calls) && isset($calls['outgoing']) ? count($calls['outgoing']) : 0,
                'data' => $calls,
                'errors' => $keyyo->errors
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'sms':
            // SMS
            $sms = $keyyo->getSMS($days);
            echo json_encode([
                'success' => $sms !== false,
                'days' => $days,
                'count' => is_array($sms) ? count($sms) : 0,
                'data' => $sms,
                'errors' => $keyyo->errors
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'phone':
            // Appels et SMS pour un numéro spécifique
            if (empty($phone)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Phone number required. Use ?action=phone&phone=0612345678'
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $data = $keyyo->getCallsAndSMSForNumber($phone, $days);
            echo json_encode([
                'success' => $data !== false,
                'phone' => $phone,
                'days' => $days,
                'incoming_count' => is_array($data) && isset($data['incoming_calls']) ? count($data['incoming_calls']) : 0,
                'outgoing_count' => is_array($data) && isset($data['outgoing_calls']) ? count($data['outgoing_calls']) : 0,
                'sms_count' => is_array($data) && isset($data['sms']) ? count($data['sms']) : 0,
                'data' => $data,
                'errors' => $keyyo->errors
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            // Documentation
            $hasToken = $keyyo->hasToken();
            echo json_encode([
                'message' => 'KeyyoAPI Test Script (OAuth2)',
                'authenticated' => $hasToken,
                'info' => $hasToken ? 'You are authenticated. Use the actions below.' : 'You need to authenticate first: ?action=auth',
                'usage' => [
                    'auth' => '?action=auth - Authenticate with Keyyo (OAuth2)',
                    'callback' => '?action=callback - OAuth2 callback (automatic)',
                    'logout' => '?action=logout - Clear session token',
                    'test' => '?action=test - Test connection',
                    'incoming' => '?action=incoming&days=7 - Get incoming calls',
                    'outgoing' => '?action=outgoing&days=7 - Get outgoing calls',
                    'all' => '?action=all&days=7 - Get all calls',
                    'sms' => '?action=sms&days=7 - Get SMS',
                    'phone' => '?action=phone&phone=0612345678&days=7 - Get calls/SMS for specific number'
                ],
                'examples' => [
                    'Authenticate' => '?action=auth',
                    'Test connection' => '?action=test',
                    'Last 7 days incoming calls' => '?action=incoming&days=7',
                    'Last 30 days all calls' => '?action=all&days=30',
                    'Calls for specific number' => '?action=phone&phone=+33612345678&days=7',
                    'Logout' => '?action=logout'
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}


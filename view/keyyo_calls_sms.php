<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    view/keyyo_calls_sms.php
 * \ingroup reedcrm
 * \brief   Page to show Keyyo calls and SMS for a thirdparty
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}


// Load libraries
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once __DIR__ . '/../class/keyyoapi.class.php';

if (file_exists(__DIR__ . '/../../saturne/lib/saturne_functions.lib.php')) {
    require_once __DIR__ . '/../../saturne/lib/saturne_functions.lib.php';
    saturne_load_langs(['reedcrm@reedcrm']);
} else {
    $langs->load('reedcrm@reedcrm');
}

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

// Security check
$socid = GETPOST('socid', 'int');
if ($user->socid > 0) {
    $socid = $user->socid;
}

// Initialize technical objects
$object = new Societe($db);
$keyyoAPI = new KeyyoAPI($db);

$hookmanager->initHooks(['keyyocallssms', 'globalcard']);

// Load object
if ($id > 0) {
    $result = $object->fetch($id);
    if ($result < 0) {
        dol_print_error($db);
        exit;
    }
}

// Security check
$result = restrictedArea($user, 'societe', $object->id, '&societe', '', 'fk_soc', 'rowid');

/*
 * Actions
 */

if ($action === 'keyyo_auth') {
    // Redirect to Keyyo authorization
    header('Location: ' . $keyyoAPI->getAuthorizationUrl());
    exit;
}

if ($action === 'keyyo_callback') {
    // Handle OAuth2 callback
    $code = GETPOST('code', 'alpha');
    $state = GETPOST('state', 'alpha');

    if ($keyyoAPI->handleCallback($code, $state)) {
        setEventMessages($langs->trans('KeyyoAuthSuccess'), null, 'mesgs');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $id);
        exit;
    } else {
        setEventMessages($langs->trans('KeyyoAuthError') . ': ' . implode(', ', $keyyoAPI->errors), null, 'errors');
    }
}

if ($action === 'keyyo_logout') {
    // Clear token from Dolibarr configuration
    dolibarr_del_const($db, 'REEDCRM_KEYYO_TOKEN', $conf->entity);
    
    // Clear session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['oauth2_state']);

    setEventMessages($langs->trans('KeyyoLogoutSuccess'), null, 'mesgs');
    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $id);
    exit;
}

/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('ThirdParty') . ' - Keyyo';
$help_url = '';

llxHeader('', $title, $help_url);

if ($id > 0) {
    $head = societe_prepare_head($object);

    print dol_get_fiche_head($head, 'keyyo', $langs->trans("ThirdParty"), -1, 'company');

    $linkback = '<a href="' . DOL_URL_ROOT . '/societe/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

    dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

    // Check if authenticated with Keyyo
    if (!$keyyoAPI->hasToken()) {
        // Redirect automatically to authentication
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=keyyo_auth');
        exit;
    }
    
    // If we have a token, try to use it
    {
        // Get all contacts linked to this thirdparty
        $contactsList = $object->contact_array();

        $phoneNumbers = [];
        $contactsInfo = [];

        if (is_array($contactsList) && count($contactsList) > 0) {
            require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

            foreach ($contactsList as $contactId => $contactData) {
                $tmpContact = new Contact($db);
                $tmpContact->fetch($contactId);

                // Collect phone numbers
                $phones = [];
                if (!empty($tmpContact->phone_pro)) {
                    $phones[] = $tmpContact->phone_pro;
                    $phoneNumbers[] = $tmpContact->phone_pro;
                }
                if (!empty($tmpContact->phone_perso)) {
                    $phones[] = $tmpContact->phone_perso;
                    $phoneNumbers[] = $tmpContact->phone_perso;
                }
                if (!empty($tmpContact->phone_mobile)) {
                    $phones[] = $tmpContact->phone_mobile;
                    $phoneNumbers[] = $tmpContact->phone_mobile;
                }

                // Store contact info with phone numbers
                foreach ($phones as $phone) {
                    $contactsInfo[$phone] = $tmpContact->getFullName($langs);
                }
            }
        }

        if (empty($phoneNumbers)) {
            print '<div class="info">' . $langs->trans('NoPhoneNumbersFoundForThisThirdparty') . '</div>';
        } else {

            // Get calls and SMS from Keyyo API (last 100 days)
            $days = 100;
            
            try {
                // Get all data once (2 API calls total)
                $allCallsData = $keyyoAPI->getAllCalls($days, 1000);
                $allSMSData = $keyyoAPI->getSMS($days, 1000);
            } catch (Exception $e) {
                if ($e->getMessage() === 'KEYYO_TOKEN_EXPIRED') {
                    // Token expired - redirect automatically to re-authenticate
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=keyyo_auth');
                    exit;
                } else {
                    // Other error
                    setEventMessages('Keyyo API Error: ' . $e->getMessage(), null, 'errors');
                }
            }
            
            // Check for errors
            if (!empty($keyyoAPI->errors)) {
                foreach ($keyyoAPI->errors as $error) {
                    setEventMessages($error, null, 'errors');
                }
            }
            
            // If no data, skip processing
            if (!isset($allCallsData) || !isset($allSMSData)) {
                print '<div class="error">' . $langs->trans('KeyyoAPIError') . '</div>';
                print '</div>';
                print dol_get_fiche_end();
                llxFooter();
                $db->close();
                exit;
            }
            
            // Helper function to match phone numbers
            $phoneMatches = function($num1, $num2) {
                $n1 = preg_replace('/[^0-9+]/', '', $num1);
                $n2 = preg_replace('/[^0-9+]/', '', $num2);
                $n1 = preg_replace('/^\+?33/', '', $n1);
                $n2 = preg_replace('/^\+?33/', '', $n2);
                $n1 = ltrim($n1, '0');
                $n2 = ltrim($n2, '0');
                
                if (strlen($n1) >= 9 && strlen($n2) >= 9) {
                    return substr($n1, -9) === substr($n2, -9);
                }
                return $n1 === $n2;
            };
            
            // Filter and aggregate data
            $allData = [
                'incoming_calls' => [],
                'outgoing_calls' => [],
                'sms' => []
            ];
            
            // Filter incoming calls
            if (isset($allCallsData['incoming']) && is_array($allCallsData['incoming'])) {
                foreach ($allCallsData['incoming'] as $call) {
                    $caller = isset($call['caller']) ? $call['caller'] : '';
                    $called = isset($call['called']) ? $call['called'] : '';
                    
                    foreach ($phoneNumbers as $phone) {
                        if ($phoneMatches($caller, $phone) || $phoneMatches($called, $phone)) {
                            $allData['incoming_calls'][] = $call;
                            break;
                        }
                    }
                }
            }
            
            // Filter outgoing calls
            if (isset($allCallsData['outgoing']) && is_array($allCallsData['outgoing'])) {
                foreach ($allCallsData['outgoing'] as $call) {
                    $caller = isset($call['caller']) ? $call['caller'] : '';
                    $called = isset($call['called']) ? $call['called'] : '';
                    
                    foreach ($phoneNumbers as $phone) {
                        if ($phoneMatches($caller, $phone) || $phoneMatches($called, $phone)) {
                            $allData['outgoing_calls'][] = $call;
                            break;
                        }
                    }
                }
            }
            
            // Filter SMS
            if ($allSMSData !== false && is_array($allSMSData)) {
                foreach ($allSMSData as $sms) {
                    $caller = isset($sms['caller']) ? $sms['caller'] : '';
                    $called = isset($sms['called']) ? $sms['called'] : '';
                    
                    foreach ($phoneNumbers as $phone) {
                        if ($phoneMatches($caller, $phone) || $phoneMatches($called, $phone)) {
                            $allData['sms'][] = $sms;
                            break;
                        }
                    }
                }
            }

            // Total counts
            $totalIncoming = count($allData['incoming_calls']);
            $totalOutgoing = count($allData['outgoing_calls']);
            $totalSMS = count($allData['sms']);

            // Display calls
            print '<div class="div-table-responsive-no-min">';
            print '<h3>' . $langs->trans('KeyyoCalls') . ' (' . ($totalIncoming + $totalOutgoing) . ')</h3>';

            if ($totalIncoming === 0 && $totalOutgoing === 0) {
                print '<div class="opacitymedium">' . $langs->trans('NoCallsFound') . '</div>';
            } else {
                // Merge and sort all calls by date
                $allCalls = array_merge($allData['incoming_calls'], $allData['outgoing_calls']);

                // Sort by date (most recent first)
                usort($allCalls, function ($a, $b) {
                    $dateA = isset($a['start']) ? $a['start'] : (isset($a['date']) ? $a['date'] : '0');
                    $dateB = isset($b['start']) ? $b['start'] : (isset($b['date']) ? $b['date'] : '0');
                    return strtotime($dateB) - strtotime($dateA);
                });

                print '<table class="tagtable nobottomiftotal liste">';
                print '<thead>';
                print '<tr class="liste_titre">';
                print '<th class="left">' . $langs->trans('Date') . '</th>';
                print '<th class="left">' . $langs->trans('Contact') . '</th>';
                print '<th class="left">' . $langs->trans('From') . '</th>';
                print '<th class="left">' . $langs->trans('To') . '</th>';
                print '<th class="left">' . $langs->trans('Duration') . '</th>';
                print '<th class="left">' . $langs->trans('Type') . '</th>';
                print '</tr>';
                print '</thead>';
                print '<tbody>';
                
                foreach ($allCalls as $call) {
                    // Try different field names that Keyyo might use
                    $date = isset($call['start']) ? $call['start'] : (isset($call['date']) ? $call['date'] : '');
                    $from = isset($call['caller']) ? $call['caller'] : (isset($call['from']) ? $call['from'] : '');
                    $to = isset($call['called']) ? $call['called'] : (isset($call['to']) ? $call['to'] : '');
                    $duration = isset($call['duration']) ? $call['duration'] : 0;
                    $type = isset($call['type']) ? $call['type'] : (in_array($call, $allData['incoming_calls']) ? 'incoming' : 'outgoing');

                    // Find contact name
                    $contactName = '';
                    if (isset($contactsInfo[$from])) {
                        $contactName = $contactsInfo[$from];
                    } elseif (isset($contactsInfo[$to])) {
                        $contactName = $contactsInfo[$to];
                    }

                    print '<tr class="oddeven">';
                    print '<td>' . dol_print_date(strtotime($date), 'dayhour') . '</td>';
                    print '<td>' . $contactName . '</td>';
                    print '<td>' . $from . '</td>';
                    print '<td>' . $to . '</td>';
                    print '<td>' . gmdate("H:i:s", $duration) . '</td>';
                    print '<td>' . ($type === 'incoming' ? '↓ ' : '↑ ') . $langs->trans(ucfirst($type)) . '</td>';
                    print '</tr>';
                }

                print '</tbody>';
                print '</table>';
            }
            print '</div>';

            print '<br>';

            // Display SMS
            print '<div class="div-table-responsive-no-min">';
            print '<h3>' . $langs->trans('SMS') . ' (' . $totalSMS . ')</h3>';

            if ($totalSMS === 0) {
                print '<div class="opacitymedium">' . $langs->trans('NoSMSFound') . '</div>';
            } else {
                // Sort SMS by date (most recent first)
                usort($allData['sms'], function ($a, $b) {
                    $dateA = isset($a['start']) ? $a['start'] : (isset($a['date']) ? $a['date'] : '0');
                    $dateB = isset($b['start']) ? $b['start'] : (isset($b['date']) ? $b['date'] : '0');
                    return strtotime($dateB) - strtotime($dateA);
                });

                print '<table class="tagtable nobottomiftotal liste">';
                print '<thead>';
                print '<tr class="liste_titre">';
                print '<th class="left">' . $langs->trans('Date') . '</th>';
                print '<th class="left">' . $langs->trans('Contact') . '</th>';
                print '<th class="left">' . $langs->trans('From') . '</th>';
                print '<th class="left">' . $langs->trans('To') . '</th>';
                print '<th class="left">' . $langs->trans('Message') . '</th>';
                print '</tr>';
                print '</thead>';
                print '<tbody>';

                foreach ($allData['sms'] as $sms) {
                    $date = isset($sms['start']) ? $sms['start'] : (isset($sms['date']) ? $sms['date'] : '');
                    $from = isset($sms['caller']) ? $sms['caller'] : (isset($sms['from']) ? $sms['from'] : '');
                    $to = isset($sms['called']) ? $sms['called'] : (isset($sms['to']) ? $sms['to'] : '');
                    $message = isset($sms['message']) ? $sms['message'] : '';

                    // Find contact name
                    $contactName = '';
                    if (isset($contactsInfo[$from])) {
                        $contactName = $contactsInfo[$from];
                    } elseif (isset($contactsInfo[$to])) {
                        $contactName = $contactsInfo[$to];
                    }

                    print '<tr class="oddeven">';
                    print '<td>' . dol_print_date(strtotime($date), 'dayhour') . '</td>';
                    print '<td>' . $contactName . '</td>';
                    print '<td>' . $from . '</td>';
                    print '<td>' . $to . '</td>';
                    print '<td>' . dol_trunc($message, 100) . '</td>';
                    print '</tr>';
                }

                print '</tbody>';
                print '</table>';
            }
            print '</div>';
        }
    }

    print '</div>';
    print dol_get_fiche_end();
}

// End of page
llxFooter();
$db->close();


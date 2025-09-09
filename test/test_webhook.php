<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * \file    test/test_webhook.php
 * \ingroup easycrm
 * \brief   Test script for Keyyo webhook
 */

// Load Dolibarr environment
if (file_exists(__DIR__ . '/../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../saturne/saturne.main.inc.php';
} elseif (file_exists(__DIR__ . '/../../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../../saturne/saturne.main.inc.php';
} else {
    die('Include of saturne main fails');
}

global $db, $langs, $user, $conf;

// Libraries
require_once __DIR__ . '/../lib/easycrm_function.lib.php';

// Translations
$langs->loadLangs(array("admin", "easycrm@easycrm"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

$page_name = "EasyCRM - Test Webhook";
llxHeader('', $page_name);

print load_fiche_titre($page_name, '', 'title_setup');

// Test parameters
$test_caller = GETPOST('caller', 'alpha') ?: '0123456789';
$test_callee = GETPOST('callee', 'alpha') ?: '0987654321';
$action = GETPOST('action', 'alpha');

if ($action == 'test') {
    print '<div class="info">';
    print '<h3>Test en cours...</h3>';
    print '<p><strong>Appelant :</strong> ' . $test_caller . '</p>';
    print '<p><strong>Appelé :</strong> ' . $test_callee . '</p>';
    print '</div>';
    
    // Test the function
    $result = get_and_show_contact($test_caller, $test_callee);
    
    print '<div class="result">';
    print '<h3>Résultats :</h3>';
    
    if ($result['user']) {
        print '<p><span class="badge badge-status4">✓</span> <strong>Utilisateur trouvé :</strong> ' . $result['user']->getFullName() . ' (' . $result['user']->login . ')</p>';
        print '<p><strong>Téléphones de l\'utilisateur :</strong></p>';
        print '<ul>';
        if ($result['user']->phone_pro) print '<li>Pro: ' . $result['user']->phone_pro . '</li>';
        if ($result['user']->phone_perso) print '<li>Perso: ' . $result['user']->phone_perso . '</li>';
        if ($result['user']->phone_mobile) print '<li>Mobile: ' . $result['user']->phone_mobile . '</li>';
        print '</ul>';
    } else {
        print '<p><span class="badge badge-status8">✗</span> <strong>Aucun utilisateur trouvé pour le numéro :</strong> ' . $test_callee . '</p>';
    }
    
    if ($result['contact']) {
        print '<p><span class="badge badge-status4">✓</span> <strong>Contact trouvé :</strong> ' . $result['contact']->getFullName() . '</p>';
        print '<p><strong>Téléphones du contact :</strong></p>';
        print '<ul>';
        if ($result['contact']->phone) print '<li>Fixe: ' . $result['contact']->phone . '</li>';
        if ($result['contact']->phone_mobile) print '<li>Mobile: ' . $result['contact']->phone_mobile . '</li>';
        print '</ul>';
        
        $contact_url = dol_buildpath('/contact/card.php?id=' . $result['contact']->id, 1);
        print '<p><a href="' . $contact_url . '" class="button" target="_blank">Voir la fiche contact</a></p>';
    } else {
        print '<p><span class="badge badge-status8">✗</span> <strong>Aucun contact trouvé pour le numéro :</strong> ' . $test_caller . '</p>';
    }
    
    if ($result['call_event_id']) {
        print '<p><span class="badge badge-status4">✓</span> <strong>Événement d\'appel créé avec l\'ID :</strong> ' . $result['call_event_id'] . '</p>';
    }
    
    print '</div>';
    
    // Debug information
    print '<div class="debug" style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 5px;">';
    print '<h4>Informations de debug :</h4>';
    print '<p><strong>Fin du numéro appelant (recherché) :</strong> ' . _phone_tail($test_caller) . '</p>';
    print '<p><strong>Fin du numéro appelé (recherché) :</strong> ' . _phone_tail($test_callee) . '</p>';
    print '</div>';
}

// Test form
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="test">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">Paramètres de test</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><label for="caller">Numéro appelant (contact à trouver) :</label></td>';
print '<td><input type="text" name="caller" id="caller" value="' . $test_caller . '" size="20" placeholder="Ex: 0123456789"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><label for="callee">Numéro appelé (utilisateur à trouver) :</label></td>';
print '<td><input type="text" name="callee" id="callee" value="' . $test_callee . '" size="20" placeholder="Ex: 0987654321"></td>';
print '</tr>';

print '</table>';

print '<div class="center" style="margin-top: 20px;">';
print '<input type="submit" class="button" value="Tester">';
print '</div>';

print '</form>';

// Information section
print '<br>';
print load_fiche_titre('Informations sur les utilisateurs et contacts', '', 'info');

// List users with phone numbers
print '<h4>Utilisateurs avec numéros de téléphone :</h4>';
$sql = "SELECT rowid, login, firstname, lastname, phone_pro, phone_perso, phone_mobile 
        FROM " . MAIN_DB_PREFIX . "user 
        WHERE (phone_pro != '' OR phone_perso != '' OR phone_mobile != '') 
        AND entity IN (" . getEntity('user') . ")
        ORDER BY login";

$resql = $db->query($sql);
if ($resql) {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>Login</td><td>Nom</td><td>Téléphones</td>';
    print '</tr>';
    
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>' . $obj->login . '</td>';
        print '<td>' . $obj->firstname . ' ' . $obj->lastname . '</td>';
        print '<td>';
        $phones = array();
        if ($obj->phone_pro) $phones[] = 'Pro: ' . $obj->phone_pro;
        if ($obj->phone_perso) $phones[] = 'Perso: ' . $obj->phone_perso;
        if ($obj->phone_mobile) $phones[] = 'Mobile: ' . $obj->phone_mobile;
        print implode('<br>', $phones);
        print '</td>';
        print '</tr>';
    }
    print '</table>';
} else {
    print '<p>Erreur lors de la récupération des utilisateurs</p>';
}

// List contacts with phone numbers
print '<h4>Contacts avec numéros de téléphone :</h4>';
$sql = "SELECT rowid, firstname, lastname, phone, phone_mobile 
        FROM " . MAIN_DB_PREFIX . "socpeople 
        WHERE (phone != '' OR phone_mobile != '') 
        AND entity IN (" . getEntity('contact') . ")
        ORDER BY lastname, firstname 
        LIMIT 20";

$resql = $db->query($sql);
if ($resql) {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>Nom</td><td>Téléphones</td>';
    print '</tr>';
    
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>' . $obj->firstname . ' ' . $obj->lastname . '</td>';
        print '<td>';
        $phones = array();
        if ($obj->phone) $phones[] = 'Fixe: ' . $obj->phone;
        if ($obj->phone_mobile) $phones[] = 'Mobile: ' . $obj->phone_mobile;
        print implode('<br>', $phones);
        print '</td>';
        print '</tr>';
    }
    print '</table>';
    print '<p><em>Seuls les 20 premiers contacts sont affichés</em></p>';
} else {
    print '<p>Erreur lors de la récupération des contacts</p>';
}

// Webhook URL information
print '<br>';
print load_fiche_titre('URL du Webhook', '', 'info');
print '<p><strong>URL à configurer dans Keyyo :</strong></p>';
print '<code>' . dol_buildpath('/custom/easycrm/webhook/keyyo_webhook.php', 2);
if (getDolGlobalString('EASY_CRM_KEYYO_EXPECTED_TOKEN')) {
    print '?token=' . getDolGlobalString('EASY_CRM_KEYYO_EXPECTED_TOKEN');
}
print '</code>';

// End of page
llxFooter(); 
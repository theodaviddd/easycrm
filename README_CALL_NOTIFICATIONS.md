# EasyCRM - Système de Notifications d'Appel Keyyo

Ce système permet d'afficher automatiquement la fiche d'un contact dans Dolibarr lorsqu'un appel téléphonique est reçu via Keyyo.

## 🎯 Fonctionnalités

- **Notification en temps réel** : Affichage d'une notification dans le navigateur lors d'un appel entrant
- **Ouverture automatique** : Possibilité d'ouvrir automatiquement la fiche du contact
- **Correspondance intelligente** : Matching des numéros de téléphone entre l'appelant et les contacts Dolibarr
- **Identification de l'utilisateur** : Identification automatique de l'utilisateur appelé
- **Configuration flexible** : Paramètres configurables via l'interface d'administration
- **Sécurité** : Token de sécurité pour protéger le webhook

## 🔧 Installation

### 1. Base de données

La table nécessaire doit être créée en base de données :

```sql
-- Exécuter le contenu du fichier sql/llx_easycrm_call_events.sql
CREATE TABLE IF NOT EXISTS llx_easycrm_call_events (
    rowid int(11) NOT NULL AUTO_INCREMENT,
    fk_user int(11) NOT NULL,
    fk_contact int(11) NOT NULL,
    caller varchar(50) NOT NULL,
    callee varchar(50) NOT NULL,
    call_date datetime NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'new',
    date_creation datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rowid),
    KEY idx_fk_user (fk_user),
    KEY idx_fk_contact (fk_contact),
    KEY idx_status (status),
    KEY idx_call_date (call_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 2. Configuration Keyyo

Dans votre interface Keyyo, configurez le webhook avec :

- **URL** : `https://votre-domaine.com/custom/easycrm/webhook/keyyo_webhook.php`
- **Méthode** : POST ou GET
- **Paramètres** : 
  - `caller` : numéro de l'appelant
  - `callee` : numéro de l'appelé

### 3. Configuration Dolibarr

Accédez à la page de configuration :
`Administration > EasyCRM > Notifications d'appel`

Ou directement : `/custom/easycrm/admin/call_notifications.php`

## ⚙️ Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| **Notifications désactivées** | Désactive globalement les notifications | Non |
| **Fréquence de vérification** | Intervalle de vérification des nouveaux appels (2-60s) | 5 secondes |
| **Ouverture automatique** | Ouvre automatiquement la fiche contact | Non |
| **Nouvel onglet** | Ouvre dans un nouvel onglet | Oui |
| **Token de sécurité** | Token pour sécuriser le webhook | Vide |

### Variables de configuration

Ces variables peuvent aussi être définies directement en base :

```php
// Désactiver les notifications
$conf->global->EASYCRM_CALL_NOTIFICATIONS_DISABLED = 1;

// Fréquence de vérification (secondes)
$conf->global->EASYCRM_CALL_CHECK_FREQUENCY = 5;

// Ouverture automatique
$conf->global->EASYCRM_AUTO_OPEN_CONTACT = 1;

// Nouvel onglet
$conf->global->EASYCRM_OPEN_IN_NEW_TAB = 1;

// Token de sécurité
$conf->global->EASY_CRM_KEYYO_EXPECTED_TOKEN = 'votre-token-secret';
```

## 🔄 Fonctionnement

### 1. Réception de l'appel
1. Keyyo envoie une requête au webhook avec les numéros `caller` et `callee`
2. Le webhook identifie l'utilisateur Dolibarr correspondant au `callee`
3. Le webhook identifie le contact correspondant au `caller`
4. Un événement d'appel est stocké en base de données

### 2. Notification côté client
1. Le JavaScript vérifie périodiquement les nouveaux événements d'appel
2. Une notification jNotify s'affiche avec les informations du contact
3. Optionnellement, la fiche contact s'ouvre automatiquement
4. L'événement est marqué comme traité

### 3. Correspondance des numéros
Le système utilise une correspondance intelligente :
- Normalisation des numéros (suppression des espaces, tirets, etc.)
- Comparaison sur les 9 derniers chiffres pour plus de robustesse
- Support des formats internationaux

## 📁 Structure des fichiers

```
htdocs/custom/easycrm/
├── webhook/
│   └── keyyo_webhook.php          # Point d'entrée du webhook Keyyo
├── ajax/
│   └── check_call_events.php      # Endpoint AJAX pour vérifier les nouveaux appels
├── js/
│   └── call_notifications.js.php  # JavaScript des notifications côté client
├── admin/
│   └── call_notifications.php     # Page de configuration
├── class/
│   └── actions_easycrm.class.php  # Hook pour inclure le JavaScript
├── lib/
│   └── easycrm_function.lib.php   # Fonctions de traitement des appels
├── sql/
│   └── llx_easycrm_call_events.sql # Script de création de table
├── sounds/
│   └── README.md                  # Instructions pour ajouter un son
└── langs/fr_FR/
    └── easycrm.lang               # Traductions françaises
```

## 🔐 Sécurité

### Token de sécurité
Il est fortement recommandé de configurer un token de sécurité :

1. Générez un token alééatoire (ex: `openssl rand -hex 32`)
2. Configurez-le dans Dolibarr
3. Ajoutez-le à l'URL du webhook Keyyo : `?token=votre-token`

### Logs
Les événements sont loggés dans :
`htdocs/custom/easycrm/webhook/keyyo_webhook.log`

## 🎵 Son de notification (optionnel)

Pour ajouter un son lors des notifications :

1. Placez un fichier `phone-ring.mp3` dans le dossier `sounds/`
2. Format recommandé : MP3, 2-5 secondes, 128 kbps

## 🐛 Dépannage

### Les notifications ne s'affichent pas
1. Vérifiez que les notifications ne sont pas désactivées
2. Vérifiez la console JavaScript pour les erreurs
3. Vérifiez que l'utilisateur a des numéros de téléphone configurés
4. Vérifiez les logs du webhook

### Le contact n'est pas trouvé
1. Vérifiez que le contact a un numéro de téléphone configuré
2. Vérifiez le format des numéros (avec/sans indicatifs)
3. Consultez les logs pour voir les numéros recherchés

### Le webhook ne reçoit pas les appels
1. Vérifiez l'URL du webhook dans Keyyo
2. Vérifiez le token de sécurité
3. Consultez les logs du serveur web

## 📊 Monitoring

### Vérification des événements d'appel
```sql
-- Derniers événements d'appel
SELECT * FROM llx_easycrm_call_events 
ORDER BY call_date DESC 
LIMIT 10;

-- Statistiques par utilisateur
SELECT u.login, COUNT(*) as nb_calls 
FROM llx_easycrm_call_events ce 
JOIN llx_user u ON ce.fk_user = u.rowid 
GROUP BY u.login;
```

### Nettoyage des anciens événements
```sql
-- Supprimer les événements de plus de 30 jours
DELETE FROM llx_easycrm_call_events 
WHERE call_date < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

## 🔄 Mises à jour

Lors des mises à jour d'EasyCRM, vérifiez :
1. La compatibilité de la structure de base de données
2. Les nouveaux paramètres de configuration
3. Les modifications des hooks Dolibarr

## 📞 Support

Pour toute question ou problème :
1. Consultez les logs du webhook
2. Vérifiez la configuration dans l'administration
3. Testez manuellement l'URL du webhook
4. Contactez le support technique 
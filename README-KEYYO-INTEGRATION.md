# Intégration Keyyo CTI - EasyCRM

Cette intégration permet de connecter Keyyo CTI avec le module EasyCRM de Dolibarr pour recevoir les événements d'appels entrants.

## Configuration OAuth

Les paramètres OAuth sont préconfigurés dans le fichier JavaScript :

- **Client ID** : `68b6b2440fe6a`
- **Client Secret** : `725a1c4d5333171121b35d67`
- **Authorize URL** : `https://ssl.keyyo.com/oauth2/authorize.php`
- **Access Token URL** : `https://api.keyyo.com/oauth2/token.php`

## Fonctionnalités

### 1. Test d'intégration sur la page admin

Accédez à `/custom/easycrm/admin/setup.php` pour voir la section "Test Keyyo CTI" qui propose :

- **Tester la connexion Keyyo** : Charge le SDK et teste l'initialisation
- **Tester OAuth** : Vérifie les endpoints OAuth et génère l'URL d'autorisation
- **Afficher la configuration** : Montre les paramètres actuels

### 2. Page de test autonome

Un fichier `test-keyyo.html` est disponible pour tester le SDK de manière isolée :

- Interface simple avec boutons de test
- Console de debug en temps réel
- Simulation d'appels entrants
- Tests de chargement du SDK

## Utilisation

### Sur la page d'administration

1. Allez dans **EasyCRM > Configuration** (`/custom/easycrm/admin/setup.php`)
2. Faites défiler jusqu'à la section "Test Keyyo CTI"
3. Cliquez sur "Tester la connexion Keyyo"
4. Vérifiez les messages dans la zone de résultats

### Test autonome

1. Ouvrez `test-keyyo.html` dans votre navigateur
2. La page charge automatiquement le SDK au démarrage
3. Utilisez les boutons pour tester les différentes fonctionnalités
4. Consultez la console de debug pour voir les détails

## Architecture technique

### Fichiers

- `js/keyyo-admin.js` : Script d'intégration pour la page admin
- `test-keyyo.html` : Page de test autonome
- `admin/setup.php` : Page d'administration modifiée pour inclure le script

### Fonctionnement

1. **Chargement du SDK** : Chargement depuis `https://api.keyyo.com/libs/keyyo-cti/1.1/keyyo-cti.min.js`
2. **Flux OAuth2** : Obtention d'un access token via le flux d'autorisation
3. **CSI Token** : Récupération d'un CSI token avec l'access token
4. **Session CTI** : Appel de `create_session(csi_token)` pour se connecter
5. **Événements** : Configuration des handlers pour les appels entrants

## Événements gérés

- `onReady` : SDK initialisé et prêt
- `onError` : Erreur de connexion ou d'authentification
- `onIncomingCall` : Appel entrant détecté

## Debugging

### Console navigateur

Tous les événements sont loggés dans la console avec des timestamps :
```
[14:30:15] Keyyo Admin Integration starting...
[14:30:16] SDK Keyyo chargé avec succès
[14:30:17] ✓ Connexion Keyyo réussie!
```

### Messages d'état

L'interface affiche des messages colorés :
- 🟢 **Vert** : Succès
- 🔴 **Rouge** : Erreur
- 🟡 **Jaune** : Avertissement
- 🔵 **Bleu** : Information

## Dépannage

### SDK ne se charge pas

1. Vérifiez la connectivité internet
2. Consultez la console pour voir les URLs tentées
3. Vérifiez que Keyyo n'a pas changé l'emplacement du SDK

### Erreur d'authentification

1. Vérifiez que le Client ID est correct
2. Assurez-vous que le Client Secret correspond
3. Vérifiez que les URLs OAuth sont accessibles

### Pas d'événements d'appel

1. Vérifiez que le SDK est bien initialisé
2. Testez avec la simulation d'appel
3. Vérifiez la configuration Keyyo côté serveur

## Développement

Pour étendre cette intégration :

1. Modifiez `keyyo-admin.js` pour ajouter de nouvelles fonctionnalités
2. Ajoutez des handlers pour d'autres types d'événements Keyyo
3. Intégrez avec la base de données Dolibarr pour persister les appels

## Support

En cas de problème :
1. Consultez la console navigateur
2. Utilisez la page de test pour isoler le problème
3. Vérifiez la documentation officielle Keyyo CTI 
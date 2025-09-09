/**
 * Keyyo CTI Integration for EasyCRM Admin
 *
 * This script integrates with the Keyyo CTI JavaScript SDK for testing
 * and initialization on the admin setup page.
 */

class KeyyoAdminIntegration {
    constructor() {
        this.config = {
            clientId: '68b6b2440fe6a',
            clientSecret: '725a1c4d5333171121b35d67',
            authorizeUrl: 'https://ssl.keyyo.com/oauth2/authorize.php',
            tokenUrl: 'https://api.keyyo.com/oauth2/token.php'
        };

        this.isInitialized = false;
        this.keyyoSDK = null;

        console.log('Keyyo Admin Integration starting...');
        this.init();
    }

    /**
     * Initialize the Keyyo CTI integration
     */
    async init() {
        try {
            // Add test button to the page
            this.addTestButton();

            // Load Keyyo SDK
            await this.loadKeyyoSDK();

            console.log('Keyyo Admin Integration initialized successfully');

        } catch (error) {
            console.error('Failed to initialize Keyyo Admin Integration:', error);
            this.showMessage('Erreur d\'initialisation Keyyo: ' + error.message, 'error');
        }
    }

    /**
     * Load Keyyo SDK dynamically
     */
    async loadKeyyoSDK() {
        return new Promise((resolve, reject) => {
            // Check if SDK is already loaded
            if (window.KeyyoCTI || window.Keyyo) {
                console.log('Keyyo SDK already loaded');
                resolve();
                return;
            }

            // Try multiple possible SDK URLs
            const sdkUrls = [
                'https://api.keyyo.com/libs/keyyo-cti/1.1/keyyo-cti.min.js',
                'https://cdn.keyyo.com/js/keyyo-cti.js',
                'https://api.keyyo.com/js/sdk.js'
            ];

            let currentUrlIndex = 0;

            const tryLoadSDK = () => {
                if (currentUrlIndex >= sdkUrls.length) {
                    reject(new Error('Failed to load Keyyo SDK from any URL'));
                    return;
                }

                const script = document.createElement('script');
                script.src = sdkUrls[currentUrlIndex];

                script.onload = () => {
                    console.log('Keyyo SDK loaded successfully from:', sdkUrls[currentUrlIndex]);
                    this.showMessage('SDK Keyyo chargé avec succès', 'success');
                    resolve();
                };

                script.onerror = () => {
                    console.warn('Failed to load SDK from:', sdkUrls[currentUrlIndex]);
                    currentUrlIndex++;
                    tryLoadSDK();
                };

                document.head.appendChild(script);
            };

            tryLoadSDK();
        });
    }

        /**
     * Test Keyyo CTI connection - Complete flow
     */
    async testConnection() {
        try {
            this.showMessage('🔄 Connexion automatique à Keyyo en cours...', 'info');
            
            // Step 1: Check SDK
            if (!window.Keyyo) {
                throw new Error('SDK Keyyo non disponible');
            }
            this.showMessage('✓ SDK Keyyo disponible', 'success');

            // Step 2: Get access token using client credentials
            this.showMessage('🔑 Authentification OAuth2...', 'info');
            const accessToken = await this.getAccessTokenClientCredentials();
            
            // Step 3: Get CSI list
            this.showMessage('📋 Récupération de la liste des CSI...', 'info');
            const csiList = await this.getCSIList(accessToken);
            
            // Step 4: Initialize CTI with first available CSI
            if (csiList && csiList.length > 0) {
                this.showMessage('✓ CSI disponibles trouvés!', 'success');
                this.displayCSIList(csiList);
                
                // Try to connect with first CSI
                await this.connectWithCSI(csiList[0], accessToken);
            } else {
                this.showMessage('⚠ Aucun CSI disponible', 'warning');
            }
            
        } catch (error) {
            console.error('Keyyo connection test failed:', error);
            this.showMessage('✗ Connexion échouée: ' + error.message, 'error');
        }
    }

    /**
     * Get access token using client credentials flow
     */
    async getAccessTokenClientCredentials() {
        try {
            const tokenData = {
                grant_type: 'client_credentials',
                client_id: this.config.clientId,
                client_secret: this.config.clientSecret
            };

            const response = await fetch(this.config.tokenUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(tokenData)
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }

            const tokenResponse = await response.json();
            console.log('Token response:', tokenResponse);
            
            if (tokenResponse.access_token) {
                this.showMessage('✓ Access token obtenu!', 'success');
                return tokenResponse.access_token;
            } else {
                throw new Error('Pas d\'access token dans la réponse');
            }
            
        } catch (error) {
            console.error('Token request failed:', error);
            throw new Error('Échec authentification OAuth2: ' + error.message);
        }
    }

    /**
     * Get list of available CSI
     */
    async getCSIList(accessToken) {
        try {
            // Try different possible endpoints for CSI list
            const possibleEndpoints = [
                'https://api.keyyo.com/rest/manager/csi',
                'https://api.keyyo.com/rest/manager/csi/list',
                'https://api.keyyo.com/rest/manager/user/csi'
            ];

            for (const endpoint of possibleEndpoints) {
                try {
                    console.log(`Trying CSI endpoint: ${endpoint}`);
                    
                    const response = await fetch(endpoint, {
                        method: 'GET',
                        headers: {
                            'Authorization': 'Bearer ' + accessToken,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        const csiData = await response.json();
                        console.log('CSI response:', csiData);
                        
                        // Handle different response formats
                        if (Array.isArray(csiData)) {
                            return csiData;
                        } else if (csiData.csi) {
                            return Array.isArray(csiData.csi) ? csiData.csi : [csiData.csi];
                        } else if (csiData.data) {
                            return Array.isArray(csiData.data) ? csiData.data : [csiData.data];
                        } else {
                            return [csiData];
                        }
                    }
                } catch (e) {
                    console.log(`Endpoint ${endpoint} failed:`, e.message);
                }
            }
            
            throw new Error('Aucun endpoint CSI accessible');
            
        } catch (error) {
            console.error('CSI list failed:', error);
            throw new Error('Récupération liste CSI échouée: ' + error.message);
        }
    }

    /**
     * Display CSI list in the interface
     */
    displayCSIList(csiList) {
        const resultsDiv = document.getElementById('keyyo-test-results');
        
        const csiHtml = `
            <div class="keyyo-message keyyo-message-success" style="margin-top: 15px;">
                <h4>📋 CSI disponibles (${csiList.length}):</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    ${csiList.map(csi => {
                        const csiNumber = csi.number || csi.csi || csi.id || csi;
                        const csiName = csi.name || csi.label || '';
                        return `<li><strong>${csiNumber}</strong> ${csiName ? '- ' + csiName : ''}</li>`;
                    }).join('')}
                </ul>
            </div>
        `;
        
        resultsDiv.innerHTML += csiHtml;
    }

    /**
     * Connect to CTI with specific CSI
     */
    async connectWithCSI(csi, accessToken) {
        try {
            this.showMessage('🔌 Connexion CTI avec CSI...', 'info');
            
            // Get CSI token for this specific CSI
            const csiToken = await this.getCSIToken(accessToken, csi);
            
            // Initialize CTI
            const cti = new window.Keyyo.CTI();
            this.keyyoSDK = cti;
            
            // Create session
            return new Promise((resolve, reject) => {
                cti.create_session(csiToken, (err, res) => {
                    if (err) {
                        console.error('CTI session error:', err);
                        this.showMessage('✗ Erreur session CTI: ' + (err.message || err), 'error');
                        reject(err);
                        return;
                    }

                    console.log('CTI session created:', res);
                    this.showMessage('✅ Session CTI créée avec succès!', 'success');
                    this.showMessage(`📞 Connecté au CSI: ${res.number}`, 'info');
                    this.showMessage(`🆔 Session ID: ${res.session_id}`, 'info');
                    this.isInitialized = true;
                    
                    // Auto-setup call events
                    this.setupCallEvents();
                    
                    resolve(res);
                });
            });
            
        } catch (error) {
            console.error('CSI connection failed:', error);
            this.showMessage('✗ Connexion CSI échouée: ' + error.message, 'error');
            throw error;
        }
    }

    /**
     * Setup call event handlers automatically
     */
    setupCallEvents() {
        try {
            if (!this.keyyoSDK) return;

            this.keyyoSDK.onCall = (call) => {
                console.log('📞 Nouvel appel reçu:', call);
                this.showMessage('📞 APPEL ENTRANT DÉTECTÉ!', 'success');
                this.showMessage(`👤 ${call.caller} → ${call.callee}`, 'info');
                this.showMessage(`📊 État: ${call.state}`, 'info');

                // Setup call event handlers
                call.onSetup = () => {
                    console.log('🔔 Call setup:', call.callref);
                    this.showMessage('🔔 Téléphone sonne...', 'info');
                };

                call.onConnect = () => {
                    console.log('✅ Call connected:', call.callref);
                    this.showMessage('✅ Appel connecté!', 'success');
                };

                call.onRelease = () => {
                    console.log('📴 Call released:', call.callref);
                    this.showMessage('📴 Appel terminé', 'info');
                };

                call.onMissed = () => {
                    console.log('📵 Call missed:', call.callref);
                    this.showMessage('📵 Appel manqué', 'warning');
                };
            };

            this.showMessage('🎧 Gestionnaires d\'événements configurés', 'success');
            this.showMessage('⏳ En attente d\'appels entrants...', 'info');
            
        } catch (error) {
            console.error('Event setup failed:', error);
        }
    }

    

    /**
     * Get CSI token for specific CSI using access token
     */
    async getCSIToken(accessToken, csi) {
        try {
            const csiNumber = csi.number || csi.csi || csi.id || csi;
            console.log(`Getting CSI token for: ${csiNumber}`);
            
            // Try different possible endpoints for CSI token
            const possibleEndpoints = [
                `https://api.keyyo.com/rest/manager/csi/${csiNumber}/token`,
                `https://api.keyyo.com/rest/manager/csi/token?csi=${csiNumber}`,
                'https://api.keyyo.com/rest/manager/csi/token'
            ];

            for (const endpoint of possibleEndpoints) {
                try {
                    console.log(`Trying CSI token endpoint: ${endpoint}`);
                    
                    const response = await fetch(endpoint, {
                        method: 'GET',
                        headers: {
                            'Authorization': 'Bearer ' + accessToken,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        const tokenResponse = await response.json();
                        console.log('CSI token response:', tokenResponse);
                        
                        // Handle different response formats
                        const token = tokenResponse.token || tokenResponse.csi_token || tokenResponse.access_token || tokenResponse;
                        
                        if (typeof token === 'string') {
                            this.showMessage('✓ CSI token obtenu!', 'success');
                            return token;
                        }
                    }
                } catch (e) {
                    console.log(`CSI token endpoint ${endpoint} failed:`, e.message);
                }
            }
            
            throw new Error('Impossible d\'obtenir le CSI token');
            
        } catch (error) {
            console.error('CSI token failed:', error);
            throw new Error('Récupération CSI token échouée: ' + error.message);
        }
    }



    /**
     * Add test button to the admin page
     */
    addTestButton() {
        // Create test section
        const testSection = document.createElement('div');
        testSection.id = 'keyyo-test-section';
        testSection.innerHTML = `
            <div class="titre" style="margin-top: 20px;">Test Keyyo CTI</div>
            <div class="tabsAction">
                <button type="button" id="keyyo-test-connection" class="butAction" style="font-size: 14px; padding: 12px 20px;">
                    🚀 Connecter à Keyyo CTI
                </button>
                <button type="button" id="keyyo-show-config" class="butActionSecondary">
                    📋 Voir la configuration
                </button>
            </div>
            <div id="keyyo-test-results" style="margin-top: 15px;"></div>
        `;

        // Find a good place to insert the test section
        const form = document.querySelector('form[name="quickcreation_api"]');
        if (form) {
            form.parentNode.insertBefore(testSection, form.nextSibling);
        } else {
            // Fallback: append to body
            document.body.appendChild(testSection);
        }

        // Show welcome message
        setTimeout(() => {
            this.showMessage('👋 Prêt à tester l\'intégration Keyyo CTI!', 'info');
            this.showMessage('Cliquez sur "🚀 Connecter à Keyyo CTI" pour commencer', 'info');
        }, 500);
        // Add event listeners
        document.getElementById('keyyo-test-connection').addEventListener('click', () => {
            this.testConnection();
        });

        document.getElementById('keyyo-show-config').addEventListener('click', () => {
            this.showConfiguration();
        });
    }

        /**
     * Show configuration details
     */
    showConfiguration() {
        const status = this.getStatus();
        const configHtml = `
            <div class="keyyo-message keyyo-message-info" style="margin-top: 10px;">
                <h4>📋 Configuration Keyyo CTI:</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>Client ID:</strong> ${this.config.clientId}</li>
                    <li><strong>Client Secret:</strong> ${this.config.clientSecret.substring(0, 8)}***</li>
                    <li><strong>Authorize URL:</strong> ${this.config.authorizeUrl}</li>
                    <li><strong>Token URL:</strong> ${this.config.tokenUrl}</li>
                    <li><strong>SDK Status:</strong> ${status.sdkLoaded ? '✅ Chargé' : '❌ Non chargé'}</li>
                    <li><strong>Connexion CTI:</strong> ${this.isInitialized ? '✅ Connecté' : '❌ Déconnecté'}</li>
                    ${this.isInitialized ? '<li><strong>Événements:</strong> ✅ Configurés</li>' : ''}
                </ul>
                ${!this.isInitialized ? '<p><em>👆 Cliquez sur "Connecter à Keyyo CTI" pour vous connecter</em></p>' : ''}
            </div>
        `;
        
        const resultsDiv = document.getElementById('keyyo-test-results');
        resultsDiv.innerHTML = configHtml;
    }

    /**
     * Show message in the results area
     */
    showMessage(message, type = 'info') {
        const resultsDiv = document.getElementById('keyyo-test-results');
        if (!resultsDiv) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = `keyyo-message keyyo-message-${type}`;
        messageDiv.innerHTML = `
            <span>${message}</span>
            <small style="float: right;">${new Date().toLocaleTimeString()}</small>
        `;

        // Clear previous messages of the same type or add new one
        const existingMessage = resultsDiv.querySelector(`.keyyo-message-${type}`);
        if (existingMessage) {
            existingMessage.remove();
        }

        resultsDiv.appendChild(messageDiv);

        // Auto-remove info messages after 5 seconds
        if (type === 'info') {
            setTimeout(() => {
                if (messageDiv.parentElement) {
                    messageDiv.remove();
                }
            }, 5000);
        }

        console.log(`Keyyo ${type}:`, message);
    }



    /**
     * Get current status
     */
    getStatus() {
        return {
            initialized: this.isInitialized,
            sdkLoaded: !!(window.Keyyo),
            config: this.config
        };
    }
}

// CSS Styles for the test interface
const style = document.createElement('style');
style.textContent = `
    .keyyo-message {
        padding: 10px;
        margin: 5px 0;
        border-radius: 4px;
        border-left: 4px solid #ccc;
        background: #f9f9f9;
        font-size: 13px;
        clear: both;
    }

    .keyyo-message-success {
        border-left-color: #28a745;
        background: #d4edda;
        color: #155724;
    }

    .keyyo-message-error {
        border-left-color: #dc3545;
        background: #f8d7da;
        color: #721c24;
    }

    .keyyo-message-warning {
        border-left-color: #ffc107;
        background: #fff3cd;
        color: #856404;
    }

    .keyyo-message-info {
        border-left-color: #17a2b8;
        background: #d1ecf1;
        color: #0c5460;
    }

    #keyyo-test-section {
        border: 1px solid #ddd;
        padding: 15px;
        margin: 20px 0;
        border-radius: 4px;
        background: #fafafa;
    }

    #keyyo-test-section .titre {
        color: #333;
        font-weight: bold;
        margin-bottom: 10px;
    }
`;

document.head.appendChild(style);

// Initialize when DOM is ready
let keyyoAdmin;

document.addEventListener('DOMContentLoaded', function() {
    // Only initialize on admin setup page
    if (window.location.pathname.includes('/admin/setup.php') ||
        window.location.pathname.includes('/easycrm/admin/setup.php')) {

        console.log('Initializing Keyyo Admin Integration...');
        keyyoAdmin = new KeyyoAdminIntegration();

        // Make it globally available for debugging
        window.keyyoAdmin = keyyoAdmin;
    }
});

// Export for potential external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = KeyyoAdminIntegration;
}

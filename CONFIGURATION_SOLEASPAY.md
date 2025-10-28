# Configuration SoleasPay pour NVIDIA Technology Platform

## Configuration du Webhook (Obligatoire pour la validation automatique)

Pour que les dépôts soient validés automatiquement, vous devez configurer le webhook dans votre dashboard SoleasPay:

### Étapes de configuration:

1. **Accéder au dashboard SoleasPay**
   - URL: https://soleaspay.com
   - Connectez-vous avec vos identifiants

2. **Configurer l'URL du callback**
   - Allez dans: Paramètres > API > Configuration Callback
   - URL du callback: `https://votre-domaine-replit.repl.co/soleaspay_callback.php`
   - Remplacez `votre-domaine-replit` par votre vrai domaine Replit

3. **Récupérer la clé de sécurité**
   - Après configuration, SoleasPay génère une clé secrète (hash SHA-512)
   - Cette clé est envoyée dans le header `x-private-key` de chaque callback
   - **Note**: La vérification de cette clé n'est pas encore implémentée dans le webhook actuel

4. **Tester le webhook**
   - Faites un dépôt test
   - Vérifiez les logs dans `soleaspay_callback.log`
   - Le webhook reçoit une notification JSON avec:
     ```json
     {
       "success": true/false,
       "status": "SUCCESS" | "RECEIVED" | "PROCESSING" | "REFUND",
       "data": {
         "reference": "MLS2021B",
         "external_reference": "DEP_123_...",
         "amount": 1000,
         "currency": "XOF"
       }
     }
     ```

## Système de validation à 3 niveaux

### 1. Validation immédiate (automatique)
- Après 3 secondes, le système tente de vérifier le paiement
- Si SoleasPay confirme immédiatement, le compte est crédité instantanément

### 2. Validation via webhook (automatique)
- SoleasPay envoie une notification au webhook quand le paiement est confirmé
- Le webhook met à jour le statut et crédite le compte automatiquement
- **Nécessite la configuration du callback dans le dashboard SoleasPay**

### 3. Validation manuelle (par l'administrateur)
- Si les 2 méthodes automatiques échouent, le dépôt reste "en attente"
- L'admin peut valider ou rejeter manuellement via `/adminxyz.php`
- Utile pour les cas exceptionnels ou problèmes techniques

## Dépannage

### Le webhook ne reçoit pas de notifications
1. Vérifiez que l'URL du callback est bien configurée dans le dashboard SoleasPay
2. Vérifiez que l'URL est accessible publiquement (pas localhost)
3. Consultez les logs: `soleaspay_callback.log`

### Les dépôts restent en attente
1. Vérifiez la configuration du webhook
2. Validez manuellement via l'interface admin pour débloquer les utilisateurs
3. Contactez le support SoleasPay si le problème persiste

## Sécurité

- Le webhook log toutes les transactions dans `soleaspay_callback.log`
- Protection contre les doublons (vérifie si le dépôt est déjà validé)
- Protection contre la dégradation de statut (ne rejette pas un dépôt déjà validé)
- **À implémenter**: Vérification du header `x-private-key` pour plus de sécurité

## API SoleasPay utilisée

- **Endpoint**: `https://soleaspay.com/api/agent/bills/v3`
- **Méthode**: POST
- **Headers**:
  - `x-api-key`: Votre clé API
  - `operation`: 2 (Pay-In)
  - `service`: ID du service mobile money (1-12)

- **Endpoint de vérification**: `https://soleaspay.com/api/agent/verif-pay`
- **Méthode**: GET
- **Paramètres**: orderId, payId

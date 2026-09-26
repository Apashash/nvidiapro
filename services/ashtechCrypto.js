const CRYPTO_PENDING_TTL_MS = 15 * 60 * 1000;
const CRYPTO_STATUS_GRACE_MS = 5 * 60 * 1000;

function normalizeAshtechCryptoAssets(payload) {
  if (!payload || !Array.isArray(payload.assets)) {
    throw new Error('Réponse du catalogue crypto AshTechPay invalide');
  }

  const assets = payload.assets.map(asset => {
    if (!asset || typeof asset !== 'object' || Array.isArray(asset)) {
      return null;
    }

    const normalized = {
      asset_code: typeof asset.asset_code === 'string' ? asset.asset_code.trim() : '',
      coin: typeof asset.coin === 'string' ? asset.coin.trim() : '',
      name: typeof asset.name === 'string' ? asset.name.trim() : '',
      network: typeof asset.network === 'string' ? asset.network.trim() : '',
      network_label: typeof asset.network_label === 'string' ? asset.network_label.trim() : '',
      memo_required: asset.memo_required === true,
      memo_type: typeof asset.memo_type === 'string' && asset.memo_type.trim()
        ? asset.memo_type.trim()
        : null,
      currency: typeof asset.currency === 'string' ? asset.currency.trim().toUpperCase() : '',
    };

    if (!normalized.asset_code || !normalized.coin || !normalized.name
        || !normalized.network || !normalized.network_label || !normalized.currency) {
      return null;
    }

    if (asset.memo_type !== null && asset.memo_type !== undefined
        && typeof asset.memo_type !== 'string') {
      return null;
    }

    return normalized;
  }).filter(Boolean);

  if (!assets.length) {
    throw new Error('Le catalogue crypto AshTechPay ne contient aucun actif valide');
  }

  return assets;
}

function findAshtechCryptoAsset(assets, assetCode) {
  if (!Array.isArray(assets) || typeof assetCode !== 'string') return null;
  return assets.find(asset => asset.asset_code === assetCode) || null;
}

function calculateUsdtAmount(fiatAmount, fcfaPerUsdt) {
  const amount = Number(fiatAmount);
  const rate = Number(fcfaPerUsdt);
  if (!Number.isFinite(amount) || amount <= 0 || !Number.isFinite(rate) || rate <= 0) {
    throw new Error('Montant ou taux de conversion invalide');
  }

  const cents = Math.round((amount / rate) * 100);
  if (cents <= 0) {
    throw new Error('Le montant est trop faible pour être converti en USDT');
  }

  return (cents / 100).toFixed(2);
}

function calculateManualUsdtWithdrawalAmount(netFiatAmount, fcfaPerUsdt) {
  const amount = Number(netFiatAmount);
  const rate = Number(fcfaPerUsdt);
  if (!Number.isFinite(amount) || amount <= 0 || !Number.isFinite(rate) || rate <= 0) {
    throw new Error('Montant net ou taux de conversion invalide');
  }

  const cents = Math.floor((amount / rate) * 100 + 1e-9);
  if (cents <= 0) {
    throw new Error('Le montant net est trop faible pour un retrait USDT');
  }

  return (cents / 100).toFixed(2);
}

function getManualUsdtWithdrawalAssets(assets) {
  if (!Array.isArray(assets)) return [];
  return assets.filter(asset =>
    asset
      && String(asset.coin || '').trim().toUpperCase() === 'USDT'
      && asset.memo_required !== true
  );
}

function normalizeAshtechCryptoCollectResponse(payment, selectedAsset) {
  if (!payment || typeof payment !== 'object' || Array.isArray(payment)) {
    throw new Error('Réponse de création crypto AshTechPay invalide');
  }

  const requiredStrings = [
    'transaction_id',
    'reference',
    'status',
    'payment_method',
    'asset_code',
    'network',
    'address',
    'currency',
  ];
  for (const field of requiredStrings) {
    if (typeof payment[field] !== 'string' || !payment[field].trim()) {
      throw new Error(`Réponse crypto AshTechPay sans champ ${field} valide`);
    }
  }

  if (!['pending', 'completed', 'failed'].includes(payment.status)) {
    throw new Error('Statut de création crypto AshTechPay non documenté');
  }
  if (payment.payment_method !== 'crypto') {
    throw new Error('AshTechPay a retourné une méthode de paiement inattendue');
  }
  if (!selectedAsset
      || payment.asset_code !== selectedAsset.asset_code
      || payment.network !== selectedAsset.network) {
    throw new Error('Le réseau retourné par AshTechPay ne correspond pas à celui sélectionné');
  }
  if (payment.currency !== 'USDT') {
    throw new Error('AshTechPay a retourné une devise inattendue pour le dépôt crypto');
  }
  if (selectedAsset.memo_required
      && (typeof payment.memo !== 'string' || !payment.memo.trim())) {
    throw new Error('AshTechPay n’a pas retourné le memo/tag requis par ce réseau');
  }

  for (const field of ['amount', 'amount_usdt']) {
    if (!Number.isFinite(Number(payment[field])) || Number(payment[field]) <= 0) {
      throw new Error(`Réponse crypto AshTechPay sans montant ${field} valide`);
    }
  }

  for (const field of ['memo', 'memo_type']) {
    if (payment[field] !== null && payment[field] !== undefined
        && typeof payment[field] !== 'string') {
      throw new Error(`Réponse crypto AshTechPay sans champ ${field} valide`);
    }
  }

  for (const field of ['expires_at', 'created_at']) {
    if (payment[field] !== null && payment[field] !== undefined
        && (typeof payment[field] !== 'string' || !Number.isFinite(Date.parse(payment[field])))) {
      throw new Error(`Réponse crypto AshTechPay sans date ${field} valide`);
    }
  }

  return {
    transaction_id: payment.transaction_id.trim(),
    reference: payment.reference.trim(),
    merchant_reference: typeof payment.merchant_reference === 'string'
      ? payment.merchant_reference.trim()
      : null,
    status: payment.status,
    payment_method: payment.payment_method,
    asset_code: payment.asset_code.trim(),
    network: payment.network.trim(),
    address: payment.address.trim(),
    memo: typeof payment.memo === 'string' && payment.memo.trim() ? payment.memo.trim() : null,
    memo_type: typeof payment.memo_type === 'string' && payment.memo_type.trim()
      ? payment.memo_type.trim()
      : null,
    amount: Number(payment.amount),
    currency: payment.currency.trim(),
    amount_usdt: Number(payment.amount_usdt),
    credited_amount_usdt: Number.isFinite(Number(payment.credited_amount_usdt))
      ? Number(payment.credited_amount_usdt)
      : null,
    total_fee_amount_usdt: Number.isFinite(Number(payment.total_fee_amount_usdt))
      ? Number(payment.total_fee_amount_usdt)
      : null,
    expires_at: payment.expires_at || null,
    created_at: payment.created_at || null,
  };
}

function getCryptoExpiry(payment, now = Date.now()) {
  if (payment?.expires_at) {
    const expiresAt = Date.parse(payment.expires_at);
    if (Number.isFinite(expiresAt)) return new Date(expiresAt).toISOString();
  }

  const createdAt = payment?.created_at ? Date.parse(payment.created_at) : now;
  const baseTime = Number.isFinite(createdAt) ? createdAt : now;
  return new Date(baseTime + CRYPTO_PENDING_TTL_MS).toISOString();
}

function getCryptoPollTimeoutMs(payment, now = Date.now()) {
  const expiryTime = Date.parse(getCryptoExpiry(payment, now));
  return Math.max(
    CRYPTO_PENDING_TTL_MS,
    expiryTime - now + CRYPTO_STATUS_GRACE_MS,
  );
}

module.exports = {
  CRYPTO_PENDING_TTL_MS,
  CRYPTO_STATUS_GRACE_MS,
  calculateUsdtAmount,
  calculateManualUsdtWithdrawalAmount,
  findAshtechCryptoAsset,
  getManualUsdtWithdrawalAssets,
  getCryptoExpiry,
  getCryptoPollTimeoutMs,
  normalizeAshtechCryptoAssets,
  normalizeAshtechCryptoCollectResponse,
};
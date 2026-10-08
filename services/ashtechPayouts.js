function parseProviderMetadata(value) {
  if (value && typeof value === 'object' && !Array.isArray(value)) return value;
  if (typeof value !== 'string' || !value.trim()) return {};
  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
}

function normalizeAshtechPayoutStatus(status) {
  const normalized = String(status || '').trim().toLowerCase();
  if (normalized === 'completed' || normalized === 'success') return 'success';
  if (normalized === 'failed') return 'failed';
  if (normalized === 'pending' || normalized === 'pending_manual') return 'pending';
  return 'unknown';
}

async function settleAshtechPayout(db, withdrawalId, result) {
  const status = normalizeAshtechPayoutStatus(result);
  if (status === 'unknown' || status === 'pending') {
    return { ok: true, status: 'pending' };
  }

  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();
    const [[withdrawal]] = await conn.query(
      'SELECT * FROM retraits WHERE id = ? FOR UPDATE',
      [withdrawalId],
    );
    if (!withdrawal) {
      await conn.rollback();
      return { ok: false, status: 'missing', message: 'Retrait introuvable.' };
    }
    if (withdrawal.fournisseur !== 'ashtechpay' || withdrawal.statut !== 'en_cours') {
      await conn.rollback();
      return { ok: true, status: withdrawal.statut };
    }

    if (status === 'success') {
      await conn.query(
        "UPDATE retraits SET statut = 'valide', date_traitement = NOW() WHERE id = ? AND statut = 'en_cours' AND fournisseur = 'ashtechpay'",
        [withdrawalId],
      );
      await conn.commit();
      return { ok: true, status: 'success' };
    }

    const [balanceUpdate] = await conn.query(
      'UPDATE soldes SET solde = solde + ? WHERE user_id = ?',
      [withdrawal.montant, withdrawal.user_id],
    );
    if (balanceUpdate.affectedRows === 0) {
      await conn.query(
        'INSERT INTO soldes (user_id, solde) VALUES (?, ?)',
        [withdrawal.user_id, withdrawal.montant],
      );
    }
    await conn.query(
      "UPDATE retraits SET statut = 'rejete', date_traitement = NOW() WHERE id = ? AND statut = 'en_cours' AND fournisseur = 'ashtechpay'",
      [withdrawalId],
    );
    await conn.commit();
    return { ok: true, status: 'failed' };
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}

module.exports = {
  normalizeAshtechPayoutStatus,
  parseProviderMetadata,
  settleAshtechPayout,
};

const db = require('../config/db');

// Pays a single commande's daily gain if its 24h cycle has elapsed.
// Locks the commande row for the duration of the transaction so that
// concurrent callers (the scheduler and the manual "collecter" endpoint)
// cannot both observe the same eligibility window and double-credit it.
async function payDueCommande(commandeId) {
  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();

    const [[cmd]] = await conn.query(
      "SELECT * FROM commandes WHERE id = ? AND date_fin >= CURRENT_DATE AND statut = 'actif' FOR UPDATE",
      [commandeId]
    );
    if (!cmd) { await conn.rollback(); return 0; }

    const created = new Date(cmd.date_creation).getTime();
    const now = Date.now();
    if ((now - created) / 3600000 < 24) { await conn.rollback(); return 0; }

    const [[lp]] = await conn.query(
      "SELECT MAX(date_paiement) as last_payment FROM historique_revenus WHERE user_id = ? AND commande_id = ? AND type = 'paiement_journalier'",
      [cmd.user_id, cmd.id]
    );
    if (lp.last_payment) {
      const diffH = (now - new Date(lp.last_payment).getTime()) / 3600000;
      if (diffH < 24) { await conn.rollback(); return 0; }
    }

    const gain = parseFloat(cmd.gain_journalier);
    await conn.query(
      "INSERT INTO historique_revenus (user_id, commande_id, montant, type, date_paiement) VALUES (?, ?, ?, 'paiement_journalier', NOW())",
      [cmd.user_id, cmd.id, gain]
    );
    await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [gain, cmd.user_id]);
    await conn.commit();
    return gain;
  } catch (e) {
    await conn.rollback();
    console.error('payDueCommande failed for commande', commandeId, e);
    return 0;
  } finally {
    conn.release();
  }
}

// Pays every due commande for a single user. Returns the total credited.
// Used by the manual "collecter" endpoint.
async function payDueCommandesForUser(userId) {
  const [commandes] = await db.query(
    "SELECT id FROM commandes WHERE user_id = ? AND date_fin >= CURRENT_DATE AND statut = 'actif'",
    [userId]
  );
  let total = 0;
  for (const cmd of commandes) {
    total += await payDueCommande(cmd.id);
  }
  return total;
}

let autoPayoutRunning = false;

// Pays every due commande across all users. Used by the background scheduler.
async function runAutoPayouts() {
  if (autoPayoutRunning) return; // prevent overlapping runs
  autoPayoutRunning = true;
  try {
    const [commandes] = await db.query(
      "SELECT id FROM commandes WHERE date_fin >= CURRENT_DATE AND statut = 'actif'"
    );
    for (const cmd of commandes) {
      await payDueCommande(cmd.id);
    }
  } catch (e) {
    console.error('runAutoPayouts error:', e);
  } finally {
    autoPayoutRunning = false;
  }
}

function startAutoPayoutScheduler() {
  // Run shortly after boot, then every 5 minutes — cheap to run since it
  // only pays commandes whose 24h window has actually elapsed, and the
  // per-commande row lock keeps it safe alongside the manual collect route.
  setTimeout(runAutoPayouts, 5000);
  setInterval(runAutoPayouts, 5 * 60 * 1000);
}

module.exports = { payDueCommande, payDueCommandesForUser, runAutoPayouts, startAutoPayoutScheduler };

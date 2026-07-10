const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { getParams } = require('../services/params');

// Maps admin day abbreviations → JS getUTCDay() values (0=Sun … 6=Sat)
const DAY_MAP = { 'Dim': 0, 'Lun': 1, 'Mar': 2, 'Mer': 3, 'Jeu': 4, 'Ven': 5, 'Sam': 6 };

function buildScheduleStatus(params) {
  const jours      = (params.retrait_jours || 'Lun,Mar,Mer,Jeu,Ven,Sam').split(',').map(d => d.trim());
  const heureDebut = parseInt(params.retrait_heure_debut ?? 9);
  const heureFin   = parseInt(params.retrait_heure_fin   ?? 19);
  const allowedDays = jours.map(d => DAY_MAP[d]).filter(v => v !== undefined);

  const now  = new Date();
  const hGmt = parseInt(now.toUTCString().split(' ')[4].split(':')[0]);
  const day  = now.getUTCDay();

  const dayOk   = allowedDays.includes(day);
  const heureOk = hGmt >= heureDebut && hGmt < heureFin;

  const joursLabel  = jours.join(', ');
  const heuresLabel = `${heureDebut}h à ${heureFin}h GMT`;

  return {
    disponible: dayOk && heureOk,
    message: `Les retraits sont disponibles : ${joursLabel}, de ${heuresLabel}.`,
  };
}

// ── GET /retrait ─────────────────────────────────────────────────────────────
router.get('/retrait', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const params = await getParams();
    const [[user]]    = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? parseFloat(soldeRow.solde) : 0;

    const suspendu = (params.retraits_actifs === '0');
    const schedule = buildScheduleStatus(params);
    const retraits_disponibles = !suspendu && schedule.disponible;

    const message = req.session.retrait_message || null;
    delete req.session.retrait_message;

    res.render('retrait', { user, solde, retraits_disponibles, suspendu, schedule, params, message });
  } catch (e) {
    console.error(e);
    res.redirect('/');
  }
});

// ── POST /retrait ─────────────────────────────────────────────────────────────
router.post('/retrait', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const params = await getParams();

    // 1. Withdrawal suspended by admin?
    if (params.retraits_actifs === '0') {
      return res.json({ success: false, message: 'Le retrait est indisponible pour le moment, veuillez réessayer plus tard.' });
    }

    // 2. Schedule check
    const schedule = buildScheduleStatus(params);
    if (!schedule.disponible) {
      return res.json({ success: false, message: schedule.message });
    }

    // 3. Active investment plan required
    const [[cmds]] = await db.query(
      "SELECT COUNT(*)::int as nb FROM commandes WHERE user_id = ? AND date_fin >= CURRENT_DATE AND statut = 'actif'",
      [user_id]
    );
    if (Number(cmds.nb) === 0) {
      return res.json({ success: false, message: "Vous devez avoir au moins un plan d'investissement actif pour effectuer un retrait." });
    }

    // 4. Form validation
    const montant   = parseFloat(req.body.montant);
    const numero    = (req.body.numero   || '').trim();
    const nom       = (req.body.nom      || '').trim();
    const operateur = (req.body.operateur || '').trim();
    const pays      = (req.body.pays     || '').trim();

    if (!montant || !numero || !nom || !operateur || !pays) {
      return res.json({ success: false, message: 'Veuillez remplir tous les champs.' });
    }

    // 5. Minimum amount from params
    const retraitMin = parseFloat(params.retrait_minimum ?? 1200);
    if (montant < retraitMin) {
      return res.json({
        success: false,
        message: `Le montant minimum de retrait est de ${retraitMin.toLocaleString('fr-FR')} FCFA.`,
      });
    }

    const maxParJour = parseInt(params.retrait_max_par_jour ?? 1);
    const methode = `${operateur} (${pays})`;

    // 6. Atomic transaction: check daily limit + debit balance + insert retrait
    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();

      // Re-check daily limit inside transaction (prevents concurrent over-limit withdrawals)
      const [[recents]] = await conn.query(
        "SELECT COUNT(*)::int as nb FROM retraits WHERE user_id = ? AND statut IN ('en_attente', 'valide') AND date_demande >= NOW() - INTERVAL '24 hours'",
        [user_id]
      );
      if (Number(recents.nb) >= maxParJour) {
        await conn.rollback();
        return res.json({
          success: false,
          message: `Vous avez atteint la limite de ${maxParJour} retrait(s) autorisé(s) par 24 heures.`,
        });
      }

      // Atomic balance debit — only succeeds if balance is sufficient
      const [upd] = await conn.query(
        'UPDATE soldes SET solde = solde - ? WHERE user_id = ? AND solde >= ?',
        [montant, user_id, montant]
      );
      if (upd.affectedRows === 0) {
        await conn.rollback();
        return res.json({ success: false, message: 'Solde insuffisant.' });
      }

      await conn.query(
        "INSERT INTO retraits (user_id, montant, methode, numero_compte, statut) VALUES (?, ?, ?, ?, 'en_attente')",
        [user_id, montant, methode, numero]
      );
      await conn.commit();
    } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }

    res.json({ success: true });
  } catch (e) {
    console.error(e);
    res.json({ success: false, message: 'Erreur serveur: ' + e.message });
  }
});

module.exports = router;

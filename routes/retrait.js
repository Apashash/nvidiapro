const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { getParams } = require('../services/params');
const {
  getAshtechCountries,
  getAshtechCryptoAssets,
} = require('../services/paymentProviders');
const {
  calculateManualUsdtWithdrawalAmount,
  findAshtechCryptoAsset,
  getManualUsdtWithdrawalAssets,
} = require('../services/ashtechCrypto');
const {
  findAccountPaymentCountry,
  getCountryDialCode,
} = require('../services/accountPaymentCountry');

// Maps admin day abbreviations → JS getUTCDay() values (0=Sun … 6=Sat)
const DAY_MAP = { 'Dim': 0, 'Lun': 1, 'Mar': 2, 'Mer': 3, 'Jeu': 4, 'Ven': 5, 'Sam': 6 };

// Accepts "HH:MM" (new format) or a bare hour number (legacy format, e.g. "9")
// and returns total minutes since midnight. Falls back to `defaultHour` if
// the value is missing or unparsable, so a stray empty/invalid setting never
// produces NaN.
function parseHeureToMinutes(value, defaultHour) {
  const str = String(value ?? '').trim();
  const match = str.match(/^(\d{1,2}):(\d{2})$/);
  if (match) {
    const h = parseInt(match[1], 10);
    const m = parseInt(match[2], 10);
    if (!Number.isNaN(h) && !Number.isNaN(m)) return h * 60 + m;
  }
  const h = parseInt(str, 10);
  return (!Number.isNaN(h) ? h : defaultHour) * 60;
}

function formatMinutes(mins) {
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return m === 0 ? `${h}h` : `${h}h${String(m).padStart(2, '0')}`;
}

function buildScheduleStatus(params) {
  const jours       = (params.retrait_jours || 'Lun,Mar,Mer,Jeu,Ven,Sam').split(',').map(d => d.trim());
  const debutMins   = parseHeureToMinutes(params.retrait_heure_debut, 9);
  const finMins     = parseHeureToMinutes(params.retrait_heure_fin, 19);
  const allowedDays = jours.map(d => DAY_MAP[d]).filter(v => v !== undefined);

  const now = new Date();
  const [hGmt, mGmt] = now.toUTCString().split(' ')[4].split(':').map(Number);
  const nowMins = hGmt * 60 + mGmt;
  const day = now.getUTCDay();

  const dayOk   = allowedDays.includes(day);
  const heureOk = nowMins >= debutMins && nowMins < finMins;

  const joursLabel  = jours.join(', ');
  const heuresLabel = `${formatMinutes(debutMins)} à ${formatMinutes(finMins)} GMT`;

  return {
    disponible: dayOk && heureOk,
    message: `Les retraits sont disponibles : ${joursLabel}, de ${heuresLabel}.`,
  };
}

router.get('/retrait/crypto/assets', requireAuth, async (req, res) => {
  try {
    const assets = getManualUsdtWithdrawalAssets(await getAshtechCryptoAssets())
      .map(asset => ({
        asset_code: asset.asset_code,
        network_label: asset.network_label,
      }));
    res.json({ assets });
  } catch (error) {
    console.error('Could not load USDT withdrawal networks:', error.message);
    res.status(503).json({
      error: 'Les réseaux USDT sont temporairement indisponibles.',
    });
  }
});

// ── GET /retrait ─────────────────────────────────────────────────────────────
router.get('/retrait', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const params = await getParams();
    const [[user]]    = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const countries = await getAshtechCountries();
    const withdrawalCountry = findAccountPaymentCountry(user?.pays, countries);
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? parseFloat(soldeRow.solde) : 0;

    const suspendu = (params.retraits_actifs === '0');
    const schedule = buildScheduleStatus(params);
    const retrait_bloque = !!(user && user.retrait_bloque);
    const [[commandRow]] = await db.query(
      "SELECT COUNT(*)::int as nb FROM commandes WHERE user_id = ?",
      [user_id]
    );
    const hasActiveInvestment = Number(commandRow?.nb) > 0;
    const retraits_disponibles = !suspendu && !retrait_bloque && schedule.disponible && hasActiveInvestment;

    const message = req.session.retrait_message || null;
    delete req.session.retrait_message;

    const fraisPourcentage = parseFloat(params.retrait_frais_pourcentage ?? 0);
    const configuredCryptoRate = params.taux_usdt_fcfa === undefined || params.taux_usdt_fcfa === ''
      ? 600
      : Number(params.taux_usdt_fcfa);
    const withdrawalCryptoRate = Number.isFinite(configuredCryptoRate) && configuredCryptoRate > 0
      ? configuredCryptoRate
      : 0;
    res.render('retrait', {
      user, solde, retraits_disponibles, hasActiveInvestment, suspendu,
      retrait_bloque, schedule, params, fraisPourcentage, message,
      withdrawalCountry,
      withdrawalDialCode: withdrawalCountry
        ? getCountryDialCode(withdrawalCountry.code)
        : '',
      withdrawalCryptoRate,
    });
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

    // 1. Retrait bloqué individuellement par l'admin ?
    const [[userCheck]] = await db.query(
      'SELECT retrait_bloque, pays, nom FROM utilisateurs WHERE id = ?',
      [user_id]
    );
    if (userCheck && userCheck.retrait_bloque) {
      return res.json({ success: false, blocked: true, message: 'Votre retrait est bloqué. Pour le débloquer, achetez un nouveau plan VIP ou invitez une personne à investir.' });
    }

    // 2. Withdrawal suspended by admin?
    if (params.retraits_actifs === '0') {
      return res.json({ success: false, message: 'Le retrait est indisponible pour le moment, veuillez réessayer plus tard.' });
    }

    // 3. Schedule check
    const schedule = buildScheduleStatus(params);
    if (!schedule.disponible) {
      return res.json({ success: false, message: schedule.message });
    }

    // 3. At least one investment/action must have been purchased.
    const [[cmds]] = await db.query(
      "SELECT COUNT(*)::int as nb FROM commandes WHERE user_id = ?",
      [user_id]
    );
    if (Number(cmds.nb) === 0) {
      return res.json({ success: false, message: "Vous devez acheter au moins une action avant de pouvoir effectuer un retrait." });
    }

    // 4. Form validation
    const montant = Number(req.body.montant);
    const cryptoMode = req.body.mode === 'crypto';
    let numero = '';
    let nom = '';
    let operateur = '';
    let pays = '';
    let methode = '';
    let cryptoAmountUsdt = null;
    let networkLabel = null;

    if (!Number.isFinite(montant) || montant <= 0) {
      return res.json({ success: false, message: 'Saisissez un montant valide.' });
    }

    const retraitMin = parseFloat(params.retrait_minimum ?? 1200);
    if (montant < retraitMin) {
      return res.json({
        success: false,
        message: `Le montant minimum de retrait est de ${retraitMin.toLocaleString('fr-FR')} FCFA.`,
      });
    }

    if (cryptoMode) {
      const walletAddress = String(req.body.wallet_address || '').trim();
      const assetCode = String(req.body.asset_code || '').trim();
      if (walletAddress.length < 20 || walletAddress.length > 255
          || !/^[A-Za-z0-9:_-]+$/.test(walletAddress)) {
        return res.json({ success: false, message: 'Adresse de portefeuille USDT invalide.' });
      }

      let assets;
      try {
        assets = getManualUsdtWithdrawalAssets(await getAshtechCryptoAssets());
      } catch (error) {
        console.error('USDT network validation failed:', error.message);
        return res.json({ success: false, message: 'Impossible de vérifier le réseau USDT. Réessayez plus tard.' });
      }
      const asset = findAshtechCryptoAsset(assets, assetCode);
      if (!asset) {
        return res.json({ success: false, message: 'Sélectionnez un réseau USDT disponible.' });
      }

      const rate = params.taux_usdt_fcfa === undefined || params.taux_usdt_fcfa === ''
        ? 600
        : Number(params.taux_usdt_fcfa);
      if (!Number.isFinite(rate) || rate <= 0) {
        return res.json({ success: false, message: 'Le taux USDT/FCFA n’est pas configuré.' });
      }
      try {
        const fraisPourcentage = parseFloat(params.retrait_frais_pourcentage ?? 0);
        const frais = Math.round((montant * fraisPourcentage / 100) * 100) / 100;
        const montantNet = Math.round((montant - frais) * 100) / 100;
        cryptoAmountUsdt = calculateManualUsdtWithdrawalAmount(montantNet, rate);
      } catch (error) {
        return res.json({ success: false, message: error.message });
      }

      networkLabel = asset.network_label;
      numero = walletAddress;
      nom = String(userCheck?.nom || '').trim();
      operateur = 'Crypto USDT';
      pays = String(userCheck?.pays || 'Autre').trim();
      methode = `Crypto USDT ${cryptoAmountUsdt} · ${networkLabel} · taux ${rate} FCFA/USDT`;
      if (methode.length > 100) {
        return res.json({ success: false, message: 'Le nom du réseau USDT est trop long.' });
      }
    } else {
      const submittedCountry = String(req.body.pays || '').trim();
      const countryCode = String(req.body.country_code || '').trim().toUpperCase();
      const countries = await getAshtechCountries();
      const accountCountry = findAccountPaymentCountry(userCheck?.pays, countries);
      const submittedAccountCountry = findAccountPaymentCountry(submittedCountry, countries);
      const country = countries.find(item => item.code === countryCode);
      operateur = String(req.body.operateur || '').trim();
      nom = String(req.body.nom || '').trim();
      const submittedPhone = String(req.body.numero || '').trim();
      const dialCode = country ? getCountryDialCode(country.code) : '';
      const phoneDigits = submittedPhone.replace(/\D/g, '');

      if (!accountCountry || !country || country.code !== accountCountry.code
          || !submittedAccountCountry || submittedAccountCountry.code !== accountCountry.code) {
        return res.json({
          success: false,
          message: 'Le retrait Mobile Money est limité au pays de votre compte. Choisissez le crypto si nécessaire.',
        });
      }
      if (!country.operators.includes(operateur)) {
        return res.json({ success: false, message: 'Opérateur invalide pour le pays de votre compte.' });
      }
      if (!dialCode || !phoneDigits.startsWith(dialCode)
          || phoneDigits.length < 8 || phoneDigits.length > 15
          || !nom || nom.length > 100) {
        return res.json({ success: false, message: 'Vérifiez le numéro et le nom du titulaire.' });
      }

      numero = `+${phoneDigits}`;
      pays = country.name;
      methode = operateur;
    }

    const maxParJour = parseInt(params.retrait_max_par_jour ?? 1);

    // Withdrawal fee — percentage kept by the platform, deducted from the payout
    const fraisPourcentage = parseFloat(params.retrait_frais_pourcentage ?? 0);
    const frais = Math.round((montant * fraisPourcentage / 100) * 100) / 100;
    const montantNet = Math.round((montant - frais) * 100) / 100;

    if (!cryptoMode) {
      methode = fraisPourcentage > 0
        ? `${operateur} (${pays}) — frais ${fraisPourcentage}% : ${frais.toLocaleString('fr-FR')} FCFA, net : ${montantNet.toLocaleString('fr-FR')} FCFA`
        : `${operateur} (${pays})`;
    }

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
        "INSERT INTO retraits (user_id, montant, montant_net, frais, operateur, pays, methode, numero_compte, fournisseur, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'manuel', 'en_attente')",
        [user_id, montant, montantNet, frais, operateur, pays, methode, numero]
      );
      await conn.commit();
    } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }

    res.json({
      success: true,
      frais,
      montantNet,
      cryptoAmountUsdt,
      networkLabel,
    });
  } catch (e) {
    console.error(e);
    res.json({ success: false, message: 'Erreur serveur: ' + e.message });
  }
});

module.exports = router;

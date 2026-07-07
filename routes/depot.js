const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const axios = require('axios');

const pays_operateurs = {
  'Bénin': { country_code: 'BJ', currency: 'XOF', operators: { '35': 'MTN Money', '36': 'Moov Money' } },
  'Burkina Faso': { country_code: 'BF', currency: 'XOF', operators: { '33': 'Moov Money', '34': 'Orange Money' } },
  'Cameroun': { country_code: 'CM', currency: 'XAF', operators: { '1': 'MTN Mobile Money', '2': 'Orange Money' } },
  "Côte d'Ivoire": { country_code: 'CI', currency: 'XOF', operators: { '30': 'MTN Money', '32': 'Wave', '31': 'Moov Money', '29': 'Orange Money' } },
  'Mali': { country_code: 'ML', currency: 'XOF', operators: { '39': 'Orange Money', '40': 'Moov Money' } },
  'Togo': { country_code: 'TG', currency: 'XOF', operators: { '38': 'Moov Money', '37': 'T-Money' } },
  'Sénégal': { country_code: 'SN', currency: 'XOF', operators: { '26': 'Free Money', '25': 'Wave', '27': 'Expresso', '28': 'Wizall', '24': 'Orange Money' } },
};

router.get('/depot', requireAuth, async (req, res) => {
  try {
    const user_id = req.session.user_id;
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const error = req.session.error || null;
    const success = req.session.success || (req.query.success === '1' ? 'Dépôt en cours de traitement...' : null);
    const failed = req.query.failed === '1' ? 'Paiement échoué. Veuillez réessayer.' : null;
    delete req.session.error;
    delete req.session.success;
    res.render('depot', { user, pays_operateurs, error, success, failed });
  } catch (e) {
    console.error('GET /depot error:', e);
    res.redirect('/');
  }
});

router.post('/depot/process', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const montant = parseFloat(req.body.montant || 0);
  const pays = (req.body.pays || '').trim();
  const operateur = (req.body.operateur || '').trim();
  const email = (req.body.email || '').trim();
  const numero = (req.body.numero || '').trim();

  if (montant < 200) { req.session.error = 'Le montant minimum est de 200'; return res.redirect('/depot'); }
  if (!pays || !operateur || !email || !numero) { req.session.error = 'Tous les champs sont obligatoires'; return res.redirect('/depot'); }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { req.session.error = 'Adresse email invalide'; return res.redirect('/depot'); }
  if (!/^[0-9]{9,15}$/.test(numero)) { req.session.error = 'Numéro de téléphone invalide'; return res.redirect('/depot'); }

  const devises = { 'Bénin': 'XOF', 'Burkina Faso': 'XOF', 'Cameroun': 'XAF', "Côte d'Ivoire": 'XOF', 'Mali': 'XOF', 'Togo': 'XOF', 'Sénégal': 'XOF' };
  const currency = devises[pays] || 'XOF';
  const api_key = process.env.SOLEASPAY_API_KEY || 'met ta clee api soleaspay ici';
  const order_id = `DEP_${user_id}_${Date.now()}_${Math.floor(Math.random() * 9000) + 1000}`;

  const [[userRow]] = await db.query('SELECT nom FROM utilisateurs WHERE id = ?', [user_id]);
  const payer_name = userRow ? userRow.nom : 'Client';

  let depot_id;
  try {
    const [result] = await db.query(
      "INSERT INTO depots (user_id, montant, methode, numero_transaction, pays, statut) VALUES (?, ?, ?, ?, ?, 'en_attente')",
      [user_id, montant, `Mobile Money - Service ${operateur}`, order_id, pays]
    );
    depot_id = result.insertId;
  } catch (e) {
    req.session.error = "Erreur lors de l'enregistrement du dépôt";
    return res.redirect('/depot');
  }

  try {
    const protocol = req.headers['x-forwarded-proto'] || req.protocol;
    const host = req.headers.host;
    const response = await axios.post(
      'https://soleaspay.com/api/agent/bills/v3',
      {
        wallet: numero, amount: montant, currency, orderId: order_id,
        description: 'Dépôt sur NVIDIA Technology', payer: payer_name, payerEmail: email,
        successUrl: `${protocol}://${host}/depot?success=1`,
        failureUrl: `${protocol}://${host}/depot?failed=1`,
      },
      {
        headers: { 'x-api-key': api_key, 'operation': '2', 'service': operateur, 'Content-Type': 'application/json' },
        timeout: 30000,
      }
    );

    const result = response.data;
    if (result.success === true) {
      const reference = result.data?.reference || '';
      if (reference) {
        await db.query('UPDATE depots SET numero_transaction = ? WHERE id = ?', [`${reference}|${order_id}`, depot_id]);
      }
      // Quick verification after 3s (async)
      setTimeout(async () => {
        try {
          const vRes = await axios.get(
            `https://soleaspay.com/api/agent/verif-pay?orderId=${encodeURIComponent(order_id)}&payId=${encodeURIComponent(reference)}`,
            { headers: { 'x-api-key': api_key }, timeout: 15000 }
          );
          const vData = vRes.data;
          if (vData.success && vData.status === 'SUCCESS') {
            const conn = await db.getConnection();
            try {
              await conn.beginTransaction();
              const [[dep]] = await conn.query('SELECT * FROM depots WHERE id = ?', [depot_id]);
              if (dep && dep.statut !== 'valide') {
                const [[sl]] = await conn.query('SELECT * FROM soldes WHERE user_id = ?', [dep.user_id]);
                if (sl) await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [dep.montant, dep.user_id]);
                else await conn.query('INSERT INTO soldes (user_id, solde) VALUES (?, ?)', [dep.user_id, dep.montant]);
                await conn.query("UPDATE depots SET statut = 'valide', date_validation = NOW() WHERE id = ?", [depot_id]);
              }
              await conn.commit();
            } catch (e) { await conn.rollback(); } finally { conn.release(); }
          }
        } catch (e) { /* silent */ }
      }, 3000);

      res.redirect('/depot?success=1');
    } else {
      await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot_id]);
      req.session.error = result.message || 'Paiement refusé par le serveur';
      res.redirect('/depot');
    }
  } catch (e) {
    console.error('SoleasPay error:', e.message);
    req.session.error = 'Erreur de connexion au serveur de paiement';
    res.redirect('/depot');
  }
});

// SoleasPay webhook callback
router.post('/soleaspay_callback', async (req, res) => {
  const data = req.body;
  const success = data.success;
  const status = data.status;
  const tx = data.data || {};
  const order_id = tx.external_reference || '';
  const amount = tx.amount || 0;

  if (!order_id) return res.status(400).json({ error: 'Missing order_id' });

  try {
    const [[depot]] = await db.query(
      'SELECT * FROM depots WHERE numero_transaction LIKE ? OR numero_transaction = ?',
      [`%${order_id}%`, order_id]
    );
    if (!depot) return res.status(404).json({ error: 'Deposit not found' });
    if (depot.statut === 'valide') return res.json({ success: true, message: 'Already processed' });

    if (success === true && status === 'SUCCESS') {
      const conn = await db.getConnection();
      try {
        await conn.beginTransaction();
        const [[sl]] = await conn.query('SELECT * FROM soldes WHERE user_id = ?', [depot.user_id]);
        if (sl) await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [depot.montant, depot.user_id]);
        else await conn.query('INSERT INTO soldes (user_id, solde) VALUES (?, ?)', [depot.user_id, depot.montant]);
        await conn.query("UPDATE depots SET statut = 'valide', date_validation = NOW() WHERE id = ?", [depot.id]);
        await conn.commit();
        res.json({ success: true, message: 'Deposit validated' });
      } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
    } else if (['RECEIVED', 'PROCESSING'].includes(status)) {
      res.json({ success: true, message: 'Payment processing' });
    } else {
      if (depot.statut !== 'valide') {
        await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot.id]);
      }
      res.json({ success: true, message: 'Deposit rejected' });
    }
  } catch (e) {
    console.error('Callback error:', e);
    res.status(500).json({ error: 'Internal server error' });
  }
});

module.exports = router;

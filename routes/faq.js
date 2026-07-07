const express = require('express');
const router = express.Router();
const { requireAuth } = require('../middleware/auth');

router.get('/faq', requireAuth, (req, res) => res.render('faq'));
router.get('/tuto', requireAuth, (req, res) => res.render('tuto'));

module.exports = router;

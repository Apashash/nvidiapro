const express = require('express');
const session = require('express-session');
const PgSession = require('connect-pg-simple')(session);
const path = require('path');
const db = require('./config/db');

const app = express();
const sessionSecret = process.env.SESSION_SECRET
  || (process.env.NODE_ENV === 'production' ? null : 'nvidia-tech-secret-2025');

if (!sessionSecret) {
  throw new Error('SESSION_SECRET must be configured when NODE_ENV=production.');
}

// View engine
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));
app.set('trust proxy', 1);

// Middleware
app.use(express.urlencoded({ extended: true }));
// Keep the raw webhook body available for AshTechPay HMAC verification.
// This must run before express.json() consumes the request body.
app.use('/ashtechpay_callback', express.raw({ type: 'application/json' }));
// SoleasPay signs callbacks with the x-private-key header.
app.use('/soleaspay_callback', express.raw({ type: 'application/json' }));
app.use(express.json());

app.use(session({
  // The default MemoryStore loses every login when Plesk restarts the
  // process or sends the next request to another worker.
  store: new PgSession({
    pool: db.pool,
    tableName: 'user_sessions',
    createTableIfMissing: true,
  }),
  secret: sessionSecret,
  resave: false,
  saveUninitialized: false,
  cookie: {
    maxAge: 24 * 60 * 60 * 1000,
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
  },
}));

// Params middleware — loads app_parametres into res.locals.appParams for all views
app.use(require('./middleware/paramLoader'));

// Static files
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));
app.use('/assets', express.static(path.join(__dirname, 'assets')));
app.use('/images', express.static(path.join(__dirname, 'public/images')));

// Routes
app.use('/', require('./routes/auth'));
app.use('/', require('./routes/dashboard'));
app.use('/', require('./routes/investissement'));
app.use('/', require('./routes/depot'));
app.use('/', require('./routes/retrait'));
app.use('/', require('./routes/compte'));
app.use('/', require('./routes/equipe'));
app.use('/', require('./routes/roue'));
app.use('/', require('./routes/salaire'));
app.use('/', require('./routes/cadeau'));
app.use('/', require('./routes/faq'));
app.use('/', require('./routes/admin'));

// 404
app.use((req, res) => res.status(404).redirect('/'));

const PORT = process.env.PORT || 5000;
app.listen(PORT, '0.0.0.0', () => {
  console.log(`Groupe Dangote (GD) server running on port ${PORT}`);
  require('./services/autoPayout').startAutoPayoutScheduler();
});

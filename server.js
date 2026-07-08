const express = require('express');
const session = require('express-session');
const path = require('path');

const app = express();

// View engine
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// Middleware
app.use(express.urlencoded({ extended: true }));
app.use(express.json());

app.use(session({
  secret: process.env.SESSION_SECRET || 'nvidia-tech-secret-2025',
  resave: false,
  saveUninitialized: false,
  cookie: {
    maxAge: 24 * 60 * 60 * 1000,
    httpOnly: true,
    sameSite: 'lax',
  },
}));

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

const PORT = process.env.PORT || 3000;
app.listen(PORT, '0.0.0.0', () => {
  console.log(`NVIDIA Technology server running on port ${PORT}`);
});

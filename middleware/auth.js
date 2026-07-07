function requireAuth(req, res, next) {
  if (!req.session || !req.session.user_id) {
    return res.redirect('/connexion');
  }
  next();
}

function requireAdmin(req, res, next) {
  if (!req.session || !req.session.security_authenticated) {
    return res.redirect('/adminxyz');
  }
  next();
}

module.exports = { requireAuth, requireAdmin };

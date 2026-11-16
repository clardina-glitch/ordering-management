function requireAuth(req, res, next) {
  if (!req.session.userId) {
    req.session.flash = { type: 'error', message: 'Please log in to continue.' };
    return res.redirect('/login');
  }
  next();
}

function requireAdmin(req, res, next) {
  if (!req.user || req.user.role !== 'admin') {
    req.session.flash = { type: 'error', message: 'You do not have permission to do that.' };
    return res.redirect('/dashboard');
  }
  next();
}

function redirectIfGuest(req, res, next) {
  if (req.session.userId) {
    return res.redirect('/dashboard');
  }
  next();
}

module.exports = { requireAuth, requireAdmin, redirectIfGuest };

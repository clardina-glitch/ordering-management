const express = require('express');
const bcrypt = require('bcryptjs');

const pool = require('../db');
const asyncHandler = require('../utils/asyncHandler');
const { validateRegistration } = require('../utils/validate');
const { regenerate, save } = require('../utils/session');
const { redirectIfGuest } = require('../middleware/auth');

const router = express.Router();

router.get('/login', redirectIfGuest, (req, res) => {
  res.render('auth/login', { title: 'Log in', email: '' });
});

router.post(
  '/login',
  redirectIfGuest,
  asyncHandler(async (req, res) => {
    const email = String(req.body.email || '').trim().toLowerCase();
    const password = String(req.body.password || '');

    const renderError = (message) =>
      res.status(400).render('auth/login', { title: 'Log in', email, error: message });

    if (!email || !password) {
      return renderError('Email and password are required.');
    }

    const [rows] = await pool.query('SELECT * FROM users WHERE email = ?', [email]);
    if (rows.length === 0) {
      return renderError('Invalid email or password.');
    }

    const user = rows[0];
    const matches = await bcrypt.compare(password, user.password_hash);
    if (!matches) {
      return renderError('Invalid email or password.');
    }

    await regenerate(req);
    req.session.userId = user.id;
    await save(req);
    res.redirect('/dashboard');
  })
);

router.get('/register', redirectIfGuest, (req, res) => {
  res.render('auth/register', { title: 'Create account', values: {}, errors: [] });
});

router.post(
  '/register',
  redirectIfGuest,
  asyncHandler(async (req, res) => {
    const { errors, values } = validateRegistration(req.body);

    if (errors.length > 0) {
      return res.status(400).render('auth/register', {
        title: 'Create account',
        values,
        errors
      });
    }

    const passwordHash = await bcrypt.hash(req.body.password, 10);

    try {
      const [result] = await pool.query(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
        [values.name, values.email, passwordHash, 'customer']
      );

      await regenerate(req);
      req.session.userId = result.insertId;
      req.session.flash = { type: 'success', message: 'Welcome! Your account is ready.' };
      await save(req);
      res.redirect('/dashboard');
    } catch (error) {
      if (error.code === 'ER_DUP_ENTRY') {
        return res.status(400).render('auth/register', {
          title: 'Create account',
          values,
          errors: ['An account with that email already exists.']
        });
      }
      throw error;
    }
  })
);

router.post('/logout', (req, res) => {
  req.session.destroy(() => res.redirect('/login'));
});

router.get('/logout', (req, res) => {
  req.session.destroy(() => res.redirect('/login'));
});

module.exports = router;

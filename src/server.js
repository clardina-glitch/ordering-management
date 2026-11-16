require('dotenv').config();

const path = require('path');
const express = require('express');
const session = require('express-session');
const MySQLStore = require('express-mysql-session')(session);

const pool = require('./db');
const asyncHandler = require('./utils/asyncHandler');
const { countCart } = require('./utils/cart');

const authRoutes = require('./routes/auth');
const dashboardRoutes = require('./routes/dashboard');
const productRoutes = require('./routes/products');
const cartRoutes = require('./routes/cart');
const orderRoutes = require('./routes/orders');

const app = express();
const PORT = Number(process.env.PORT || 3000);

app.set('trust proxy', 1);
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, '..', 'views'));

app.use(express.urlencoded({ extended: false }));
app.use(express.static(path.join(__dirname, '..', 'public')));

const sessionStore = new MySQLStore(
  {
    clearExpired: true,
    checkExpirationInterval: 900000,
    expiration: 1000 * 60 * 60 * 24 * 7,
    createDatabaseTable: true,
    schema: {
      tableName: 'sessions',
      columnNames: {
        session_id: 'session_id',
        expires: 'expires',
        data: 'data'
      }
    }
  },
  pool
);

app.use(
  session({
    name: 'ordering.sid',
    secret: process.env.SESSION_SECRET || 'dev-only-secret-change-me',
    store: sessionStore,
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      sameSite: 'lax',
      secure: 'auto',
      maxAge: 1000 * 60 * 60 * 24 * 7
    }
  })
);

app.use((req, res, next) => {
  res.locals.currentUser = null;
  res.locals.currentPath = req.path;
  res.locals.cartCount = countCart(req.session.cart);
  res.locals.flash = req.session.flash || null;
  res.locals.money = (value) => `$${Number(value || 0).toFixed(2)}`;
  res.locals.formatDate = (value) =>
    new Date(value).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
  res.locals.title = 'Orderly';
  delete req.session.flash;
  next();
});

app.use(
  asyncHandler(async (req, res, next) => {
    if (!req.session.userId) return next();

    const [rows] = await pool.query(
      'SELECT id, name, email, role FROM users WHERE id = ?',
      [req.session.userId]
    );

    if (rows.length === 0) {
      req.session.userId = null;
      return next();
    }

    req.user = rows[0];
    res.locals.currentUser = rows[0];
    next();
  })
);

app.get('/', (req, res) => res.redirect(req.session.userId ? '/dashboard' : '/login'));

app.get('/health', (req, res) => res.json({ status: 'ok' }));

app.use(authRoutes);
app.use(dashboardRoutes);
app.use(productRoutes);
app.use(cartRoutes);
app.use(orderRoutes);

app.use((req, res) => {
  res.status(404).render('404', { title: 'Page not found' });
});

app.use((error, req, res, next) => {
  console.error(error);
  if (res.headersSent) return next(error);
  res.status(500).render('error', {
    title: 'Something went wrong',
    message: 'An unexpected error occurred. Please try again.'
  });
});

app.listen(PORT, () => {
  console.log(`Ordering System running at http://localhost:${PORT}`);
});

const express = require('express');

const pool = require('../db');
const asyncHandler = require('../utils/asyncHandler');
const { requireAuth } = require('../middleware/auth');
const { parseId, parseQuantity } = require('../utils/validate');
const { loadCart } = require('../utils/cart');

const router = express.Router();

function backTo(req, fallback) {
  const referer = req.get('referer');
  if (referer) {
    try {
      const url = new URL(referer);
      if (url.host === req.get('host')) {
        return url.pathname + url.search;
      }
    } catch (error) {
      return fallback;
    }
  }
  return fallback;
}

router.get(
  '/cart',
  requireAuth,
  asyncHandler(async (req, res) => {
    const cart = await loadCart(req.session.cart);

    req.session.cart = Object.fromEntries(cart.items.map((item) => [item.id, item.quantity]));
    res.locals.cartCount = cart.count;

    res.render('cart', { title: 'Your order', cart });
  })
);

router.post(
  '/cart/add',
  requireAuth,
  asyncHandler(async (req, res) => {
    const productId = parseId(req.body.product_id);
    const quantity = parseQuantity(req.body.quantity);

    if (!productId || !quantity) {
      req.session.flash = { type: 'error', message: 'Choose a valid product and quantity.' };
      return res.redirect('/products');
    }

    const [rows] = await pool.query('SELECT id, name, stock FROM products WHERE id = ?', [productId]);
    if (rows.length === 0) {
      req.session.flash = { type: 'error', message: 'That product is no longer available.' };
      return res.redirect('/products');
    }

    const product = rows[0];
    if (product.stock < 1) {
      req.session.flash = { type: 'error', message: `${product.name} is out of stock.` };
      return res.redirect('/products');
    }

    req.session.cart = req.session.cart || {};
    const existing = Number(req.session.cart[productId]) || 0;
    const nextQuantity = Math.min(existing + quantity, product.stock);
    req.session.cart[productId] = nextQuantity;

    if (nextQuantity < existing + quantity) {
      req.session.flash = {
        type: 'info',
        message: `Only ${product.stock} of ${product.name} are available, so your quantity was adjusted.`
      };
    } else {
      req.session.flash = { type: 'success', message: `${product.name} added to your order.` };
    }

    res.redirect(backTo(req, '/products'));
  })
);

router.post(
  '/cart/update',
  requireAuth,
  asyncHandler(async (req, res) => {
    const productId = parseId(req.body.product_id);
    const quantity = Number(req.body.quantity);

    if (!productId || !Number.isInteger(quantity) || quantity < 0 || quantity > 9999) {
      req.session.flash = { type: 'error', message: 'Enter a valid quantity.' };
      return res.redirect('/cart');
    }

    req.session.cart = req.session.cart || {};

    if (quantity === 0) {
      delete req.session.cart[productId];
      req.session.flash = { type: 'info', message: 'Item removed from your order.' };
      return res.redirect('/cart');
    }

    const [rows] = await pool.query('SELECT name, stock FROM products WHERE id = ?', [productId]);
    if (rows.length === 0) {
      delete req.session.cart[productId];
      req.session.flash = { type: 'info', message: 'That product was removed from your order.' };
      return res.redirect('/cart');
    }

    const product = rows[0];
    const nextQuantity = Math.min(quantity, product.stock);
    req.session.cart[productId] = nextQuantity;

    if (nextQuantity < quantity) {
      req.session.flash = {
        type: 'info',
        message: `Only ${product.stock} of ${product.name} are available.`
      };
    }

    res.redirect('/cart');
  })
);

router.post(
  '/cart/remove',
  requireAuth,
  (req, res) => {
    const productId = parseId(req.body.product_id);
    if (productId && req.session.cart) {
      delete req.session.cart[productId];
    }
    req.session.flash = { type: 'info', message: 'Item removed from your order.' };
    res.redirect('/cart');
  }
);

module.exports = router;

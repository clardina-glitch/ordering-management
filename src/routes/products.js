const express = require('express');

const pool = require('../db');
const asyncHandler = require('../utils/asyncHandler');
const { requireAuth, requireAdmin } = require('../middleware/auth');
const { cleanText, parseId, validateProduct } = require('../utils/validate');

const router = express.Router();

router.get(
  '/products',
  requireAuth,
  asyncHandler(async (req, res) => {
    const q = cleanText(req.query.q, 100);
    const conditions = [];
    const params = [];

    if (q) {
      conditions.push('(name LIKE ? OR description LIKE ?)');
      params.push(`%${q}%`, `%${q}%`);
    }

    const sql = `SELECT * FROM products${
      conditions.length ? ` WHERE ${conditions.join(' AND ')}` : ''
    } ORDER BY name ASC`;

    const [products] = await pool.query(sql, params);

    res.render('products/index', {
      title: 'Products',
      products,
      q,
      isAdmin: req.user.role === 'admin'
    });
  })
);

router.get('/products/new', requireAuth, requireAdmin, (req, res) => {
  res.render('products/form', {
    title: 'Add product',
    product: { name: '', description: '', price: '', stock: '', image: '📦' },
    errors: [],
    isEdit: false
  });
});

router.post(
  '/products',
  requireAuth,
  requireAdmin,
  asyncHandler(async (req, res) => {
    const { errors, values } = validateProduct(req.body);

    if (errors.length > 0) {
      return res.status(400).render('products/form', {
        title: 'Add product',
        product: values,
        errors,
        isEdit: false
      });
    }

    await pool.query(
      'INSERT INTO products (name, description, price, stock, image) VALUES (?, ?, ?, ?, ?)',
      [values.name, values.description, Number(values.price), Number(values.stock), values.image]
    );

    req.session.flash = { type: 'success', message: 'Product added.' };
    res.redirect('/products');
  })
);

router.get(
  '/products/:id/edit',
  requireAuth,
  requireAdmin,
  asyncHandler(async (req, res) => {
    const id = parseId(req.params.id);
    if (!id) return res.status(404).render('404', { title: 'Product not found' });

    const [rows] = await pool.query('SELECT * FROM products WHERE id = ?', [id]);
    if (rows.length === 0) {
      return res.status(404).render('404', { title: 'Product not found' });
    }

    res.render('products/form', {
      title: 'Edit product',
      product: rows[0],
      errors: [],
      isEdit: true
    });
  })
);

router.post(
  '/products/:id',
  requireAuth,
  requireAdmin,
  asyncHandler(async (req, res) => {
    const id = parseId(req.params.id);
    if (!id) return res.status(404).render('404', { title: 'Product not found' });

    const { errors, values } = validateProduct(req.body);

    if (errors.length > 0) {
      return res.status(400).render('products/form', {
        title: 'Edit product',
        product: { ...values, id },
        errors,
        isEdit: true
      });
    }

    const [result] = await pool.query(
      'UPDATE products SET name = ?, description = ?, price = ?, stock = ?, image = ? WHERE id = ?',
      [values.name, values.description, Number(values.price), Number(values.stock), values.image, id]
    );

    if (result.affectedRows === 0) {
      return res.status(404).render('404', { title: 'Product not found' });
    }

    req.session.flash = { type: 'success', message: 'Product updated.' };
    res.redirect('/products');
  })
);

router.post(
  '/products/:id/delete',
  requireAuth,
  requireAdmin,
  asyncHandler(async (req, res) => {
    const id = parseId(req.params.id);
    if (!id) return res.status(404).render('404', { title: 'Product not found' });

    await pool.query('DELETE FROM products WHERE id = ?', [id]);

    req.session.flash = { type: 'success', message: 'Product deleted.' };
    res.redirect('/products');
  })
);

module.exports = router;

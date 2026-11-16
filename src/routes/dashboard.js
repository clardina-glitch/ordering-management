const express = require('express');

const pool = require('../db');
const asyncHandler = require('../utils/asyncHandler');
const { requireAuth } = require('../middleware/auth');

const router = express.Router();

router.get(
  '/dashboard',
  requireAuth,
  asyncHandler(async (req, res) => {
    const isAdmin = req.user.role === 'admin';
    let stats;
    let recentOrders;

    if (isAdmin) {
      const [[productCount], [customerCount], [orderCount], [revenue], [lowStock]] =
        await Promise.all([
          pool.query('SELECT COUNT(*) AS value FROM products'),
          pool.query("SELECT COUNT(*) AS value FROM users WHERE role = 'customer'"),
          pool.query('SELECT COUNT(*) AS value FROM orders'),
          pool.query("SELECT COALESCE(SUM(total), 0) AS value FROM orders WHERE status <> 'cancelled'"),
          pool.query('SELECT COUNT(*) AS value FROM products WHERE stock < 10')
        ]);

      stats = [
        { label: 'Products', value: productCount[0].value, hint: `${lowStock[0].value} low on stock` },
        { label: 'Customers', value: customerCount[0].value, hint: 'Registered accounts' },
        { label: 'Orders', value: orderCount[0].value, hint: 'All time' },
        { label: 'Revenue', value: res.locals.money(revenue[0].value), hint: 'Excludes cancelled' }
      ];

      [recentOrders] = await pool.query(
        `SELECT o.id, o.total, o.status, o.created_at, u.name AS customer_name
         FROM orders o
         JOIN users u ON u.id = o.user_id
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 6`
      );
    } else {
      const [[orderCount], [spent], [items], [pending], [productCount]] = await Promise.all([
        pool.query('SELECT COUNT(*) AS value FROM orders WHERE user_id = ?', [req.user.id]),
        pool.query(
          "SELECT COALESCE(SUM(total), 0) AS value FROM orders WHERE user_id = ? AND status <> 'cancelled'",
          [req.user.id]
        ),
        pool.query(
          `SELECT COALESCE(SUM(oi.quantity), 0) AS value
           FROM order_items oi
           JOIN orders o ON o.id = oi.order_id
           WHERE o.user_id = ? AND o.status <> 'cancelled'`,
          [req.user.id]
        ),
        pool.query(
          "SELECT COUNT(*) AS value FROM orders WHERE user_id = ? AND status = 'pending'",
          [req.user.id]
        ),
        pool.query('SELECT COUNT(*) AS value FROM products')
      ]);

      stats = [
        { label: 'My orders', value: orderCount[0].value, hint: `${pending[0].value} pending` },
        { label: 'Items ordered', value: items[0].value, hint: 'All time' },
        { label: 'Total spent', value: res.locals.money(spent[0].value), hint: 'Excludes cancelled' },
        { label: 'Available products', value: productCount[0].value, hint: 'Browse the catalogue' }
      ];

      [recentOrders] = await pool.query(
        `SELECT o.id, o.total, o.status, o.created_at, u.name AS customer_name
         FROM orders o
         JOIN users u ON u.id = o.user_id
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 6`,
        [req.user.id]
      );
    }

    res.render('dashboard', {
      title: 'Dashboard',
      isAdmin,
      stats,
      recentOrders
    });
  })
);

module.exports = router;

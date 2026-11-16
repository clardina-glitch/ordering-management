const express = require('express');

const pool = require('../db');
const asyncHandler = require('../utils/asyncHandler');
const { requireAuth } = require('../middleware/auth');
const { cleanText, parseId } = require('../utils/validate');

const router = express.Router();
const ORDER_STATUSES = ['pending', 'completed', 'cancelled'];

function userError(message) {
  const error = new Error(message);
  error.userFacing = true;
  return error;
}

router.post(
  '/orders',
  requireAuth,
  asyncHandler(async (req, res) => {
    const entries = Object.entries(req.session.cart || {})
      .map(([id, quantity]) => ({ id: Number(id), quantity: Number(quantity) }))
      .filter((entry) => Number.isInteger(entry.id) && entry.id > 0 && entry.quantity > 0);

    if (entries.length === 0) {
      req.session.flash = { type: 'error', message: 'Your order is empty.' };
      return res.redirect('/cart');
    }

    const connection = await pool.getConnection();

    try {
      await connection.beginTransaction();

      const ids = entries.map((entry) => entry.id);
      const [products] = await connection.query(
        'SELECT id, name, price, stock FROM products WHERE id IN (?) FOR UPDATE',
        [ids]
      );
      const productMap = new Map(products.map((product) => [product.id, product]));

      let total = 0;
      const lines = [];

      for (const entry of entries) {
        const product = productMap.get(entry.id);
        if (!product) {
          throw userError('One of the products in your order is no longer available.');
        }
        if (product.stock < entry.quantity) {
          throw userError(`Only ${product.stock} of ${product.name} are available.`);
        }

        const price = Number(product.price);
        const subtotal = Math.round(price * entry.quantity * 100) / 100;
        total += subtotal;

        lines.push({
          productId: product.id,
          name: product.name,
          price,
          quantity: entry.quantity,
          subtotal
        });
      }

      total = Math.round(total * 100) / 100;

      const [orderResult] = await connection.query(
        'INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)',
        [req.user.id, total, 'pending']
      );
      const orderId = orderResult.insertId;

      await connection.query(
        `INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, subtotal)
         VALUES ?`,
        [
          lines.map((line) => [
            orderId,
            line.productId,
            line.name,
            line.price,
            line.quantity,
            line.subtotal
          ])
        ]
      );

      for (const line of lines) {
        const [update] = await connection.query(
          'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
          [line.quantity, line.productId, line.quantity]
        );
        if (update.affectedRows === 0) {
          throw userError(`Not enough stock for ${line.name}.`);
        }
      }

      await connection.commit();

      req.session.cart = {};
      req.session.flash = { type: 'success', message: `Order #${orderId} placed successfully.` };
      res.redirect(`/orders/${orderId}`);
    } catch (error) {
      await connection.rollback();
      if (error.userFacing) {
        req.session.flash = { type: 'error', message: error.message };
        return res.redirect('/cart');
      }
      throw error;
    } finally {
      connection.release();
    }
  })
);

router.get(
  '/orders',
  requireAuth,
  asyncHandler(async (req, res) => {
    const q = cleanText(req.query.q, 100);
    const conditions = [];
    const params = [];

    if (req.user.role !== 'admin') {
      conditions.push('o.user_id = ?');
      params.push(req.user.id);
    }

    if (q) {
      const numericId = /^\d+$/.test(q) ? Number(q) : null;
      const like = `%${q}%`;
      let search = `(u.name LIKE ? OR u.email LIKE ? OR o.status LIKE ?
        OR EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.product_name LIKE ?)`;
      params.push(like, like, like, like);
      if (numericId) {
        search += ' OR o.id = ?';
        params.push(numericId);
      }
      search += ')';
      conditions.push(search);
    }

    let sql = `SELECT o.id, o.total, o.status, o.created_at, u.name AS customer_name,
                 (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi
                  WHERE oi.order_id = o.id) AS item_count
               FROM orders o
               JOIN users u ON u.id = o.user_id`;

    if (conditions.length > 0) {
      sql += ` WHERE ${conditions.join(' AND ')}`;
    }

    sql += ' ORDER BY o.created_at DESC, o.id DESC LIMIT 100';

    const [orders] = await pool.query(sql, params);

    res.render('orders/index', {
      title: req.user.role === 'admin' ? 'All orders' : 'My orders',
      orders,
      q,
      isAdmin: req.user.role === 'admin'
    });
  })
);

router.get(
  '/orders/:id',
  requireAuth,
  asyncHandler(async (req, res) => {
    const id = parseId(req.params.id);
    if (!id) return res.status(404).render('404', { title: 'Order not found' });

    const [orderRows] = await pool.query(
      `SELECT o.*, u.name AS customer_name, u.email AS customer_email
       FROM orders o
       JOIN users u ON u.id = o.user_id
       WHERE o.id = ?`,
      [id]
    );

    if (orderRows.length === 0) {
      return res.status(404).render('404', { title: 'Order not found' });
    }

    const order = orderRows[0];

    if (req.user.role !== 'admin' && order.user_id !== req.user.id) {
      req.session.flash = { type: 'error', message: 'You do not have permission to view that order.' };
      return res.redirect('/orders');
    }

    const [items] = await pool.query(
      'SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC',
      [id]
    );

    res.render('orders/show', {
      title: `Order #${order.id}`,
      order,
      items,
      isAdmin: req.user.role === 'admin'
    });
  })
);

router.post(
  '/orders/:id/status',
  requireAuth,
  asyncHandler(async (req, res) => {
    if (req.user.role !== 'admin') {
      req.session.flash = { type: 'error', message: 'You do not have permission to do that.' };
      return res.redirect('/orders');
    }

    const id = parseId(req.params.id);
    const status = String(req.body.status || '');

    if (!id || !ORDER_STATUSES.includes(status)) {
      req.session.flash = { type: 'error', message: 'Invalid order status.' };
      return res.redirect('/orders');
    }

    const connection = await pool.getConnection();

    try {
      await connection.beginTransaction();

      const [rows] = await connection.query('SELECT id, status FROM orders WHERE id = ? FOR UPDATE', [id]);
      if (rows.length === 0) {
        await connection.rollback();
        return res.status(404).render('404', { title: 'Order not found' });
      }

      const currentStatus = rows[0].status;

      if (currentStatus === 'cancelled' && status !== 'cancelled') {
        await connection.rollback();
        req.session.flash = {
          type: 'error',
          message: 'Cancelled orders cannot be reopened. Create a new order instead.'
        };
        return res.redirect(`/orders/${id}`);
      }

      if (currentStatus !== status) {
        await connection.query('UPDATE orders SET status = ? WHERE id = ?', [status, id]);

        if (status === 'cancelled') {
          const [items] = await connection.query(
            'SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL',
            [id]
          );

          for (const item of items) {
            await connection.query('UPDATE products SET stock = stock + ? WHERE id = ?', [
              item.quantity,
              item.product_id
            ]);
          }
        }

        req.session.flash = { type: 'success', message: `Order #${id} marked as ${status}.` };
      }

      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }

    res.redirect(`/orders/${id}`);
  })
);

module.exports = router;

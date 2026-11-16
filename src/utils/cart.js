const pool = require('../db');

async function loadCart(cart = {}) {
  const ids = Object.keys(cart)
    .map(Number)
    .filter((id) => Number.isInteger(id) && id > 0);

  if (ids.length === 0) {
    return { items: [], total: 0, count: 0 };
  }

  const [rows] = await pool.query(
    'SELECT id, name, price, stock, image FROM products WHERE id IN (?)',
    [ids]
  );
  const products = new Map(rows.map((row) => [row.id, row]));

  const items = [];
  for (const id of ids) {
    const product = products.get(id);
    const requested = Number(cart[id]) || 0;
    if (!product) continue;

    const quantity = Math.min(Math.max(Math.floor(requested), 0), product.stock);
    if (quantity < 1) continue;

    const price = Number(product.price);
    items.push({
      id: product.id,
      name: product.name,
      price,
      quantity,
      stock: product.stock,
      image: product.image,
      subtotal: price * quantity
    });
  }

  const total = items.reduce((sum, item) => sum + item.subtotal, 0);
  const count = items.reduce((sum, item) => sum + item.quantity, 0);

  return { items, total, count };
}

function countCart(cart = {}) {
  return Object.values(cart).reduce((sum, quantity) => sum + (Number(quantity) || 0), 0);
}

module.exports = { loadCart, countCart };

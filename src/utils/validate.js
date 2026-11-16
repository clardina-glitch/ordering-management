const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function cleanText(value, maxLength) {
  return String(value || '').trim().slice(0, maxLength);
}

function parseId(value) {
  const id = Number(value);
  return Number.isInteger(id) && id > 0 ? id : null;
}

function parseQuantity(value, max = 9999) {
  const quantity = Number(value);
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > max) return null;
  return quantity;
}

function parsePrice(value) {
  const price = Number(String(value).trim());
  if (!Number.isFinite(price) || price < 0 || price > 99999999.99) return null;
  return Math.round(price * 100) / 100;
}

function parseStock(value) {
  const stock = Number(value);
  if (!Number.isInteger(stock) || stock < 0 || stock > 1000000) return null;
  return stock;
}

function validateProduct(body) {
  const errors = [];
  const name = cleanText(body.name, 120);
  const description = cleanText(body.description, 1000);
  const image = cleanText(body.image, 16) || '📦';
  const price = parsePrice(body.price);
  const stock = parseStock(body.stock);

  if (name.length < 2) errors.push('Product name must be at least 2 characters.');
  if (price === null) errors.push('Price must be a number between 0 and 99,999,999.99.');
  if (stock === null) errors.push('Stock must be a whole number of 0 or more.');

  return {
    errors,
    values: { name, description, image, price: body.price, stock: body.stock }
  };
}

function validateRegistration(body) {
  const errors = [];
  const name = cleanText(body.name, 80);
  const email = cleanText(body.email, 190).toLowerCase();
  const password = String(body.password || '');
  const confirm = String(body.confirmPassword || '');

  if (name.length < 2) errors.push('Name must be at least 2 characters.');
  if (!EMAIL_PATTERN.test(email)) errors.push('Enter a valid email address.');
  if (password.length < 6) errors.push('Password must be at least 6 characters.');
  if (password !== confirm) errors.push('Passwords do not match.');

  return { errors, values: { name, email } };
}

module.exports = {
  EMAIL_PATTERN,
  cleanText,
  parseId,
  parseQuantity,
  parsePrice,
  parseStock,
  validateProduct,
  validateRegistration
};

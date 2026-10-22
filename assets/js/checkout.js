/* ===== Checkout page =====
   Frontend-only for now. On submit, this should POST to php/order.php later. */
document.addEventListener("DOMContentLoaded", async () => {
  renderHeader("");
  renderFooter();

  const cart = getCart();
  if (!cart.length) {
    window.location.href = "cart.html";
    return;
  }

  let products = [];
  try {
    products = await fetchProducts();
  } catch (err) {
    console.error(err);
    return;
  }

  renderSummary(cart, products);
  bindCheckoutForm(cart, products);
});

function renderSummary(cart, products) {
  const rows = cart
    .map((item) => {
      const product = products.find((p) => p.id === item.id);
      if (!product) return "";
      return `<div class="summary-row"><span>${product.name} x${item.qty}</span><span>${formatPrice(product.price * item.qty)}</span></div>`;
    })
    .join("");

  const subtotal = cart.reduce((sum, item) => {
    const product = products.find((p) => p.id === item.id);
    return product ? sum + product.price * item.qty : sum;
  }, 0);
  const shipping = 50000;
  const total = subtotal + shipping;

  qs("#checkout-summary").innerHTML = `
    <h3>Đơn hàng của bạn</h3>
    ${rows}
    <div class="summary-row"><span>Phí vận chuyển</span><span>${formatPrice(shipping)}</span></div>
    <div class="summary-row total"><span>Tổng cộng</span><span>${formatPrice(total)}</span></div>
  `;
}

function bindCheckoutForm(cart, products) {
  const form = qs("#checkout-form");

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    if (!validateCheckoutForm(form)) return;

    // TODO: gửi dữ liệu đơn hàng tới backend PHP, ví dụ:
    // fetch("php/order.php", { method: "POST", body: new FormData(form) })

    clearCart();
    showToast("Đặt hàng thành công!");
    form.reset();
    setTimeout(() => {
      window.location.href = "index.html";
    }, 1200);
  });
}

function validateCheckoutForm(form) {
  let isValid = true;
  const setError = (field, message) => {
    const el = qs(`[data-error-for="${field}"]`);
    if (el) el.textContent = message;
    if (message) isValid = false;
  };

  const fullname = form.fullname.value.trim();
  setError("fullname", fullname.length < 2 ? "Vui lòng nhập họ tên hợp lệ" : "");

  const phone = form.phone.value.trim();
  setError("phone", !/^0\d{9,10}$/.test(phone) ? "Số điện thoại không hợp lệ" : "");

  const email = form.email.value.trim();
  setError("email", !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? "Email không hợp lệ" : "");

  const address = form.address.value.trim();
  setError("address", address.length < 5 ? "Vui lòng nhập địa chỉ giao hàng" : "");

  return isValid;
}

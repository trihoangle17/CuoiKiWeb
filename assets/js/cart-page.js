/* ===== Cart page ===== */
let cartProducts = [];

document.addEventListener("DOMContentLoaded", async () => {
  renderHeader("");
  renderFooter();

  try {
    cartProducts = await fetchProducts();
  } catch (err) {
    console.error(err);
    return;
  }

  renderCart();
});

function renderCart() {
  const root = qs("#cart-root");
  const cart = getCart();

  if (!cart.length) {
    root.innerHTML = `
      <div class="empty-state">
        <p>Giỏ hàng của bạn đang trống.</p>
        <a href="products.html" class="btn btn-primary" style="margin-top:14px">Tiếp tục mua sắm</a>
      </div>
    `;
    return;
  }

  const rows = cart
    .map((item) => {
      const product = cartProducts.find((p) => p.id === item.id);
      if (!product) return "";
      const lineTotal = product.price * item.qty;
      return `
        <tr data-id="${product.id}">
          <td>
            <div class="cart-product">
              <img src="${product.image}" alt="${product.name}">
              <a href="product.html?id=${product.id}">${product.name}</a>
            </div>
          </td>
          <td>${formatPrice(product.price)}</td>
          <td>
            <div class="qty-control">
              <button type="button" class="qty-dec">-</button>
              <input type="number" class="qty-input" value="${item.qty}" min="1" max="${product.stock}">
              <button type="button" class="qty-inc">+</button>
            </div>
          </td>
          <td>${formatPrice(lineTotal)}</td>
          <td><button class="cart-remove">Xóa</button></td>
        </tr>
      `;
    })
    .join("");

  const subtotal = cart.reduce((sum, item) => {
    const product = cartProducts.find((p) => p.id === item.id);
    return product ? sum + product.price * item.qty : sum;
  }, 0);
  const shipping = subtotal > 0 ? 50000 : 0;
  const total = subtotal + shipping;

  qs("#cart-root").innerHTML = `
    <div class="cart-layout">
      <table class="cart-table">
        <thead>
          <tr>
            <th>Sản phẩm</th>
            <th>Giá</th>
            <th>Số lượng</th>
            <th>Tổng</th>
            <th></th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>

      <div class="summary-box">
        <h3>Tổng đơn hàng</h3>
        <div class="summary-row"><span>Tạm tính</span><span>${formatPrice(subtotal)}</span></div>
        <div class="summary-row"><span>Phí vận chuyển</span><span>${formatPrice(shipping)}</span></div>
        <div class="summary-row total"><span>Tổng cộng</span><span>${formatPrice(total)}</span></div>
        <a href="checkout.html" class="btn btn-primary">Tiến hành thanh toán</a>
      </div>
    </div>
  `;

  bindCartRowEvents();
}

function bindCartRowEvents() {
  qsa(".cart-table tbody tr").forEach((row) => {
    const id = Number(row.dataset.id);
    const input = qs(".qty-input", row);

    qs(".qty-dec", row).addEventListener("click", () => {
      const val = Math.max(1, Number(input.value) - 1);
      input.value = val;
      updateCartQty(id, val);
      renderCart();
    });

    qs(".qty-inc", row).addEventListener("click", () => {
      const max = Number(input.max);
      const val = Math.min(max, Number(input.value) + 1);
      input.value = val;
      updateCartQty(id, val);
      renderCart();
    });

    input.addEventListener("change", () => {
      let val = Number(input.value) || 1;
      val = Math.max(1, Math.min(Number(input.max), val));
      updateCartQty(id, val);
      renderCart();
    });

    qs(".cart-remove", row).addEventListener("click", () => {
      removeFromCart(id);
      renderCart();
    });
  });
}

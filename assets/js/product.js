/* ===== Product detail page ===== */
document.addEventListener("DOMContentLoaded", async () => {
  renderHeader("products");
  renderFooter();

  const id = Number(getQueryParam("id"));
  let products = [];
  try {
    products = await fetchProducts();
  } catch (err) {
    console.error(err);
    return;
  }

  const product = products.find((p) => p.id === id);
  const root = qs("#product-detail-root");

  if (!product) {
    root.innerHTML = `<div class="empty-state">Không tìm thấy sản phẩm.</div>`;
    return;
  }

  document.title = `${product.name} - PCShop`;
  qs("#breadcrumb-name").textContent = product.name;
  renderDetail(product);

  const related = products
    .filter((p) => p.category === product.category && p.id !== product.id)
    .slice(0, 4);
  if (related.length) {
    qs("#related-section").style.display = "";
    renderProductGrid(qs("#related-grid"), related);
    bindAddToCartButtons();
  }
});

function renderDetail(product) {
  const hasSale = product.oldPrice && product.oldPrice > product.price;
  const specsRows = Object.entries(product.specs)
    .map(([key, value]) => `<tr><td>${key}</td><td>${value}</td></tr>`)
    .join("");

  const root = qs("#product-detail-root");
  root.innerHTML = `
    <div class="product-detail">
      <div class="gallery">
        <img src="${product.image}" alt="${product.name}">
      </div>
      <div class="detail-info">
        <h1>${product.name}</h1>
        <div class="meta">
          <span>Thương hiệu: ${product.brand}</span>
          <span>${renderStars(product.rating)} (${product.rating})</span>
        </div>

        <div class="price-block">
          <span class="price">${formatPrice(product.price)}</span>
          ${hasSale ? `<span class="old-price">${formatPrice(product.oldPrice)}</span>` : ""}
          <div class="stock">${product.stock > 0 ? `Còn hàng (${product.stock} sản phẩm)` : "Hết hàng"}</div>
        </div>

        <div class="qty-row">
          <div class="qty-control">
            <button type="button" id="qty-minus">-</button>
            <input type="number" id="qty-input" value="1" min="1" max="${product.stock}">
            <button type="button" id="qty-plus">+</button>
          </div>
        </div>

        <div class="action-row">
          <button class="btn btn-primary" id="add-to-cart-btn">Thêm vào giỏ hàng</button>
          <a href="cart.html" class="btn btn-outline" id="buy-now-btn">Mua ngay</a>
        </div>

        <div class="tabs">
          <div class="tab-buttons">
            <button type="button" class="active" data-tab="desc">Mô tả</button>
            <button type="button" data-tab="specs">Thông số kỹ thuật</button>
          </div>
          <div class="tab-panel active" data-panel="desc">
            <p>${product.description}</p>
          </div>
          <div class="tab-panel" data-panel="specs">
            <table class="spec-table">${specsRows}</table>
          </div>
        </div>
      </div>
    </div>
  `;

  bindDetailEvents(product);
}

function bindDetailEvents(product) {
  const qtyInput = qs("#qty-input");

  qs("#qty-minus").addEventListener("click", () => {
    qtyInput.value = Math.max(1, Number(qtyInput.value) - 1);
  });

  qs("#qty-plus").addEventListener("click", () => {
    qtyInput.value = Math.min(product.stock, Number(qtyInput.value) + 1);
  });

  qtyInput.addEventListener("change", () => {
    let val = Number(qtyInput.value) || 1;
    val = Math.max(1, Math.min(product.stock, val));
    qtyInput.value = val;
  });

  qs("#add-to-cart-btn").addEventListener("click", () => {
    addToCart(product.id, Number(qtyInput.value));
    showToast("Đã thêm vào giỏ hàng");
  });

  qs("#buy-now-btn").addEventListener("click", () => {
    addToCart(product.id, Number(qtyInput.value));
  });

  qsa(".tab-buttons button").forEach((btn) => {
    btn.addEventListener("click", () => {
      qsa(".tab-buttons button").forEach((b) => b.classList.remove("active"));
      qsa(".tab-panel").forEach((p) => p.classList.remove("active"));
      btn.classList.add("active");
      qs(`.tab-panel[data-panel="${btn.dataset.tab}"]`).classList.add("active");
    });
  });
}

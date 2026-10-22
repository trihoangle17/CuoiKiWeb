/* ===== Reusable render helpers ===== */
function productCardHTML(product) {
  const hasSale = product.oldPrice && product.oldPrice > product.price;
  const discount = hasSale
    ? Math.round(100 - (product.price / product.oldPrice) * 100)
    : 0;

  return `
    <div class="product-card" data-id="${product.id}">
      ${hasSale ? `<span class="badge-sale">-${discount}%</span>` : ""}
      <a href="product.html?id=${product.id}" class="thumb">
        <img src="${product.image}" alt="${product.name}" loading="lazy">
      </a>
      <div class="info">
        <span class="brand">${product.brand}</span>
        <a href="product.html?id=${product.id}" class="name">${product.name}</a>
        <span class="rating">${renderStars(product.rating)} (${product.rating})</span>
        <div class="price-row">
          <span class="price">${formatPrice(product.price)}</span>
          ${hasSale ? `<span class="old-price">${formatPrice(product.oldPrice)}</span>` : ""}
        </div>
        <button class="add-cart-btn" data-add-id="${product.id}">Thêm vào giỏ</button>
      </div>
    </div>
  `;
}

function renderProductGrid(container, products) {
  if (!products.length) {
    container.innerHTML = `<div class="empty-state">Không tìm thấy sản phẩm phù hợp.</div>`;
    return;
  }
  container.innerHTML = products.map(productCardHTML).join("");
}

function bindAddToCartButtons(scope = document) {
  qsa("[data-add-id]", scope).forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const id = Number(btn.dataset.addId);
      addToCart(id, 1);
      showToast("Đã thêm vào giỏ hàng");
    });
  });
}

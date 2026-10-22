/* ===== Shared header / footer, injected into #site-header / #site-footer ===== */
function renderHeader(activePage = "") {
  const el = qs("#site-header");
  if (!el) return;

  const nav = [
    { href: "index.html", key: "home", label: "Trang chủ" },
    { href: "products.html", key: "products", label: "Sản phẩm" },
    { href: "products.html?category=pc-build", key: "pc-build", label: "PC nguyên bộ" },
    { href: "about.html", key: "about", label: "Giới thiệu" },
    { href: "contact.html", key: "contact", label: "Liên hệ" },
  ];

  el.innerHTML = `
    <div class="header-top">
      <a href="index.html" class="logo">PC<span>Shop</span></a>
      <form class="search-form" action="products.html" method="get">
        <input type="text" name="search" placeholder="Tìm CPU, GPU, RAM, PC nguyên bộ...">
        <button type="submit">Tìm</button>
      </form>
      <div class="header-actions">
        <a href="login.html">👤 Tài khoản</a>
        <a href="cart.html">🛒 Giỏ hàng<span class="cart-count">0</span></a>
      </div>
    </div>
    <nav class="main-nav">
      <ul>
        ${nav
          .map(
            (item) =>
              `<li><a href="${item.href}" class="${item.key === activePage ? "active" : ""}">${item.label}</a></li>`
          )
          .join("")}
      </ul>
    </nav>
  `;

  updateCartBadge();
}

function renderFooter() {
  const el = qs("#site-footer");
  if (!el) return;

  el.innerHTML = `
    <div class="container">
      <div class="footer-grid">
        <div>
          <h4>PCShop</h4>
          <ul>
            <li><a href="about.html">Giới thiệu</a></li>
            <li><a href="contact.html">Liên hệ</a></li>
          </ul>
        </div>
        <div>
          <h4>Danh mục</h4>
          <ul>
            <li><a href="products.html?category=pc-build">PC nguyên bộ</a></li>
            <li><a href="products.html?category=cpu">CPU</a></li>
            <li><a href="products.html?category=gpu">Card đồ họa</a></li>
            <li><a href="products.html?category=ram">RAM</a></li>
          </ul>
        </div>
        <div>
          <h4>Hỗ trợ khách hàng</h4>
          <ul>
            <li><a href="contact.html">Chính sách bảo hành</a></li>
            <li><a href="contact.html">Hướng dẫn mua hàng</a></li>
            <li><a href="contact.html">Vận chuyển &amp; thanh toán</a></li>
          </ul>
        </div>
        <div>
          <h4>Tài khoản</h4>
          <ul>
            <li><a href="login.html">Đăng nhập</a></li>
            <li><a href="register.html">Đăng ký</a></li>
            <li><a href="cart.html">Giỏ hàng</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        &copy; 2024 PCShop. Website demo bán PC và linh kiện máy tính.
      </div>
    </div>
  `;
}

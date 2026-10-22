/* ===== Product listing page ===== */
let allProducts = [];

document.addEventListener("DOMContentLoaded", async () => {
  renderHeader("products");
  renderFooter();

  try {
    allProducts = await fetchProducts();
  } catch (err) {
    console.error(err);
    return;
  }

  setupFilterOptions();
  applyInitialParams();
  bindFilterEvents();
  applyFilters();
});

function setupFilterOptions() {
  const categorySelect = qs("#filter-category");
  const categories = [...new Set(allProducts.map((p) => p.category))];
  categories.forEach((cat) => {
    const opt = document.createElement("option");
    opt.value = cat;
    opt.textContent = categoryLabel(cat);
    categorySelect.appendChild(opt);
  });

  const brandSelect = qs("#filter-brand");
  const brands = [...new Set(allProducts.map((p) => p.brand))].sort();
  brands.forEach((brand) => {
    const opt = document.createElement("option");
    opt.value = brand;
    opt.textContent = brand;
    brandSelect.appendChild(opt);
  });

  const priceInput = qs("#filter-price");
  const maxPrice = Math.max(...allProducts.map((p) => p.price));
  priceInput.max = maxPrice;
  priceInput.value = maxPrice;
  qs("#price-value").textContent = formatPrice(maxPrice);
}

function applyInitialParams() {
  const category = getQueryParam("category");
  const search = getQueryParam("search");
  if (category) qs("#filter-category").value = category;
  if (search) qs("#search-term-storage")?.remove();
  window._searchTerm = search || "";
}

function bindFilterEvents() {
  qs("#filter-category").addEventListener("change", applyFilters);
  qs("#filter-brand").addEventListener("change", applyFilters);
  qs("#filter-sort").addEventListener("change", applyFilters);
  qs("#filter-price").addEventListener("input", (e) => {
    qs("#price-value").textContent = formatPrice(Number(e.target.value));
    applyFilters();
  });
  qs("#reset-filters").addEventListener("click", () => {
    qs("#filter-category").value = "";
    qs("#filter-brand").value = "";
    qs("#filter-sort").value = "default";
    const maxPrice = Math.max(...allProducts.map((p) => p.price));
    qs("#filter-price").value = maxPrice;
    qs("#price-value").textContent = formatPrice(maxPrice);
    window._searchTerm = "";
    applyFilters();
  });
}

function applyFilters() {
  const category = qs("#filter-category").value;
  const brand = qs("#filter-brand").value;
  const maxPrice = Number(qs("#filter-price").value);
  const sort = qs("#filter-sort").value;
  const search = (window._searchTerm || "").trim().toLowerCase();

  let results = allProducts.filter((p) => {
    if (category && p.category !== category) return false;
    if (brand && p.brand !== brand) return false;
    if (p.price > maxPrice) return false;
    if (search && !p.name.toLowerCase().includes(search)) return false;
    return true;
  });

  switch (sort) {
    case "price-asc":
      results.sort((a, b) => a.price - b.price);
      break;
    case "price-desc":
      results.sort((a, b) => b.price - a.price);
      break;
    case "rating-desc":
      results.sort((a, b) => b.rating - a.rating);
      break;
  }

  qs("#result-count").textContent = `${results.length} sản phẩm`;
  renderProductGrid(qs("#product-grid"), results);
  bindAddToCartButtons();
}

/* ===== Home page ===== */
document.addEventListener("DOMContentLoaded", async () => {
  renderHeader("home");
  renderFooter();

  try {
    const products = await fetchProducts();

    const featured = [...products].sort((a, b) => b.rating - a.rating).slice(0, 8);
    renderProductGrid(qs("#featured-grid"), featured);

    const pcBuilds = products.filter((p) => p.category === "pc-build");
    renderProductGrid(qs("#pcbuild-grid"), pcBuilds);

    bindAddToCartButtons();
  } catch (err) {
    console.error(err);
  }
});

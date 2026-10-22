/* ===== Shared helpers ===== */
function formatPrice(value) {
  return value.toLocaleString("vi-VN") + "đ";
}

function qs(selector, scope = document) {
  return scope.querySelector(selector);
}

function qsa(selector, scope = document) {
  return [...scope.querySelectorAll(selector)];
}

function getQueryParam(name) {
  return new URLSearchParams(window.location.search).get(name);
}

async function fetchProducts() {
  const res = await fetch("data/products.json");
  if (!res.ok) throw new Error("Không tải được dữ liệu sản phẩm");
  return res.json();
}

function renderStars(rating) {
  const full = Math.round(rating);
  return "★".repeat(full) + "☆".repeat(5 - full);
}

function showToast(message) {
  let toast = qs(".toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.className = "toast";
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => toast.classList.remove("show"), 2200);
}

const CATEGORY_LABELS = {
  "pc-build": "PC nguyên bộ",
  cpu: "CPU",
  gpu: "Card đồ họa",
  ram: "RAM",
  storage: "Ổ cứng",
  mainboard: "Mainboard",
  psu: "Nguồn (PSU)",
  case: "Case",
  monitor: "Màn hình",
  accessory: "Phụ kiện",
};

function categoryLabel(key) {
  return CATEGORY_LABELS[key] || key;
}

/* ===== Login / Register forms =====
   Frontend-only validation for now. Submit handlers should later
   POST to php/login.php and php/register.php. */
document.addEventListener("DOMContentLoaded", () => {
  renderHeader("");
  renderFooter();

  bindLoginForm();
  bindRegisterForm();
});

function setFieldError(field, message) {
  const el = qs(`[data-error-for="${field}"]`);
  if (el) el.textContent = message;
  return !message;
}

function bindLoginForm() {
  const form = qs("#login-form");
  if (!form) return;

  form.addEventListener("submit", (e) => {
    e.preventDefault();

    const email = form.email.value.trim();
    const password = form.password.value;

    let valid = true;
    valid = setFieldError("email", /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? "" : "Email không hợp lệ") && valid;
    valid = setFieldError("password", password.length >= 6 ? "" : "Mật khẩu phải có ít nhất 6 ký tự") && valid;

    if (!valid) return;

    // TODO: fetch("php/login.php", { method: "POST", body: new FormData(form) })
    showToast("Đăng nhập thành công (demo)");
    setTimeout(() => (window.location.href = "index.html"), 1000);
  });
}

function bindRegisterForm() {
  const form = qs("#register-form");
  if (!form) return;

  form.addEventListener("submit", (e) => {
    e.preventDefault();

    const fullname = form.fullname.value.trim();
    const email = form.email.value.trim();
    const password = form.password.value;
    const confirmPassword = form.confirmPassword.value;

    let valid = true;
    valid = setFieldError("fullname", fullname.length >= 2 ? "" : "Vui lòng nhập họ tên") && valid;
    valid = setFieldError("email", /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? "" : "Email không hợp lệ") && valid;
    valid = setFieldError("password", password.length >= 6 ? "" : "Mật khẩu phải có ít nhất 6 ký tự") && valid;
    valid = setFieldError("confirmPassword", confirmPassword === password ? "" : "Mật khẩu xác nhận không khớp") && valid;

    if (!valid) return;

    // TODO: fetch("php/register.php", { method: "POST", body: new FormData(form) })
    showToast("Đăng ký thành công (demo)");
    setTimeout(() => (window.location.href = "login.html"), 1000);
  });
}

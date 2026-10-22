/* ===== Contact page =====
   Frontend-only for now. Submit handler should later POST to php/contact.php */
document.addEventListener("DOMContentLoaded", () => {
  renderHeader("contact");
  renderFooter();

  const form = qs("#contact-form");
  form.addEventListener("submit", (e) => {
    e.preventDefault();

    const name = form.name.value.trim();
    const email = form.email.value.trim();
    const message = form.message.value.trim();

    let valid = true;
    const setError = (field, msg) => {
      qs(`[data-error-for="${field}"]`).textContent = msg;
      if (msg) valid = false;
    };

    setError("name", name.length < 2 ? "Vui lòng nhập họ tên" : "");
    setError("email", !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? "Email không hợp lệ" : "");
    setError("message", message.length < 5 ? "Vui lòng nhập nội dung" : "");

    if (!valid) return;

    // TODO: fetch("php/contact.php", { method: "POST", body: new FormData(form) })
    showToast("Gửi liên hệ thành công!");
    form.reset();
  });
});

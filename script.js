/* =====================================================
   HELPERS
===================================================== */
const onReady = (fn) => document.addEventListener("DOMContentLoaded", fn);

const normalizePage = (val = "") =>
  String(val)
    .trim()
    .toLowerCase()
    .replace(/^\/+/, "")
    .replace(/\.php$/, "");

/* =====================================================
   PAGE DETECTION
===================================================== */
function getCurrentPageKey() {
  const bodyKey = document.body?.dataset?.page;
  if (bodyKey) return normalizePage(bodyKey);

  let current = window.location.pathname.split("/").pop() || "";
  if (current === "" || current === "index.php") current = "beranda.php";
  return normalizePage(current);
}

/* =====================================================
   FEATURES
===================================================== */

/* === NAVBAR ACTIVE === */
function initNavActive() {
  const currentKey = getCurrentPageKey();

  document.querySelectorAll(".bottom-nav .nav-item, .nav-item").forEach((nav) => {
    const navKey = normalizePage(nav.dataset.page);
    nav.classList.toggle("active", navKey && navKey === currentKey);
  });
}

/* === SEARCH PAGE REDIRECT === */
function initOpenSearch() {
  const btn = document.getElementById("searchQuery");
  if (!btn) return;

  btn.addEventListener("click", () => {
    window.location.href = "search.php";
  });
}

/* === SEARCH HINT SLIDE (GOJEK STYLE) === */
function initSearchHintSlide() {
  const input = document.getElementById("searchQuery");
  const hint = document.getElementById("searchHint");
  if (!input || !hint) return;

  const texts = ["Cari laporan hari ini", "Cari berdasarkan petugas", "Cari area kerja", "Cari riwayat checklist"];

  let index = 0;
  let timer = null;

  const nextHint = () => {
    hint.classList.add("slide-up");

    setTimeout(() => {
      index = (index + 1) % texts.length;
      hint.textContent = texts[index];
      hint.classList.remove("slide-up");
    }, 450);
  };

  timer = setInterval(nextHint, 3000);

  const stop = () => {
    clearInterval(timer);
    input.parentElement.classList.add("active");
  };

  const resume = () => {
    if (input.value !== "") return;
    input.parentElement.classList.remove("active");
    timer = setInterval(nextHint, 3000);
  };

  input.addEventListener("focus", stop);
  input.addEventListener("input", stop);
  input.addEventListener("blur", resume);
}

/* === TOGGLE PASSWORD === */
function initTogglePassword() {
  document.querySelectorAll(".toggle-eye").forEach((icon) => {
    icon.addEventListener("click", () => {
      const input = icon.previousElementSibling;
      if (!input) return;

      input.type = input.type === "password" ? "text" : "password";
      icon.classList.toggle("fa-eye");
      icon.classList.toggle("fa-eye-slash");
    });
  });
}

/* === LOGOUT MODAL === */
function initLogoutModal() {
  const logoutLogo = document.getElementById("logoutLogo");
  const logoutModal = document.getElementById("logoutModal");
  const logoutBox = document.getElementById("logoutBox");
  const cancelLogout = document.getElementById("cancelLogout");
  const confirmLogout = document.getElementById("confirmLogout");

  if (!logoutLogo || !logoutModal || !logoutBox) return;

  logoutLogo.addEventListener("click", () => {
    logoutModal.classList.add("active");
    setTimeout(() => logoutBox.classList.add("show-modal"), 20);
  });

  cancelLogout?.addEventListener("click", () => {
    logoutBox.classList.remove("show-modal");
    logoutBox.classList.add("hide-modal");

    setTimeout(() => {
      logoutModal.classList.remove("active");
      logoutBox.classList.remove("hide-modal");
    }, 250);
  });

  confirmLogout?.addEventListener("click", () => {
    window.location.href = "logout.php";
  });

  logoutModal.addEventListener("click", (e) => {
    if (e.target === logoutModal) cancelLogout?.click();
  });
}

/* =====================================================
   INIT — ONE ENTRY POINT
===================================================== */
onReady(() => {
  initNavActive();
  initOpenSearch();
  initSearchHintSlide();
  initTogglePassword();
  initLogoutModal();
});

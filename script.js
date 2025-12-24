/* =====================================================
   HELPERS (GLOBAL)
===================================================== */
const onReady = (fn) => {
  if (document.readyState !== "loading") fn();
  else document.addEventListener("DOMContentLoaded", fn, { once: true });
};

const $id = (id) => document.getElementById(id);
const $qs = (sel, root = document) => root.querySelector(sel);
const $qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

const safeOn = (el, ev, fn, opt) => el && el.addEventListener(ev, fn, opt);

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
   FEATURE: NAV ACTIVE
===================================================== */
function initNavActive() {
  const currentKey = getCurrentPageKey();

  $qsa(".bottom-nav .nav-item, .nav-item").forEach((nav) => {
    const navKey = normalizePage(nav.dataset?.page || "");
    nav.classList.toggle("active", navKey && navKey === currentKey);
  });
}

/* =====================================================
   FEATURE: SEARCH
===================================================== */
function initOpenSearch() {
  const btn = $id("searchQuery");
  if (!btn) return;
  safeOn(btn, "click", () => (window.location.href = "search.php"));
}

function initSearchHintSlide() {
  const input = $id("searchQuery");
  const hint = $id("searchHint");
  if (!input || !hint) return;

  const texts = ["Cari laporan hari ini", "Cari berdasarkan petugas", "Cari area kerja", "Cari riwayat checklist"];

  let index = 0;
  let timer = setInterval(nextHint, 3000);

  function nextHint() {
    hint.classList.add("slide-up");
    setTimeout(() => {
      index = (index + 1) % texts.length;
      hint.textContent = texts[index];
      hint.classList.remove("slide-up");
    }, 450);
  }

  const stop = () => {
    clearInterval(timer);
    timer = null;
    input.parentElement?.classList.add("active");
  };

  const resume = () => {
    if (input.value !== "") return;
    input.parentElement?.classList.remove("active");
    if (!timer) timer = setInterval(nextHint, 3000);
  };

  safeOn(input, "focus", stop);
  safeOn(input, "input", stop);
  safeOn(input, "blur", resume);
}

/* =====================================================
   FEATURE: TOGGLE PASSWORD
===================================================== */
function initTogglePassword() {
  $qsa(".toggle-eye").forEach((icon) => {
    safeOn(icon, "click", () => {
      const input = icon.previousElementSibling;
      if (!input) return;

      input.type = input.type === "password" ? "text" : "password";
      icon.classList.toggle("fa-eye");
      icon.classList.toggle("fa-eye-slash");
    });
  });
}

/* =====================================================
   FEATURE: LOGOUT MODAL
===================================================== */
function initLogoutModal() {
  const logoutLogo = $id("logoutLogo");
  const logoutModal = $id("logoutModal");
  const logoutBox = $id("logoutBox");
  const cancel = $id("cancelLogout");
  const confirm = $id("confirmLogout");

  if (!logoutLogo || !logoutModal || !logoutBox) return;

  safeOn(logoutLogo, "click", () => {
    logoutModal.classList.add("active");
    setTimeout(() => logoutBox.classList.add("show-modal"), 20);
  });

  safeOn(cancel, "click", () => {
    logoutBox.classList.remove("show-modal");
    logoutBox.classList.add("hide-modal");
    setTimeout(() => {
      logoutModal.classList.remove("active");
      logoutBox.classList.remove("hide-modal");
    }, 250);
  });

  safeOn(confirm, "click", () => (window.location.href = "logout.php"));

  safeOn(logoutModal, "click", (e) => {
    if (e.target === logoutModal) cancel?.click();
  });
}

/* =====================================================
   FEATURE: BERANDA CAROUSEL
===================================================== */
function initBerandaCarousel() {
  const carousel = $id("carousel");
  if (!carousel) return;

  const items = $qsa(".carousel-item", carousel);
  const dots = $qsa(".dot");
  if (!items.length) return;

  let current = 0;
  let timer = null;

  const setDot = (i) => {
    dots.forEach((d) => d.classList.remove("active"));
    dots[i]?.classList.add("active");
  };

  const goTo = (i) => {
    current = (i + items.length) % items.length;
    carousel.scrollTo({
      left: items[current].offsetLeft - (carousel.clientWidth - items[current].clientWidth) / 2,
      behavior: "smooth",
    });
    setDot(current);
  };

  const start = () => {
    if (timer) return;
    timer = setInterval(() => goTo(current + 1), 4000);
  };

  const stop = () => {
    clearInterval(timer);
    timer = null;
  };

  if ("IntersectionObserver" in window) {
    new IntersectionObserver(([e]) => (e.isIntersecting ? start() : stop()), { threshold: 0.4 }).observe(carousel);
  } else start();

  dots.forEach((d, i) =>
    safeOn(d, "click", () => {
      goTo(i);
      stop();
      start();
    })
  );

  setDot(0);
}

/* =====================================================
   FEATURE: LATEST ACTIVITY
===================================================== */
function initLatestActivity() {
  const container = $id("latestActivity");
  if (!container) return;

  const url = "api/get_latest_activity.php";
  setInterval(async () => {
    try {
      container.classList.remove("show");
      await new Promise((r) => setTimeout(r, 250));

      const res = await fetch(url, { cache: "no-store" });
      if (!res.ok) return;

      container.innerHTML = await res.text();
      container.classList.add("show");
    } catch (e) {
      console.error(e);
    }
  }, 15000);
}

function initPetugasDropdown({ inputId = "petugasInput", dropdownId = "petugasDropdown", itemSelector = ".petugas-item", dataAttr = "nama" } = {}) {
  const input = document.getElementById(inputId);
  const dropdown = document.getElementById(dropdownId);
  if (!input || !dropdown) return;

  const items = dropdown.querySelectorAll(itemSelector);

  const filter = (keyword) => {
    let visible = false;
    items.forEach((item) => {
      const nama = (item.dataset[dataAttr] || item.textContent).toLowerCase();
      const match = nama.includes(keyword);
      item.style.display = match ? "block" : "none";
      if (match) visible = true;
    });
    dropdown.classList.toggle("hidden", !visible);
  };

  input.addEventListener("input", () => filter(input.value.toLowerCase().trim()));

  input.addEventListener("focus", () => filter(""));

  items.forEach((item) =>
    item.addEventListener("click", () => {
      input.value = item.textContent.trim();
      dropdown.classList.add("hidden");
    })
  );

  document.addEventListener("click", (e) => {
    if (!e.target.closest(".relative")) {
      dropdown.classList.add("hidden");
    }
  });
}

/* =====================================================
   ENTRY POINT — ONE PLACE ONLY
===================================================== */
onReady(() => {
  initNavActive();
  initOpenSearch();
  initSearchHintSlide();
  initTogglePassword();
  initLogoutModal();
  initLatestActivity();
  initBerandaCarousel();
  initPetugasDropdown();
});

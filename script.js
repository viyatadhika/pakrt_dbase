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

function initLatestActivity() {
  const box = document.getElementById("latestActivity");
  if (!box) return;

  fetch("api/get_latest_activity.php")
    .then((r) => r.text())
    .then((html) => (box.innerHTML = html));
}

function initBerandaCarousel() {
  const carousel = document.getElementById("carousel");
  if (!carousel) return;

  const items = Array.from(carousel.querySelectorAll(".carousel-item"));
  const dots = Array.from(document.querySelectorAll(".dot"));
  if (!items.length) return;

  let currentIndex = 0;
  let autoTimer = null;

  /* ===============================
     HELPER
  ============================== */
  const setActiveDot = (index) => {
    dots.forEach((d) => d.classList.remove("active"));
    dots[index]?.classList.add("active");
  };

  const scrollToItem = (index) => {
    const target = items[index];
    if (!target) return;

    const left = target.offsetLeft - (carousel.clientWidth - target.clientWidth) / 2;

    carousel.scrollTo({
      left,
      behavior: "smooth",
    });
  };

  const goTo = (index) => {
    currentIndex = (index + items.length) % items.length;
    scrollToItem(currentIndex);
    setActiveDot(currentIndex);
  };

  /* ===============================
     AUTO SLIDE
  ============================== */
  const startAuto = () => {
    if (autoTimer || items.length <= 1) return;
    autoTimer = setInterval(() => {
      goTo(currentIndex + 1);
    }, 4000);
  };

  const stopAuto = () => {
    clearInterval(autoTimer);
    autoTimer = null;
  };

  /* ===============================
     OBSERVER (AMAN, TIDAK NARIK KE ATAS)
  ============================== */
  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) startAuto();
          else stopAuto();
        });
      },
      { threshold: 0.4 }
    );
    observer.observe(carousel);
  } else {
    startAuto();
  }

  /* ===============================
     DOT CLICK
  ============================== */
  dots.forEach((dot, i) => {
    dot.addEventListener("click", () => {
      goTo(i);
      stopAuto();
      startAuto();
    });
  });

  /* ===============================
     UPDATE DOT SAAT SWIPE MANUAL
  ============================== */
  let scrollTimeout;
  carousel.addEventListener("scroll", () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
      const center = carousel.scrollLeft + carousel.clientWidth / 2;
      let nearest = 0;
      let min = Infinity;

      items.forEach((item, i) => {
        const dist = Math.abs(item.offsetLeft + item.clientWidth / 2 - center);
        if (dist < min) {
          min = dist;
          nearest = i;
        }
      });

      currentIndex = nearest;
      setActiveDot(currentIndex);
    }, 80);
  });

  /* ===============================
     INIT
  ============================== */
  setActiveDot(0);
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
  initLatestActivity();
  initBerandaCarousel();
});

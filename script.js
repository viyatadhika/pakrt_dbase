/* ======= PAGE IDENTIFIER ======= */
const bodyPage = document.body.dataset.page;

/* ======= SLIDING DETAIL SHEET ======= */
function openSheet(id) {
  document.getElementById(id)?.classList.add("show");
}
function closeSheet(id) {
  document.getElementById(id)?.classList.remove("show");
}

/* ======= CHART LOADER ======= */
function loadChart(canvasId, type, labels, data, color) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  new Chart(canvas, {
    type,
    data: {
      labels,
      datasets: [
        {
          data,
          borderColor: color,
          backgroundColor: color + "33",
          fill: true,
          tension: 0.4,
        },
      ],
    },
    options: { responsive: true },
  });
}

/* ======= DOM CONTENT LOADED ======= */
document.addEventListener("DOMContentLoaded", () => {
  /* --- NAVIGATION ACTIVE STATE --- */
  document.querySelectorAll(".nav-item").forEach((item) => {
    if (item.dataset.page === bodyPage) {
      item.classList.add("active");
    }
  });

  /* --- LATEST ACTIVITY IN BERANDA --- */
  if (bodyPage === "beranda") {
    const box = document.getElementById("latestActivity");
    if (box) {
      fetch("api/get_latest_activity.php")
        .then((r) => r.text())
        .then((html) => {
          box.innerHTML = html;
        });
    }
  }

  /* --- PREVIEW FOTO PROFIL --- */
  const input = document.getElementById("fotoProfilInput");
  const preview = document.getElementById("fotoProfilPreview");
  const btnSimpan = document.getElementById("btnSimpanFoto");

  if (input && preview && btnSimpan) {
    input.addEventListener("change", function () {
      const file = this.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (e) => {
        if (preview.tagName === "DIV") {
          preview.outerHTML = `
            <img id="fotoProfilPreview"
                 src="${e.target.result}"
                 class="w-24 h-24 rounded-full object-cover border shadow">
          `;
        } else {
          preview.src = e.target.result;
        }
      };

      reader.readAsDataURL(file);
      btnSimpan.classList.remove("hidden");
    });
  }
});

document.addEventListener("DOMContentLoaded", () => {
  new Swiper(".mySwiper", {
    slidesPerView: 1.15,
    spaceBetween: 16,
    loop: true,
    autoplay: {
      delay: 2500,
      disableOnInteraction: false,
    },
  });
});

// === CAROUSEL BARU (AMAN, TIDAK NARIK KE ATAS) ===
document.addEventListener("DOMContentLoaded", () => {
  const carousel = document.getElementById("carousel");
  if (!carousel) return; // kalau bukan di beranda, langsung keluar

  const items = Array.from(document.querySelectorAll(".carousel-item"));
  const dots = Array.from(document.querySelectorAll(".dot"));
  if (!items.length) return;

  let currentIndex = 0;
  let autoTimer = null;

  // aktifkan dot sesuai index
  const setActiveDot = (index) => {
    dots.forEach((d) => d.classList.remove("active"));
    if (dots[index]) dots[index].classList.add("active");
  };

  // scroll horizontal saja ke item tertentu
  const scrollToItem = (index) => {
    const target = items[index];
    if (!target) return;

    const targetLeft = target.offsetLeft - (carousel.clientWidth - target.clientWidth) / 2;

    carousel.scrollTo({
      left: targetLeft,
      behavior: "smooth",
    });
  };

  const goTo = (index) => {
    currentIndex = (index + items.length) % items.length;
    scrollToItem(currentIndex);
    setActiveDot(currentIndex);
  };

  // === AUTO SLIDE ===
  const startAuto = () => {
    if (autoTimer || items.length <= 1) return;
    autoTimer = setInterval(() => {
      goTo(currentIndex + 1);
    }, 4000); // 4 detik per slide
  };

  const stopAuto = () => {
    if (!autoTimer) return;
    clearInterval(autoTimer);
    autoTimer = null;
  };

  // Jalankan auto slide hanya saat carousel kelihatan di layar
  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.target !== carousel) return;
          if (entry.isIntersecting) startAuto();
          else stopAuto();
        });
      },
      { threshold: 0.3 }
    );
    observer.observe(carousel);
  } else {
    startAuto();
  }

  // === KLIK DOT ===
  dots.forEach((dot, i) => {
    dot.addEventListener("click", () => {
      goTo(i);
      // reset auto-slide biar geser lagi dari posisi baru
      stopAuto();
      startAuto();
    });
  });

  // === UPDATE DOT SAAT USER SWIPE MANUAL ===
  let scrollTimeout;
  carousel.addEventListener("scroll", () => {
    if (scrollTimeout) clearTimeout(scrollTimeout);

    scrollTimeout = setTimeout(() => {
      const center = carousel.scrollLeft + carousel.clientWidth / 2;
      let nearestIndex = 0;
      let nearestDist = Infinity;

      items.forEach((item, i) => {
        const itemCenter = item.offsetLeft + item.clientWidth / 2;
        const dist = Math.abs(itemCenter - center);
        if (dist < nearestDist) {
          nearestDist = dist;
          nearestIndex = i;
        }
      });

      currentIndex = nearestIndex;
      setActiveDot(currentIndex);
    }, 80);
  });

  // set awal
  setActiveDot(0);
});

// === LOGOUT MODAL ===
document.addEventListener("DOMContentLoaded", () => {
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

  cancelLogout.addEventListener("click", () => {
    logoutBox.classList.remove("show-modal");
    logoutBox.classList.add("hide-modal");
    setTimeout(() => {
      logoutModal.classList.remove("active");
      logoutBox.classList.remove("hide-modal");
    }, 250);
  });

  confirmLogout.addEventListener("click", () => {
    window.location.href = "logout.php";
  });

  logoutModal.addEventListener("click", (e) => {
    if (e.target === logoutModal) cancelLogout.click();
  });
});

// === SHEET UPLOAD PRESENSI PPPK ===
document.addEventListener("DOMContentLoaded", () => {
  const openUploadPresensi = document.getElementById("openUploadPresensi");
  const sheetPresensi = document.getElementById("sheetPresensi");
  const fadeBgPresensi = document.getElementById("fadeBgPresensi");
  const closeSheetPresensi = document.getElementById("closeSheetPresensi");
  const sheetPresensiContent = document.getElementById("sheetPresensiContent");

  // kalau elemen-elemen ini tidak ada (misal bukan di lainnya.php) → stop
  if (!openUploadPresensi || !sheetPresensi || !fadeBgPresensi || !closeSheetPresensi || !sheetPresensiContent) return;

  const openSheetPresensi = () => {
    sheetPresensi.style.transition = "none";
    sheetPresensi.style.transform = "translateY(100%)";

    requestAnimationFrame(() => {
      sheetPresensi.classList.add("show");
      fadeBgPresensi.classList.add("show");
      sheetPresensi.style.transition = "transform 0.35s ease";
      sheetPresensi.style.transform = "translateY(0)";
    });
  };

  const closeSheetPresensiFn = () => {
    sheetPresensi.style.transition = "transform 0.35s ease";
    sheetPresensi.style.transform = "translateY(100%)";
    fadeBgPresensi.classList.remove("show");

    setTimeout(() => {
      sheetPresensi.classList.remove("show");
      sheetPresensi.style.transform = "translateY(100%)";
    }, 400);
  };

  // buka sheet saat klik menu Upload Presensi
  openUploadPresensi.addEventListener("click", openSheetPresensi);

  // klik background / tombol X → tutup
  fadeBgPresensi.addEventListener("click", closeSheetPresensiFn);
  closeSheetPresensi.addEventListener("click", closeSheetPresensiFn);

  // SWIPE DOWN untuk tutup (kayak sheet sebelumnya)
  let startY = 0,
    currentY = 0,
    isDragging = false,
    canClose = false;

  sheetPresensiContent.addEventListener("touchstart", (e) => {
    if (sheetPresensiContent.scrollTop <= 0) {
      canClose = true;
      startY = e.touches[0].clientY;
      isDragging = true;
      sheetPresensi.style.transition = "none";
    } else {
      canClose = false;
    }
  });

  sheetPresensiContent.addEventListener("touchmove", (e) => {
    if (!isDragging || !canClose) return;

    currentY = e.touches[0].clientY;
    const deltaY = currentY - startY;

    if (deltaY > 0) {
      e.preventDefault();
      sheetPresensi.style.transform = `translateY(${deltaY}px)`;
    }
  });

  sheetPresensiContent.addEventListener("touchend", () => {
    if (!isDragging) return;
    isDragging = false;

    const deltaY = currentY - startY;
    sheetPresensi.style.transition = "transform 0.35s ease";

    if (canClose && deltaY > 100) {
      closeSheetPresensiFn();
    } else {
      sheetPresensi.style.transform = "translateY(0)";
    }
  });
});

// === TOGGLE PASSWORD VISIBILITY ===
document.addEventListener("DOMContentLoaded", () => {
  const toggleIcons = document.querySelectorAll(".toggle-eye");
  toggleIcons.forEach((icon) => {
    icon.addEventListener("click", () => {
      const input = icon.previousElementSibling;
      const type = input.getAttribute("type") === "password" ? "text" : "password";
      input.setAttribute("type", type);
      icon.classList.toggle("fa-eye");
      icon.classList.toggle("fa-eye-slash");
    });
  });
});

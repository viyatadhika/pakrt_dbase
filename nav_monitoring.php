<nav class="bottom-nav">

    <a href="beranda.php" class="nav-item <?= $activePage === 'beranda.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i>
        <span>Beranda</span>
    </a>

    <a href="riwayat.php" class="nav-item <?= $activePage === 'riwayat.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>Riwayat</span>
    </a>

    <a href="statistik.php" class="nav-item <?= $activePage === 'statistik.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-column"></i>
        <span>Statistik</span>
    </a>

    <a href="lainnya.php" class="nav-item <?= $activePage === 'lainnya.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-layer-group"></i>
        <span>Lainnya</span>
    </a>

    <a href="profil.php" class="nav-item <?= $activePage === 'profil.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-user"></i>
        <span>Profil</span>
    </a>

</nav>
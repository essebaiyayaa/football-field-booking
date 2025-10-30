<?php
// S'assurer que la session est démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- Header & Navigation -->
<header>
<nav>
    <a href="home.php" class="logo">
        FootBooking
    </a>
    
    <ul class="nav-links">
        <li><a href="home.php">Accueil</a></li>
        <li><a href="terrains.php">Liste des terrains</a></li>
        <li><a href="reservation.php">Réserver un terrain</a></li>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'client'): ?>
            <li><a href="mes-reservations.php">Mes réservations</a></li>
        <?php endif; ?>
    </ul>

    <div class="auth-buttons">
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Utilisateur connecté -->
            <div class="user-menu">
                <button class="user-button" onclick="toggleUserMenu()">
                    <i class="fa-solid fa-user-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu" id="userDropdown">
                    <a href="profile.php">
                        <i class="fa-solid fa-user"></i> Mon profil
                    </a>
                    <a href="mes_reservations.php">
                        <i class="fa-solid fa-calendar-check"></i> Mes réservations
                    </a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="dashboard_admin.php">
                            <i class="fa-solid fa-gauge"></i> Dashboard Admin
                        </a>
                    <?php elseif ($_SESSION['role'] === 'gerant_terrain'): ?>
                        <a href="dashboard_gerant.php">
                            <i class="fa-solid fa-gauge"></i> Dashboard Gérant
                        </a>
                    <?php endif; ?>
                    <hr>
                    <a href="logout.php" class="logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Se déconnecter
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Utilisateur non connecté -->
            <a href="login.php" class="btn btn-outline">
                <i class="fa-solid fa-right-to-bracket"></i>
                Se connecter
            </a>
            <a href="register.php" class="btn btn-primary">
                <i class="fa-solid fa-user-plus"></i>
                S'inscrire
            </a>
        <?php endif; ?>
    </div>
</nav>
</header>

<style>
    /* Menu utilisateur */
    .user-menu {
        position: relative;
    }

    .user-button {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        background: #16a34a;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }

    .user-button:hover {
        background: #15803d;
    }

    .user-button i.fa-user-circle {
        font-size: 1.5rem;
    }

    .user-button i.fa-chevron-down {
        font-size: 0.8rem;
        transition: transform 0.3s;
    }

    .user-button:hover i.fa-chevron-down {
        transform: rotate(180deg);
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 0.5rem;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 220px;
        display: none;
        z-index: 1000;
    }

    .dropdown-menu.show {
        display: block;
        animation: fadeIn 0.2s;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1.25rem;
        color: #374151;
        text-decoration: none;
        transition: background 0.2s;
    }

    .dropdown-menu a:hover {
        background: #f3f4f6;
    }

    .dropdown-menu a.logout {
        color: #dc2626;
    }

    .dropdown-menu a.logout:hover {
        background: #fee2e2;
    }

    .dropdown-menu hr {
        margin: 0.5rem 0;
        border: none;
        border-top: 1px solid #e5e7eb;
    }
</style>

<script>
    function toggleUserMenu() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('show');
    }

    // Fermer le menu si on clique en dehors
    document.addEventListener('click', function(event) {
        const userMenu = document.querySelector('.user-menu');
        if (userMenu && !userMenu.contains(event.target)) {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
        }
    });
</script>
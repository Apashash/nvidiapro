<?php
// menu.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}
?>

<style>
    :root {
        /* Couleurs NVIDIA */
        --accent-green: #76B900; /* Vert Pomme NVIDIA */
        --accent-black: #000000;
        --menu-bg: rgba(0, 0, 0, 0.95); /* Noir très sombre */
        --menu-border: rgba(118, 185, 0, 0.5); /* Bordure verte subtile */
        --text-inactive: rgba(255, 255, 255, 0.6);
        --text-active: #ffffff;
        --shadow-color: rgba(118, 185, 0, 0.4);
    }

    /* Navbar aux dimensions originales avec effet glass - TOUJOURS VISIBLE */
    .bottom-nav {
        position: fixed;
        bottom: 12px;
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        max-width: 320px; /* Légèrement plus large pour un look plus "pro" */
        background: var(--menu-bg);
        backdrop-filter: blur(20px) saturate(150%);
        -webkit-backdrop-filter: blur(20px) saturate(150%);
        border: 1px solid var(--menu-border);
        border-radius: 12px; /* Plus angulaire */
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 10px 0; /* Plus de padding */
        z-index: 9999;
        box-shadow: 
            0 5px 25px var(--shadow-color),
            inset 0 1px 0 rgba(255, 255, 255, 0.05); /* Effet lumineux */
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease; /* Transition plus rebondie */
    }

    /* Animation subtile au scroll */
    .bottom-nav.scroll-down {
        transform: translateX(-50%) translateY(5px) scale(0.98);
        opacity: 0.95;
    }

    .bottom-nav.scroll-up {
        transform: translateX(-50%) translateY(0) scale(1.0);
        opacity: 1;
    }

    .nav-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        color: var(--text-inactive);
        text-decoration: none;
        font-size: 11px;
        transition: all 0.3s ease-out;
        position: relative;
        padding: 5px 3px;
        border-radius: 6px;
        margin: 0 5px;
        text-transform: uppercase;
        font-family: 'Arial', sans-serif;
    }

    .nav-item i {
        font-size: 18px; /* Icônes plus grandes */
        margin-bottom: 4px;
        transition: all 0.3s ease-out;
        font-weight: 500;
    }

    .nav-item:hover {
        color: var(--text-active);
        background: rgba(118, 185, 0, 0.1); /* Hover vert discret */
        transform: translateY(-2px); /* Léger soulèvement */
    }

    .nav-item.active {
        color: var(--accent-green); /* Texte actif en vert NVIDIA */
        font-weight: bold;
    }

    .nav-item.active i {
        color: var(--accent-green);
        text-shadow: 0 0 5px var(--accent-green); /* Effet néon */
    }

    .nav-item span {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.8px; /* Espacement pour le look tech */
        line-height: 1.2;
    }

    /* Indicateur d'item actif - barre fine et lumineuse */
    .nav-item.active::after {
        content: '';
        position: absolute;
        top: -10px; /* Au-dessus de l'icône */
        left: 50%;
        transform: translateX(-50%);
        width: 80%;
        height: 2px;
        background: var(--accent-green);
        box-shadow: 0 0 10px var(--accent-green); /* Néon */
        border-radius: 1px;
        animation: active-glow 1.5s infinite alternate;
    }
    
    @keyframes active-glow {
        from { opacity: 0.7; }
        to { opacity: 1; }
    }

    /* Animation liquid "glitch" */
    .bottom-nav::before {
        content: ''; /* Texte tech sur la barre */
        position: absolute;
        top: -25px;
        left: 50%;
        transform: translateX(-50%);
        color: var(--accent-green);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 2px;
        opacity: 0.8;
        z-index: -1;
        transition: opacity 0.3s ease;
    }
    
    .bottom-nav.liquid-animate::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            90deg,
            transparent,
            rgba(118, 185, 0, 0.2),
            rgba(118, 185, 0, 0.05),
            transparent
        );
        transition: left 0.4s ease-out;
        border-radius: 12px;
        pointer-events: none;
        z-index: 10;
    }

    .bottom-nav.liquid-animate::after {
        left: 100%;
    }


    /* Responsive */
    @media (max-width: 400px) {
        .bottom-nav {
            width: 90%;
            max-width: 300px;
            padding: 8px 0;
        }
        
        .nav-item i {
            font-size: 16px;
        }
        
        .nav-item span {
            font-size: 9px;
            letter-spacing: 0.5px;
        }
    }

    /* S'assurer que le contenu n'est pas caché derrière le menu */
    body {
        background-color: #121212; /* Fond sombre pour le thème */
        color: #ffffff;
        padding-bottom: 90px !important;
    }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<nav class="bottom-nav" id="bottomNav">
    <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
        <i class="fas fa-microchip"></i> <span>Système</span>
    </a>
    <a href="investissement.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'investissement.php' ? 'active' : ''; ?>">
        <i class="fas fa-desktop"></i> <span>Action</span>
    </a>
    <a href="equipe.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'equipe.php' ? 'active' : ''; ?>">
        <i class="fas fa-robot"></i> <span>Equipe</span>
    </a>
    <a href="compte.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'compte.php' ? 'active' : ''; ?>">
        <i class="fas fa-fingerprint"></i> <span>Profil</span>
    </a>
</nav>

<script>
    // Gestion du scroll - NE PLUS CACHER LE MENU
    let lastScrollTop = 0;
    let scrollTimeout;

    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        const bottomNav = document.getElementById('bottomNav');

        // Clear le timeout précédent
        clearTimeout(scrollTimeout);

        // Animation subtile sans cacher le menu
        if (currentScroll > lastScrollTop && currentScroll > 50) {
            // Scroll vers le bas - légère translation
            bottomNav.classList.remove('scroll-up');
            bottomNav.classList.add('scroll-down');
        } else {
            // Scroll vers le haut - position normale
            bottomNav.classList.remove('scroll-down');
            bottomNav.classList.add('scroll-up');
        }

        lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;

        // Retour à l'état normal après arrêt du scroll
        scrollTimeout = setTimeout(() => {
            bottomNav.classList.remove('scroll-down', 'scroll-up');
        }, 300);
    });

    // Animation liquid seulement au changement de page
    document.addEventListener('DOMContentLoaded', function() {
        const navItems = document.querySelectorAll('.nav-item');
        const bottomNav = document.getElementById('bottomNav');

        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Vérifier si c'est un lien actif différent
                if (!this.classList.contains('active')) {
                    // Animation liquid au clic
                    bottomNav.classList.add('liquid-animate');
                    
                    // Empêcher la navigation immédiate pour laisser l'animation se produire
                    e.preventDefault(); 
                    const targetHref = this.getAttribute('href');

                    setTimeout(() => {
                        // Retirer la classe active de tous les items
                        navItems.forEach(nav => nav.classList.remove('active'));
                        
                        // Ajouter la classe active à l'item cliqué
                        this.classList.add('active');
                        
                        // Fin de l'animation et navigation
                        setTimeout(() => {
                            bottomNav.classList.remove('liquid-animate');
                            window.location.href = targetHref; // Naviguer après l'animation
                        }, 300);
                    }, 150);
                }
            });
        });

        // Animation d'apparition initiale
        setTimeout(() => {
            bottomNav.classList.add('liquid-animate');
            setTimeout(() => {
                bottomNav.classList.remove('liquid-animate');
            }, 600);
        }, 500);
    });

    // Support tactile amélioré - sans cacher le menu
    let startY = 0;

    document.addEventListener('touchstart', function(e) {
        startY = e.touches[0].clientY;
    });

    document.addEventListener('touchmove', function(e) {
        const currentY = e.touches[0].clientY;
        const diff = startY - currentY;
        const bottomNav = document.getElementById('bottomNav');
        
        // Animation subtile pendant le scroll tactile
        if (Math.abs(diff) > 10) {
            if (diff > 0) {
                // Scroll vers le bas
                bottomNav.classList.remove('scroll-up');
                bottomNav.classList.add('scroll-down');
            } else {
                // Scroll vers le haut
                bottomNav.classList.remove('scroll-down');
                bottomNav.classList.add('scroll-up');
            }
        }
    });

    document.addEventListener('touchend', function() {
        const bottomNav = document.getElementById('bottomNav');
        setTimeout(() => {
            bottomNav.classList.remove('scroll-down', 'scroll-up');
        }, 300);
    });

    // S'assurer que le menu est toujours visible
    function ensureMenuVisibility() {
        const bottomNav = document.getElementById('bottomNav');
        bottomNav.style.transform = 'translateX(-50%) translateY(0)';
        bottomNav.style.opacity = '1';
        bottomNav.classList.remove('scroll-down');
        bottomNav.classList.add('scroll-up');
    }

    // Vérifier périodiquement que le menu est visible
    setInterval(ensureMenuVisibility, 1000);
</script>
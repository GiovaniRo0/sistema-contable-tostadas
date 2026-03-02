<?php
// inicio.php - Página de Bienvenida / Punto de Entrada
require_once 'auth.php';

// CLAVE: La variable debe coincidir con el menú para que se ilumine
$currentPage = 'inicio';
include 'menu.php';

// Obtener el nombre de usuario (si está disponible)
$nombre_usuario = $_SESSION['nombre_usuario'] ?? 'Usuario';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .header-welcome {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .action-button {
            transition: all 0.2s;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .action-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body class="bg-light">
    
    <div class="container py-5">
        
        <header class="header-welcome mb-5">
            <h1 class="display-4 fw-bold"><i class="bi bi-shop me-3"></i> Tostadas Jela</h1>
            <p class="lead">¡Hola, <?= htmlspecialchars($nombre_usuario) ?>! Bienvenido al Sistema de Gestión.</p>
            <p class="mb-0">Tu plataforma central para todas las operaciones diarias.</p>
        </header>

        <section class="mb-5">
            <h2 class="text-primary mb-4"><i class="bi bi-lightning-fill me-2"></i> Acciones Rápidas</h2>
            <div class="row g-4 text-center">
                
                <div class="col-md-4">
                    <button onclick="window.location.href='ventas.php'" class="btn btn-success btn-lg w-100 py-3 action-button">
                        <i class="bi bi-cash-stack display-6 d-block mb-2"></i>
                        Nueva Venta
                    </button>
                </div>
                
                <div class="col-md-4">
                    <button onclick="window.location.href='productos.php'" class="btn btn-warning btn-lg w-100 py-3 action-button text-dark">
                        <i class="bi bi-box-seam display-6 d-block mb-2"></i>
                        Gestionar Inventario
                    </button>
                </div>
                
                <div class="col-md-4">
                    <button onclick="window.location.href='dashboard.php'" class="btn btn-info btn-lg w-100 py-3 action-button text-white">
                        <i class="bi bi-bar-chart-line display-6 d-block mb-2"></i>
                        Panel Ejecutivo (Dashboard)
                    </button>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-secondary mb-4"><i class="bi bi-gear-fill me-2"></i> Administración</h2>
            <div class="row g-3">
                
                <div class="col-md-3">
                    <button onclick="window.location.href='gastos.php'" class="btn btn-outline-danger w-100 py-2 action-button">
                        <i class="bi bi-wallet me-2"></i> Registrar Egreso
                    </button>
                </div>
                
                <div class="col-md-3">
                    <button onclick="window.location.href='historial_productos.php'" class="btn btn-outline-secondary w-100 py-2 action-button">
                        <i class="bi bi-clock-history me-2"></i> Ver Historial
                    </button>
                </div>

                <div class="col-md-3">
                    <button onclick="window.location.href='usuarios.php'" class="btn btn-outline-primary w-100 py-2 action-button">
                        <i class="bi bi-people me-2"></i> Usuarios
                    </button>
                </div>

                <div class="col-md-3">
                    <button onclick="window.location.href='logout.php'" class="btn btn-outline-dark w-100 py-2 action-button">
                        <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                    </button>
                </div>
            </div>
        </section>

    </div>

    <footer class="footer mt-auto py-3 bg-white border-top">
        <div class="container text-center">
            <span class="text-muted">Sistema de Tostadas Deliciosas &copy; <?= date('Y') ?></span>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
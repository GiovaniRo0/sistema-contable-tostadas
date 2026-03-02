<?php
require_once __DIR__ . '/auth.php';
// Nota: La conexión a DB (Conexion.php) no es necesaria directamente en el menú.

// Función para aplicar la clase 'active' al elemento actual
function getActiveClass($currentPage, $pageName)
{
  return $currentPage === $pageName ? 'active' : '';
}

// Función para aplicar la clase 'active' si la página actual pertenece al módulo
function isModuleActive($currentPage, $modulePages)
{
  return in_array($currentPage, $modulePages) ? 'active' : '';
}

// ----------------------------------------------------
// Definición de páginas por Módulo (para activar el dropdown)
// ----------------------------------------------------
$inventoryPages = ['productos', 'categorias', 'ingredientes', 'control_ingredientes'];
$financialPages = ['gastos', 'ventas'];

$reportesPages = [
    'reportes_general', 
    'reportes_ventas', 
    'reportes_gastos', 
    'reportes_inventario',
    'balance_mensual', // NUEVO
    'balance_semanal', // NUEVO
    'conteo_productos', // NUEVO
    'reportes_productos',
    'reportes_ingredientes'
];

$historialPages = ['historial_ventas', 'historial_gastos', 'historial_movimientos', 'historial_productos', 'historial_ingredientes'];
$adminPages = ['usuarios', 'configuracion'];

// Páginas de acceso directo
$dashboardPage = 'dashboard';
$inicioPage = 'inicio'; 
?>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm sticky-top">
  <div class="container">
    
    <a class="navbar-brand fw-bold" href="pag.php">
      <i class="bi bi-cup-hot-fill me-2 text-warning"></i>
      <span class="text-primary">Tostadas Jela</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        
        <li class="nav-item">
          <a class="nav-link <?= getActiveClass($currentPage, $inicioPage) ?>" href="pag.php">
            <i class="bi bi-house-door me-1"></i> Inicio
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= getActiveClass($currentPage, $dashboardPage) ?>" href="dashboard.php">
            <i class="bi bi-speedometer2 me-1"></i> General 
          </a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isModuleActive($currentPage, $inventoryPages) ?>"
            href="#"
            id="inventoryDropdown"
            role="button"
            data-bs-toggle="dropdown"
            aria-expanded="false">
            <i class="bi bi-box-seam me-1"></i> Inventario
          </a>
          <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="inventoryDropdown">
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'productos') ?>" href="productos.php">
                <i class="bi bi-cup-straw me-2"></i> Productos
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'categorias') ?>" href="categorias.php">
                <i class="bi bi-tags me-2"></i> Categorías
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'ingredientes') ?>" href="ingredientes.php">
                <i class="bi bi-egg-fried me-2"></i> Ingredientes
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'control_ingredientes') ?>" href="control_ingredientes.php">
                <i class="bi bi-list-check me-2"></i> Control de Stock
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isModuleActive($currentPage, $financialPages) ?>"
            href="#"
            id="financialDropdown"
            role="button"
            data-bs-toggle="dropdown"
            aria-expanded="false">
            <i class="bi bi-graph-up me-1"></i> Finanzas
          </a>
          <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="financialDropdown">
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'ventas') ?>" href="ventas.php">
                <i class="bi bi-cart-check me-2"></i> Ventas
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'gastos') ?>" href="gastos.php">
                <i class="bi bi-cash-coin me-2"></i> Gastos
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isModuleActive($currentPage, $reportesPages) ?>"
            href="#"
            id="reportesDropdown"
            role="button"
            data-bs-toggle="dropdown"
            aria-expanded="false">
            <i class="bi bi-bar-chart me-1"></i> Reportes
          </a>
          <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="reportesDropdown">
            
            <li>
              <h6 class="dropdown-header text-info">Análisis de Rentabilidad</h6>
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'balance_mensual') ?>" href="balance_mensual.php">
                <i class="bi bi-calendar-check me-2"></i> Balance Mensual
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'balance_semanal') ?>" href="balance_semanal.php">
                <i class="bi bi-calendar-week me-2"></i> Balance Semanal
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'conteo_productos') ?>" href="conteo_productos.php">
                <i class="bi bi-cart-plus-fill me-2"></i> Conteo Productos (Extras)
              </a>
            </li>

            <li>
              <hr class="dropdown-divider">
            </li>
            <li>
              <h6 class="dropdown-header text-warning">Control de Actividad</h6>
            </li>

            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'reportes_productos') ?>" href="reportes_productos.php">
                <i class="bi bi-box-seam me-2"></i> Reporte de Productos
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'reportes_ingredientes') ?>" href="reportes_ingredientes.php">
                <i class="bi bi-basket me-2"></i> Reporte de Ingredientes
              </a>
            </li>
            
            <li>
              <hr class="dropdown-divider">
            </li>
            <li>
              <a class="dropdown-item" href="pdf_auditoria_general.php" target="_blank">
                <i class="bi bi-file-earmark-pdf me-2"></i> Auditoria General PDF
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isModuleActive($currentPage, $historialPages) ?>"
            href="#"
            id="historialDropdown"
            role="button"
            data-bs-toggle="dropdown"
            aria-expanded="false">
            <i class="bi bi-clock-history me-1"></i> Historial
          </a>
          <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="historialDropdown">
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'historial_productos') ?>" href="historial_productos.php">
                <i class="bi bi-cart-check me-2"></i> Historial de Productos
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'historial_ingredientes') ?>" href="historial_ingredientes.php">
                <i class="bi bi-arrow-left-right me-2"></i> Historial Ingredientes
              </a>
            </li>
          </ul>
        </li>
      </ul>

      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isModuleActive($currentPage, $adminPages) ?>"
            href="#"
            id="adminDropdown"
            role="button"
            data-bs-toggle="dropdown">
            <i class="bi bi-gear me-1"></i> Administración
          </a>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="adminDropdown">
            <li>
              <a class="dropdown-item <?= getActiveClass($currentPage, 'usuarios') ?>" href="usuarios.php">
                <i class="bi bi-people me-2"></i> Usuarios
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li>
              <a class="dropdown-item text-danger fw-bold" href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
              </a>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
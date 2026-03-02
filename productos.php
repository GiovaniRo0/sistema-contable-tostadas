<?php



require_once __DIR__ . '/Conexion.php';
require_once 'auth.php';

function crearProducto($id_categoria, $nombre_producto, $descripcion, $precio_base, $costo_base, $activo)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "INSERT INTO productos (id_categoria, nombre_producto, descripcion, precio_base, costo_base, activo) 
                VALUES (:id_categoria, :nombre_producto, :descripcion, :precio_base, :costo_base, :activo)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_categoria', $id_categoria, PDO::PARAM_INT);
        $stmt->bindValue(':nombre_producto', $nombre_producto, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':precio_base', $precio_base, PDO::PARAM_STR);
        $stmt->bindValue(':costo_base', $costo_base, PDO::PARAM_STR);
        $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $stmt->execute();
        return ['success' => true, 'message' => 'Producto creado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function leerProductos()
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "SELECT p.id_producto, p.id_categoria, c.nombre_categoria, p.nombre_producto, 
                       p.descripcion, p.precio_base, p.costo_base, p.activo, p.fecha_creacion
                FROM productos p
                LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
                ORDER BY p.id_producto";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id_producto'] = isset($row['id_producto']) ? (int)$row['id_producto'] : null;
            $row['id_categoria'] = isset($row['id_categoria']) ? (int)$row['id_categoria'] : null;
            $row['precio_base'] = isset($row['precio_base']) ? (float)$row['precio_base'] : 0;
            $row['costo_base'] = isset($row['costo_base']) ? (float)$row['costo_base'] : 0;
            $row['activo'] = isset($row['activo']) ? (bool)$row['activo'] : false;
        }
        return ['success' => true, 'data' => $rows];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function obtenerCategorias()
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "SELECT id_categoria, nombre_categoria FROM categorias ORDER BY nombre_categoria";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $rows];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// AGREGA ESTE NUEVO BLOQUE PARA MANEJAR LA SOLICITUD DE CATEGORÍAS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['categorias'])) {
    header('Content-Type: application/json; charset=utf-8');
    $result = obtenerCategorias();
    if ($result['success']) {
        echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit;
}

function actualizarProducto($id_producto, $id_categoria, $nombre_producto, $descripcion, $precio_base, $costo_base, $activo)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "UPDATE productos 
                SET id_categoria = :id_categoria, nombre_producto = :nombre_producto, 
                    descripcion = :descripcion, precio_base = :precio_base, 
                    costo_base = :costo_base, activo = :activo
                WHERE id_producto = :id_producto";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_categoria', $id_categoria, PDO::PARAM_INT);
        $stmt->bindValue(':nombre_producto', $nombre_producto, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':precio_base', $precio_base, PDO::PARAM_STR);
        $stmt->bindValue(':costo_base', $costo_base, PDO::PARAM_STR);
        $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $stmt->bindValue(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Producto actualizado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function eliminarProducto($id_producto)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "DELETE FROM productos WHERE id_producto = :id_producto";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Producto eliminado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {
            case 'crear':
                $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
                $nombre_producto = trim($_POST['nombre_producto'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $precio_base = trim($_POST['precio_base'] ?? '');
                $costo_base = trim($_POST['costo_base'] ?? '');
                $activo = isset($_POST['activo']) ? filter_var($_POST['activo'], FILTER_VALIDATE_BOOLEAN) : false;

                if (!$nombre_producto || !$precio_base || !$costo_base) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                    exit;
                }
                
                if (!is_numeric($precio_base) || $precio_base < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Precio base debe ser un número válido']);
                    exit;
                }
                
                if (!is_numeric($costo_base) || $costo_base < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Costo base debe ser un número válido']);
                    exit;
                }
                
                if (strlen($nombre_producto) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre del producto no puede tener más de 100 caracteres']);
                    exit;
                }
                
                $result = crearProducto($id_categoria, $nombre_producto, $descripcion, $precio_base, $costo_base, $activo);
                echo json_encode($result);
                break;

            case 'actualizar':
                $id_producto = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : null;
                $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
                $nombre_producto = trim($_POST['nombre_producto'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $precio_base = trim($_POST['precio_base'] ?? '');
                $costo_base = trim($_POST['costo_base'] ?? '');
                $activo = isset($_POST['activo']) ? filter_var($_POST['activo'], FILTER_VALIDATE_BOOLEAN) : false;

                if (!$id_producto || !$nombre_producto || !$precio_base || !$costo_base) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                    exit;
                }
                
                if (!is_numeric($precio_base) || $precio_base < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Precio base debe ser un número válido']);
                    exit;
                }
                
                if (!is_numeric($costo_base) || $costo_base < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Costo base debe ser un número válido']);
                    exit;
                }
                
                if (strlen($nombre_producto) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre del producto no puede tener más de 100 caracteres']);
                    exit;
                }
                
                $result = actualizarProducto($id_producto, $id_categoria, $nombre_producto, $descripcion, $precio_base, $costo_base, $activo);
                echo json_encode($result);
                break;

            case 'eliminar':
                $id_producto = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : null;
                if (!$id_producto) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Falta id_producto']);
                    exit;
                }
                $result = eliminarProducto($id_producto);
                echo json_encode($result);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $result = leerProductos();
    if ($result['success']) {
        echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit;
}

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-responsive {
            overflow-x: auto;
        }

        .action-buttons {
            white-space: nowrap;
        }
        
        .search-container {
            max-width: 400px;
        }
        
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }
        
        .table-row-count {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .status-badge-active {
            background-color: #198754;
        }
        
        .status-badge-inactive {
            background-color: #6c757d;
        }
        
        .profit-positive {
            color: #198754;
            font-weight: bold;
        }
        
        .profit-negative {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>

<body class="bg-light">
    <?php
    $currentPage = 'productos';
    include 'menu.php';
    ?>
    <div class="container my-4">
        <header class="py-3 mb-4 border-bottom">
            <h1 class="display-5 fw-bold">Gestión de Productos</h1>
        </header>

        <div id="mensaje"></div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Lista de productos</h2>
            <div>
                <button id="btnRefrescar" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-repeat"></i> Refrescar
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearProductoModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Producto
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="search-container">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="searchInput" class="form-control" placeholder="Buscar productos...">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end">
                            <div class="me-3">
                                <select id="filterEstado" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="tablaProductos" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                        <th>Precio Base</th>
                        <th>Costo Base</th>
                        <th>Margen</th>
                        <th>Fecha Creación</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="pagination-container">
            <div class="table-row-count" id="rowCount">Mostrando 0 productos</div>
            <nav>
                <ul class="pagination" id="pagination">
                </ul>
            </nav>
        </div>
    </div>

    <div class="modal fade" id="crearProductoModal" tabindex="-1" aria-labelledby="crearProductoModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="crearProductoModalLabel">Crear Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrear">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_producto" class="form-label">Nombre del Producto</label>
                            <input type="text" class="form-control" id="nombre_producto" name="nombre_producto" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="id_categoria" class="form-label">Categoría</label>
                            <select class="form-select" id="id_categoria" name="id_categoria">
                                <option value="">Sin categoría</option>
                                <!-- Las opciones se cargarán dinámicamente -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="precio_base" class="form-label">Precio Base</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="precio_base" name="precio_base" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="costo_base" class="form-label">Costo Base</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="costo_base" name="costo_base" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="activo" class="form-label">Estado</label>
                            <select class="form-select" id="activo" name="activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editarProductoModal" tabindex="-1" aria-labelledby="editarProductoModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarProductoModalLabel">Editar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formActualizar">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id_producto" name="id_producto">
                        <div class="mb-3">
                            <label for="edit_nombre_producto" class="form-label">Nombre del Producto</label>
                            <input type="text" class="form-control" id="edit_nombre_producto" name="nombre_producto" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_id_categoria" class="form-label">Categoría</label>
                            <select class="form-select" id="edit_id_categoria" name="id_categoria">
                                <option value="">Sin categoría</option>
                                <!-- Las opciones se cargarán dinámicamente -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_precio_base" class="form-label">Precio Base</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="edit_precio_base" name="precio_base" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_costo_base" class="form-label">Costo Base</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="edit_costo_base" name="costo_base" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_activo" class="form-label">Estado</label>
                            <select class="form-select" id="edit_activo" name="activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <script>
        const apiUrl = window.location.href;
        let productosData = [];
        let filteredData = [];
        let categoriasData = [];
        let currentPage = 1;
        const rowsPerPage = 5;

        function mostrarMensaje(text, tipo = 'success') {
            const div = document.getElementById('mensaje');
            const alertType = tipo === 'success' ? 'alert-success' : 'alert-danger';
            div.innerHTML = `<div class="alert ${alertType} alert-dismissible fade show" role="alert">
                        ${text}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>`;
            setTimeout(() => div.innerHTML = '', 3500);
        }

        function formatearFecha(fecha) {
            if (!fecha) return '';
            const date = new Date(fecha);
            return date.toLocaleString('es-ES');
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function calcularMargen(precio, costo) {
            if (costo === 0) return 0;
            return ((precio - costo) / costo * 100).toFixed(2);
        }

        function filtrarProductos() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterEstado = document.getElementById('filterEstado').value;
            
            filteredData = productosData.filter(producto => {
                const matchesSearch = 
                    producto.nombre_producto.toLowerCase().includes(searchTerm) || 
                    (producto.descripcion && producto.descripcion.toLowerCase().includes(searchTerm)) ||
                    (producto.nombre_categoria && producto.nombre_categoria.toLowerCase().includes(searchTerm));
                
                const matchesEstado = filterEstado === '' || 
                    (filterEstado === '1' && producto.activo) || 
                    (filterEstado === '0' && !producto.activo);
                
                return matchesSearch && matchesEstado;
            });
            
            currentPage = 1; 
            renderTable();
            renderPagination();
        }

        function renderTable() {
            const tbody = document.querySelector('#tablaProductos tbody');
            tbody.innerHTML = '';
            
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = Math.min(startIndex + rowsPerPage, filteredData.length);
            const currentPageData = filteredData.slice(startIndex, endIndex);
            
            if (currentPageData.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4">No se encontraron productos</td></tr>`;
                return;
            }
            
            currentPageData.forEach(p => {
                const margen = calcularMargen(p.precio_base, p.costo_base);
                const margenClass = margen >= 0 ? 'profit-positive' : 'profit-negative';
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(p.nombre_producto)}</td>
                    <td>${escapeHtml(p.nombre_categoria || 'Sin categoría')}</td>
                    <td>${escapeHtml(p.descripcion || '')}</td>
                    <td>$${parseFloat(p.precio_base).toFixed(2)}</td>
                    <td>$${parseFloat(p.costo_base).toFixed(2)}</td>
                    <td class="${margenClass}">${margen}%</td>
                    <td>${formatearFecha(p.fecha_creacion)}</td>
                    <td><span class="badge ${p.activo ? 'status-badge-active' : 'status-badge-inactive'}">${p.activo ? 'Activo' : 'Inactivo'}</span></td>
                    <td class="action-buttons">
                      <button data-id="${p.id_producto}" class="btn btn-sm btn-outline-primary btnEditar" data-bs-toggle="modal" data-bs-target="#editarProductoModal">
                        <i class="bi bi-pencil"></i> Editar
                      </button>
                      <button data-id="${p.id_producto}" class="btn btn-sm btn-outline-danger btnEliminar">
                        <i class="bi bi-trash"></i> Eliminar
                      </button>
                    </td>`;
                tbody.appendChild(tr);
            });
            
            document.getElementById('rowCount').textContent = 
                `Mostrando ${startIndex + 1}-${endIndex} de ${filteredData.length} productos`;
        }

        function renderPagination() {
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            
            const totalPages = Math.ceil(filteredData.length / rowsPerPage);
            
            if (totalPages <= 1) return;
            
            // Botón Anterior
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Anterior</a>`;
            pagination.appendChild(prevLi);
            
            // Configuración de páginas visibles
            const maxVisiblePages = 7;
            let startPage, endPage;
            
            if (totalPages <= maxVisiblePages) {
                startPage = 1;
                endPage = totalPages;
            } else {
                const maxPagesBeforeCurrent = Math.floor(maxVisiblePages / 2);
                const maxPagesAfterCurrent = Math.ceil(maxVisiblePages / 2) - 1;
                
                if (currentPage <= maxPagesBeforeCurrent) {
                    startPage = 1;
                    endPage = maxVisiblePages;
                } else if (currentPage + maxPagesAfterCurrent >= totalPages) {
                    startPage = totalPages - maxVisiblePages + 1;
                    endPage = totalPages;
                } else {
                    startPage = currentPage - maxPagesBeforeCurrent;
                    endPage = currentPage + maxPagesAfterCurrent;
                }
            }
            
            // Primera página + puntos suspensivos si es necesario
            if (startPage > 1) {
                const firstLi = document.createElement('li');
                firstLi.className = 'page-item';
                firstLi.innerHTML = `<a class="page-link" href="#" data-page="1">1</a>`;
                pagination.appendChild(firstLi);
                
                if (startPage > 2) {
                    const ellipsisLi = document.createElement('li');
                    ellipsisLi.className = 'page-item disabled';
                    ellipsisLi.innerHTML = `<span class="page-link">...</span>`;
                    pagination.appendChild(ellipsisLi);
                }
            }
            
            // Páginas numeradas
            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                pagination.appendChild(li);
            }
            
            // Última página + puntos suspensivos si es necesario
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const ellipsisLi = document.createElement('li');
                    ellipsisLi.className = 'page-item disabled';
                    ellipsisLi.innerHTML = `<span class="page-link">...</span>`;
                    pagination.appendChild(ellipsisLi);
                }
                
                const lastLi = document.createElement('li');
                lastLi.className = 'page-item';
                lastLi.innerHTML = `<a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a>`;
                pagination.appendChild(lastLi);
            }
            
            // Botón Siguiente
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">Siguiente</a>`;
            pagination.appendChild(nextLi);
        }

        function cargarCategorias() {
            fetch('productos.php?categorias=1')
                .then(response => response.json())
                .then(data => {
                    categoriasData = data;
                    
                    // Actualizar select de crear
                    const selectCrear = document.getElementById('id_categoria');
                    selectCrear.innerHTML = '<option value="">Sin categoría</option>';
                    data.forEach(categoria => {
                        const option = document.createElement('option');
                        option.value = categoria.id_categoria;
                        option.textContent = categoria.nombre_categoria;
                        selectCrear.appendChild(option);
                    });
                    
                    // Actualizar select de editar
                    const selectEditar = document.getElementById('edit_id_categoria');
                    selectEditar.innerHTML = '<option value="">Sin categoría</option>';
                    data.forEach(categoria => {
                        const option = document.createElement('option');
                        option.value = categoria.id_categoria;
                        option.textContent = categoria.nombre_categoria;
                        selectEditar.appendChild(option);
                    });
                })
                .catch(err => console.error('Error al cargar categorías:', err));
        }

        async function fetchProductos() {
            try {
                const res = await fetch(apiUrl + '?api=1');
                if (!res.ok) throw new Error('Error al obtener productos: ' + res.status);
                const data = await res.json();
                productosData = data;
                filteredData = [...productosData];
                renderTable();
                renderPagination();
            } catch (err) {
                mostrarMensaje(err.message, 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('btnRefrescar').addEventListener('click', fetchProductos);
            
            document.getElementById('searchInput').addEventListener('input', filtrarProductos);
            document.getElementById('filterEstado').addEventListener('change', filtrarProductos);
            
            document.getElementById('pagination').addEventListener('click', function(e) {
                e.preventDefault();
                if (e.target.tagName === 'A') {
                    const page = parseInt(e.target.dataset.page);
                    if (page && page !== currentPage) {
                        currentPage = page;
                        renderTable();
                        renderPagination();
                    }
                }
            });

            document.getElementById('formCrear').addEventListener('submit', async (e) => {
                e.preventDefault();
                const f = e.target;
                const body = new URLSearchParams();
                body.append('accion', 'crear');
                body.append('id_categoria', f.id_categoria.value);
                body.append('nombre_producto', f.nombre_producto.value);
                body.append('descripcion', f.descripcion.value);
                body.append('precio_base', f.precio_base.value);
                body.append('costo_base', f.costo_base.value);
                body.append('activo', f.activo.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Producto creado correctamente');
                        f.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('crearProductoModal'));
                        modal.hide();
                        fetchProductos();
                    } else {
                        mostrarMensaje(data.message || 'Error al crear producto', 'error');
                    }
                } catch (err) {
                    mostrarMensaje(err.message, 'error');
                }
            });

            document.getElementById('formActualizar').addEventListener('submit', async (e) => {
                e.preventDefault();
                const f = e.target;
                const body = new URLSearchParams();
                body.append('accion', 'actualizar');
                body.append('id_producto', f.id_producto.value);
                body.append('id_categoria', f.id_categoria.value);
                body.append('nombre_producto', f.nombre_producto.value);
                body.append('descripcion', f.descripcion.value);
                body.append('precio_base', f.precio_base.value);
                body.append('costo_base', f.costo_base.value);
                body.append('activo', f.activo.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Producto actualizado correctamente');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editarProductoModal'));
                        modal.hide();
                        fetchProductos();
                    } else {
                        mostrarMensaje(data.message || 'Error al actualizar producto', 'error');
                    }
                } catch (err) {
                    mostrarMensaje(err.message, 'error');
                }
            });

            document.querySelector('#tablaProductos tbody').addEventListener('click', async (e) => {
                if (e.target.classList.contains('btnEliminar') || e.target.closest('.btnEliminar')) {
                    const btn = e.target.classList.contains('btnEliminar') ? e.target : e.target.closest('.btnEliminar');
                    const id = btn.dataset.id;
                    
                    const producto = productosData.find(p => p.id_producto == id);
                    const nombre = producto ? producto.nombre_producto : 'este producto';
                    
                    if (!confirm(`¿Está seguro de que desea eliminar "${nombre}"?`)) return;
                    
                    const body = new URLSearchParams();
                    body.append('accion', 'eliminar');
                    body.append('id_producto', id);
                    
                    try {
                        const res = await fetch(apiUrl, {
                            method: 'POST',
                            body
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            mostrarMensaje(data.message || 'Producto eliminado correctamente');
                            fetchProductos();
                        } else {
                            mostrarMensaje(data.message || 'Error al eliminar producto', 'error');
                        }
                    } catch (err) {
                        mostrarMensaje(err.message, 'error');
                    }
                }
                
                if (e.target.classList.contains('btnEditar') || e.target.closest('.btnEditar')) {
                    const btn = e.target.classList.contains('btnEditar') ? e.target : e.target.closest('.btnEditar');
                    const id = btn.dataset.id;
                    const producto = productosData.find(p => p.id_producto == id);
                    
                    if (producto) {
                        document.getElementById('edit_id_producto').value = producto.id_producto;
                        document.getElementById('edit_nombre_producto').value = producto.nombre_producto || '';
                        document.getElementById('edit_id_categoria').value = producto.id_categoria || '';
                        document.getElementById('edit_descripcion').value = producto.descripcion || '';
                        document.getElementById('edit_precio_base').value = producto.precio_base || '';
                        document.getElementById('edit_costo_base').value = producto.costo_base || '';
                        document.getElementById('edit_activo').value = producto.activo ? '1' : '0';
                    }
                }
            });

            fetchProductos();
            cargarCategorias();
        });
    </script>
</body>
</html>
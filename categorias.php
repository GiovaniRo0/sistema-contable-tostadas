<?php
require_once 'auth.php';
require_once __DIR__ . '/Conexion.php';

function crearCategoria($nombre_categoria, $descripcion, $activo, $acepta_extras)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "INSERT INTO categorias (nombre_categoria, descripcion, activo, acepta_extras) 
                VALUES (:nombre_categoria, :descripcion, :activo, :acepta_extras)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre_categoria', $nombre_categoria, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $stmt->bindValue(':acepta_extras', $acepta_extras, PDO::PARAM_BOOL);
        $stmt->execute();
        return ['success' => true, 'message' => 'Categoría creada correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function leerCategorias()
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "SELECT id_categoria, nombre_categoria, descripcion, activo, acepta_extras
                FROM categorias
                ORDER BY nombre_categoria";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id_categoria'] = isset($row['id_categoria']) ? (int)$row['id_categoria'] : null;
            $row['activo'] = isset($row['activo']) ? (bool)$row['activo'] : false;
            $row['acepta_extras'] = isset($row['acepta_extras']) ? (bool)$row['acepta_extras'] : false;
        }
        return ['success' => true, 'data' => $rows];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function actualizarCategoria($id_categoria, $nombre_categoria, $descripcion, $activo, $acepta_extras)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "UPDATE categorias 
                SET nombre_categoria = :nombre_categoria, descripcion = :descripcion, 
                    activo = :activo, acepta_extras = :acepta_extras
                WHERE id_categoria = :id_categoria";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre_categoria', $nombre_categoria, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $stmt->bindValue(':acepta_extras', $acepta_extras, PDO::PARAM_BOOL);
        $stmt->bindValue(':id_categoria', $id_categoria, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Categoría actualizada correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function eliminarCategoria($id_categoria)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "DELETE FROM categorias WHERE id_categoria = :id_categoria";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_categoria', $id_categoria, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Categoría eliminada correctamente'];
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
                $nombre_categoria = trim($_POST['nombre_categoria'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $activo = isset($_POST['activo']) ? filter_var($_POST['activo'], FILTER_VALIDATE_BOOLEAN) : false;
                $acepta_extras = isset($_POST['acepta_extras']) ? filter_var($_POST['acepta_extras'], FILTER_VALIDATE_BOOLEAN) : false;

                if (!$nombre_categoria) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre de categoría es requerido']);
                    exit;
                }
                
                if (strlen($nombre_categoria) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre de categoría no puede tener más de 100 caracteres']);
                    exit;
                }
                
                $result = crearCategoria($nombre_categoria, $descripcion, $activo, $acepta_extras);
                echo json_encode($result);
                break;

            case 'actualizar':
                $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
                $nombre_categoria = trim($_POST['nombre_categoria'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $activo = isset($_POST['activo']) ? filter_var($_POST['activo'], FILTER_VALIDATE_BOOLEAN) : false;
                $acepta_extras = isset($_POST['acepta_extras']) ? filter_var($_POST['acepta_extras'], FILTER_VALIDATE_BOOLEAN) : false;

                if (!$id_categoria || !$nombre_categoria) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                    exit;
                }
                
                if (strlen($nombre_categoria) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre de categoría no puede tener más de 100 caracteres']);
                    exit;
                }
                
                $result = actualizarCategoria($id_categoria, $nombre_categoria, $descripcion, $activo, $acepta_extras);
                echo json_encode($result);
                break;

            case 'eliminar':
                $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
                if (!$id_categoria) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Falta id_categoria']);
                    exit;
                }
                $result = eliminarCategoria($id_categoria);
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
    $result = leerCategorias();
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
    <title>Categorías</title>
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
        
        .badge-extras {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-no-extras {
            background-color: #6c757d;
        }
        
        .description-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body class="bg-light">
    <?php
    $currentPage = 'categorias';
    include 'menu.php';
    ?>
    <div class="container my-4">
        <header class="py-3 mb-4 border-bottom">
            <h1 class="display-5 fw-bold">Gestión de Categorías</h1>
        </header>

        <div id="mensaje"></div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Lista de categorías</h2>
            <div>
                <button id="btnRefrescar" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-repeat"></i> Refrescar
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearCategoriaModal">
                    <i class="bi bi-plus-circle"></i> Nueva Categoría
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
                                <input type="text" id="searchInput" class="form-control" placeholder="Buscar categorías...">
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
                            <div>
                                <select id="filterExtras" class="form-select">
                                    <option value="">Todos los tipos</option>
                                    <option value="1">Acepta extras</option>
                                    <option value="0">No acepta extras</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="tablaCategorias" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Acepta Extras</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="pagination-container">
            <div class="table-row-count" id="rowCount">Mostrando 0 categorías</div>
            <nav>
                <ul class="pagination" id="pagination">
                </ul>
            </nav>
        </div>
    </div>

    <div class="modal fade" id="crearCategoriaModal" tabindex="-1" aria-labelledby="crearCategoriaModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="crearCategoriaModalLabel">Crear Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrear">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_categoria" class="form-label">Nombre de Categoría</label>
                            <input type="text" class="form-control" id="nombre_categoria" name="nombre_categoria" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="activo" class="form-label">Estado</label>
                                    <select class="form-select" id="activo" name="activo">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="acepta_extras" class="form-label">Acepta Extras</label>
                                    <select class="form-select" id="acepta_extras" name="acepta_extras">
                                        <option value="1">Sí</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Categoría</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editarCategoriaModal" tabindex="-1" aria-labelledby="editarCategoriaModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarCategoriaModalLabel">Editar Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formActualizar">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id_categoria" name="id_categoria">
                        <div class="mb-3">
                            <label for="edit_nombre_categoria" class="form-label">Nombre de Categoría</label>
                            <input type="text" class="form-control" id="edit_nombre_categoria" name="nombre_categoria" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_activo" class="form-label">Estado</label>
                                    <select class="form-select" id="edit_activo" name="activo">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_acepta_extras" class="form-label">Acepta Extras</label>
                                    <select class="form-select" id="edit_acepta_extras" name="acepta_extras">
                                        <option value="1">Sí</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Categoría</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <script>
        const apiUrl = window.location.href;
        let categoriasData = [];
        let filteredData = [];
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

        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function filtrarCategorias() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterEstado = document.getElementById('filterEstado').value;
            const filterExtras = document.getElementById('filterExtras').value;
            
            filteredData = categoriasData.filter(categoria => {
                const matchesSearch = 
                    categoria.nombre_categoria.toLowerCase().includes(searchTerm) || 
                    (categoria.descripcion && categoria.descripcion.toLowerCase().includes(searchTerm));
                
                const matchesEstado = filterEstado === '' || 
                    (filterEstado === '1' && categoria.activo) || 
                    (filterEstado === '0' && !categoria.activo);
                
                const matchesExtras = filterExtras === '' || 
                    (filterExtras === '1' && categoria.acepta_extras) || 
                    (filterExtras === '0' && !categoria.acepta_extras);
                
                return matchesSearch && matchesEstado && matchesExtras;
            });
            
            currentPage = 1; 
            renderTable();
            renderPagination();
        }

        function renderTable() {
            const tbody = document.querySelector('#tablaCategorias tbody');
            tbody.innerHTML = '';
            
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = Math.min(startIndex + rowsPerPage, filteredData.length);
            const currentPageData = filteredData.slice(startIndex, endIndex);
            
            if (currentPageData.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4">No se encontraron categorías</td></tr>`;
                return;
            }
            
            currentPageData.forEach(c => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(c.nombre_categoria)}</td>
                    <td class="description-cell" title="${escapeHtml(c.descripcion || '')}">${escapeHtml(c.descripcion || '')}</td>
                    <td><span class="badge ${c.activo ? 'status-badge-active' : 'status-badge-inactive'}">${c.activo ? 'Activo' : 'Inactivo'}</span></td>
                    <td><span class="badge ${c.acepta_extras ? 'badge-extras' : 'badge-no-extras'}">${c.acepta_extras ? 'Sí' : 'No'}</span></td>
                    <td class="action-buttons">
                      <button data-id="${c.id_categoria}" class="btn btn-sm btn-outline-primary btnEditar" data-bs-toggle="modal" data-bs-target="#editarCategoriaModal">
                        <i class="bi bi-pencil"></i> Editar
                      </button>
                      <button data-id="${c.id_categoria}" class="btn btn-sm btn-outline-danger btnEliminar">
                        <i class="bi bi-trash"></i> Eliminar
                      </button>
                    </td>`;
                tbody.appendChild(tr);
            });
            
            document.getElementById('rowCount').textContent = 
                `Mostrando ${startIndex + 1}-${endIndex} de ${filteredData.length} categorías`;
        }

        function renderPagination() {
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            
            const totalPages = Math.ceil(filteredData.length / rowsPerPage);
            
            if (totalPages <= 1) return;
            
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Anterior</a>`;
            pagination.appendChild(prevLi);
            
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
            
            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                pagination.appendChild(li);
            }
            
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
            
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">Siguiente</a>`;
            pagination.appendChild(nextLi);
        }

        async function fetchCategorias() {
            try {
                const res = await fetch(apiUrl + '?api=1');
                if (!res.ok) throw new Error('Error al obtener categorías: ' + res.status);
                const data = await res.json();
                categoriasData = data;
                filteredData = [...categoriasData];
                renderTable();
                renderPagination();
            } catch (err) {
                mostrarMensaje(err.message, 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('btnRefrescar').addEventListener('click', fetchCategorias);
            
            document.getElementById('searchInput').addEventListener('input', filtrarCategorias);
            document.getElementById('filterEstado').addEventListener('change', filtrarCategorias);
            document.getElementById('filterExtras').addEventListener('change', filtrarCategorias);
            
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
                body.append('nombre_categoria', f.nombre_categoria.value);
                body.append('descripcion', f.descripcion.value);
                body.append('activo', f.activo.value);
                body.append('acepta_extras', f.acepta_extras.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Categoría creada correctamente');
                        f.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('crearCategoriaModal'));
                        modal.hide();
                        fetchCategorias();
                    } else {
                        mostrarMensaje(data.message || 'Error al crear categoría', 'error');
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
                body.append('id_categoria', f.id_categoria.value);
                body.append('nombre_categoria', f.nombre_categoria.value);
                body.append('descripcion', f.descripcion.value);
                body.append('activo', f.activo.value);
                body.append('acepta_extras', f.acepta_extras.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Categoría actualizada correctamente');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editarCategoriaModal'));
                        modal.hide();
                        fetchCategorias();
                    } else {
                        mostrarMensaje(data.message || 'Error al actualizar categoría', 'error');
                    }
                } catch (err) {
                    mostrarMensaje(err.message, 'error');
                }
            });

            document.querySelector('#tablaCategorias tbody').addEventListener('click', async (e) => {
                if (e.target.classList.contains('btnEliminar') || e.target.closest('.btnEliminar')) {
                    const btn = e.target.classList.contains('btnEliminar') ? e.target : e.target.closest('.btnEliminar');
                    const id = btn.dataset.id;
                    
                    const categoria = categoriasData.find(c => c.id_categoria == id);
                    const nombre = categoria ? categoria.nombre_categoria : 'esta categoría';
                    
                    if (!confirm(`¿Está seguro de que desea eliminar "${nombre}"?`)) return;
                    
                    const body = new URLSearchParams();
                    body.append('accion', 'eliminar');
                    body.append('id_categoria', id);
                    
                    try {
                        const res = await fetch(apiUrl, {
                            method: 'POST',
                            body
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            mostrarMensaje(data.message || 'Categoría eliminada correctamente');
                            fetchCategorias();
                        } else {
                            mostrarMensaje(data.message || 'Error al eliminar categoría', 'error');
                        }
                    } catch (err) {
                        mostrarMensaje(err.message, 'error');
                    }
                }
                
                if (e.target.classList.contains('btnEditar') || e.target.closest('.btnEditar')) {
                    const btn = e.target.classList.contains('btnEditar') ? e.target : e.target.closest('.btnEditar');
                    const id = btn.dataset.id;
                    const categoria = categoriasData.find(c => c.id_categoria == id);
                    
                    if (categoria) {
                        document.getElementById('edit_id_categoria').value = categoria.id_categoria;
                        document.getElementById('edit_nombre_categoria').value = categoria.nombre_categoria || '';
                        document.getElementById('edit_descripcion').value = categoria.descripcion || '';
                        document.getElementById('edit_activo').value = categoria.activo ? '1' : '0';
                        document.getElementById('edit_acepta_extras').value = categoria.acepta_extras ? '1' : '0';
                    }
                }
            });

            fetchCategorias();
        });
    </script>
</body>
</html>
<?php



require_once __DIR__ . '/Conexion.php';
require_once 'auth.php';

function crearUsuario($nombre, $email, $password, $rol)
{
    try {
        if (!in_array($rol, ['empleado', 'administrador'])) {
            return ['success' => false, 'message' => 'Rol no válido. Debe ser "empleado" o "administrador"'];
        }

        $conn = Conexion::ConexionBD();
        $sql = "INSERT INTO usuarios (nombre, email, password, rol) 
                VALUES (:nombre, :email, :password, :rol)";
        $stmt = $conn->prepare($sql);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':password', $hash, PDO::PARAM_STR);
        $stmt->bindValue(':rol', $rol, PDO::PARAM_STR);
        $stmt->execute();
        return ['success' => true, 'message' => 'Usuario creado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function leerUsuarios()
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "SELECT id_usuario, nombre, email, rol, fecha_creacion, activo
                FROM usuarios
                ORDER BY id_usuario";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id_usuario'] = isset($row['id_usuario']) ? (int)$row['id_usuario'] : null;
            $row['activo'] = isset($row['activo']) ? (bool)$row['activo'] : false;
        }
        return ['success' => true, 'data' => $rows];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function actualizarUsuario($id_usuario, $nombre, $email, $rol, $activo)
{
    try {
        if (!in_array($rol, ['empleado', 'administrador'])) {
            return ['success' => false, 'message' => 'Rol no válido. Debe ser "empleado" o "administrador"'];
        }

        $conn = Conexion::ConexionBD();
        $sql = "UPDATE usuarios 
                SET nombre = :nombre, email = :email, rol = :rol, activo = :activo
                WHERE id_usuario = :id_usuario";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':rol', $rol, PDO::PARAM_STR);
        $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Usuario actualizado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function eliminarUsuario($id_usuario)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "DELETE FROM usuarios WHERE id_usuario = :id_usuario";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Usuario eliminado correctamente'];
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
                $nombre = trim($_POST['nombre'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $rol = trim($_POST['rol'] ?? '');

                if (!$nombre || !$email || !$password || !$rol) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                    exit;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Email inválido']);
                    exit;
                }
                if (strlen($nombre) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre no puede tener más de 100 caracteres']);
                    exit;
                }
                if (strlen($email) > 150) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El email no puede tener más de 150 caracteres']);
                    exit;
                }
                $result = crearUsuario($nombre, $email, $password, $rol);
                echo json_encode($result);
                break;

            case 'actualizar':
                $id_usuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : null;
                $nombre = trim($_POST['nombre'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $rol = trim($_POST['rol'] ?? '');
                $activo = isset($_POST['activo']) ? filter_var($_POST['activo'], FILTER_VALIDATE_BOOLEAN) : false;

                if (!$id_usuario || !$nombre || !$email || !$rol) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                    exit;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Email inválido']);
                    exit;
                }
                if (strlen($nombre) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre no puede tener más de 100 caracteres']);
                    exit;
                }
                if (strlen($email) > 150) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El email no puede tener más de 150 caracteres']);
                    exit;
                }
                $result = actualizarUsuario($id_usuario, $nombre, $email, $rol, $activo);
                echo json_encode($result);
                break;

            case 'eliminar':
                $id_usuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : null;
                if (!$id_usuario) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Falta id_usuario']);
                    exit;
                }
                $result = eliminarUsuario($id_usuario);
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
    $result = leerUsuarios();
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
    <title>Usuarios</title>
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
        
        .badge-empleado {
            background-color: #6c757d;
        }
        
        .badge-administrador {
            background-color: #0d6efd;
        }
        
        .status-badge-active {
            background-color: #198754;
        }
        
        .status-badge-inactive {
            background-color: #6c757d;
        }
    </style>
</head>

<body class="bg-light">
    <?php
    $currentPage = 'usuarios';
    include 'menu.php';
    ?>
    <div class="container my-4">
        <header class="py-3 mb-4 border-bottom">
            <h1 class="display-5 fw-bold">Gestión de Usuarios</h1>
        </header>

        <div id="mensaje"></div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Lista de usuarios</h2>
            <div>
                <button id="btnRefrescar" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-repeat"></i> Refrescar
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearUsuarioModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Usuario
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
                                <input type="text" id="searchInput" class="form-control" placeholder="Buscar usuarios...">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end">
                            <div class="me-3">
                                <select id="filterRol" class="form-select">
                                    <option value="">Todos los roles</option>
                                    <option value="empleado">Empleado</option>
                                    <option value="administrador">Administrador</option>
                                </select>
                            </div>
                            <div>
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
            <table id="tablaUsuarios" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Fecha Creación</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="pagination-container">
            <div class="table-row-count" id="rowCount">Mostrando 0 usuarios</div>
            <nav>
                <ul class="pagination" id="pagination">
                </ul>
            </nav>
        </div>
    </div>

    <div class="modal fade" id="crearUsuarioModal" tabindex="-1" aria-labelledby="crearUsuarioModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="crearUsuarioModalLabel">Crear Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrear">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="empleado">Empleado</option>
                                <option value="administrador">Administrador</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarUsuarioModalLabel">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formActualizar">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id_usuario" name="id_usuario">
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label for="edit_rol" class="form-label">Rol</label>
                            <select class="form-select" id="edit_rol" name="rol" required>
                                <option value="empleado">Empleado</option>
                                <option value="administrador">Administrador</option>
                            </select>
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
                        <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <script>
        const apiUrl = window.location.href;
        let usuariosData = [];
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

        function filtrarUsuarios() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterRol = document.getElementById('filterRol').value;
            const filterEstado = document.getElementById('filterEstado').value;
            
            filteredData = usuariosData.filter(usuario => {
                const matchesSearch = 
                    usuario.nombre.toLowerCase().includes(searchTerm) || 
                    usuario.email.toLowerCase().includes(searchTerm);
                
                const matchesRol = filterRol === '' || usuario.rol === filterRol;
                
                const matchesEstado = filterEstado === '' || 
                    (filterEstado === '1' && usuario.activo) || 
                    (filterEstado === '0' && !usuario.activo);
                
                return matchesSearch && matchesRol && matchesEstado;
            });
            
            currentPage = 1; 
            renderTable();
            renderPagination();
        }

        function renderTable() {
            const tbody = document.querySelector('#tablaUsuarios tbody');
            tbody.innerHTML = '';
            
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = Math.min(startIndex + rowsPerPage, filteredData.length);
            const currentPageData = filteredData.slice(startIndex, endIndex);
            
            if (currentPageData.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4">No se encontraron usuarios</td></tr>`;
                return;
            }
            
            currentPageData.forEach(u => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(u.nombre)}</td>
                    <td>${escapeHtml(u.email)}</td>
                    <td><span class="badge ${u.rol === 'administrador' ? 'badge-administrador' : 'badge-empleado'}">${u.rol}</span></td>
                    <td>${formatearFecha(u.fecha_creacion)}</td>
                    <td><span class="badge ${u.activo ? 'status-badge-active' : 'status-badge-inactive'}">${u.activo ? 'Activo' : 'Inactivo'}</span></td>
                    <td class="action-buttons">
                      <button data-id="${u.id_usuario}" class="btn btn-sm btn-outline-primary btnEditar" data-bs-toggle="modal" data-bs-target="#editarUsuarioModal">
                        <i class="bi bi-pencil"></i> Editar
                      </button>
                      <button data-id="${u.id_usuario}" class="btn btn-sm btn-outline-danger btnEliminar">
                        <i class="bi bi-trash"></i> Eliminar
                      </button>
                    </td>`;
                tbody.appendChild(tr);
            });
            
            document.getElementById('rowCount').textContent = 
                `Mostrando ${startIndex + 1}-${endIndex} de ${filteredData.length} usuarios`;
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
    const maxVisiblePages = 7; // Máximo de páginas visibles
    let startPage, endPage;
    
    if (totalPages <= maxVisiblePages) {
        // Si hay menos páginas que el máximo, mostrar todas
        startPage = 1;
        endPage = totalPages;
    } else {
        // Calcular qué páginas mostrar
        const maxPagesBeforeCurrent = Math.floor(maxVisiblePages / 2);
        const maxPagesAfterCurrent = Math.ceil(maxVisiblePages / 2) - 1;
        
        if (currentPage <= maxPagesBeforeCurrent) {
            // Cerca del inicio
            startPage = 1;
            endPage = maxVisiblePages;
        } else if (currentPage + maxPagesAfterCurrent >= totalPages) {
            // Cerca del final
            startPage = totalPages - maxVisiblePages + 1;
            endPage = totalPages;
        } else {
            // En medio
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

        async function fetchUsuarios() {
            try {
                const res = await fetch(apiUrl + '?api=1');
                if (!res.ok) throw new Error('Error al obtener usuarios: ' + res.status);
                const data = await res.json();
                usuariosData = data;
                filteredData = [...usuariosData];
                renderTable();
                renderPagination();
            } catch (err) {
                mostrarMensaje(err.message, 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('btnRefrescar').addEventListener('click', fetchUsuarios);
            
            document.getElementById('searchInput').addEventListener('input', filtrarUsuarios);
            document.getElementById('filterRol').addEventListener('change', filtrarUsuarios);
            document.getElementById('filterEstado').addEventListener('change', filtrarUsuarios);
            
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
                body.append('nombre', f.nombre.value);
                body.append('email', f.email.value);
                body.append('password', f.password.value);
                body.append('rol', f.rol.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Usuario creado correctamente');
                        f.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('crearUsuarioModal'));
                        modal.hide();
                        fetchUsuarios();
                    } else {
                        mostrarMensaje(data.message || 'Error al crear usuario', 'error');
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
                body.append('id_usuario', f.id_usuario.value);
                body.append('nombre', f.nombre.value);
                body.append('email', f.email.value);
                body.append('rol', f.rol.value);
                body.append('activo', f.activo.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Usuario actualizado correctamente');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editarUsuarioModal'));
                        modal.hide();
                        fetchUsuarios();
                    } else {
                        mostrarMensaje(data.message || 'Error al actualizar usuario', 'error');
                    }
                } catch (err) {
                    mostrarMensaje(err.message, 'error');
                }
            });

            document.querySelector('#tablaUsuarios tbody').addEventListener('click', async (e) => {
                if (e.target.classList.contains('btnEliminar') || e.target.closest('.btnEliminar')) {
                    const btn = e.target.classList.contains('btnEliminar') ? e.target : e.target.closest('.btnEliminar');
                    const id = btn.dataset.id;
                    
                   
                    const usuario = usuariosData.find(u => u.id_usuario == id);
                    const nombreUsuario = usuario ? usuario.nombre : '';

                    if (!confirm(`¿Está seguro de que desea eliminar al usuario "${nombreUsuario}"?`)) return;

                    const body = new URLSearchParams();
                    body.append('accion', 'eliminar');
                    body.append('id_usuario', id);
                    try {
                        const res = await fetch(apiUrl, {
                            method: 'POST',
                            body
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            mostrarMensaje(data.message || 'Usuario eliminado correctamente');
                            fetchUsuarios();
                        } else mostrarMensaje(data.message || 'Error al eliminar usuario', 'error');
                    } catch (err) {
                        mostrarMensaje(err.message, 'error');
                    }
                }

                if (e.target.classList.contains('btnEditar') || e.target.closest('.btnEditar')) {
                    const btn = e.target.classList.contains('btnEditar') ? e.target : e.target.closest('.btnEditar');
                    const id = btn.dataset.id;

                    const usuario = usuariosData.find(u => u.id_usuario == id);
                    if (usuario) {
                        document.querySelector('#formActualizar #edit_id_usuario').value = usuario.id_usuario;
                        document.querySelector('#formActualizar #edit_nombre').value = usuario.nombre;
                        document.querySelector('#formActualizar #edit_email').value = usuario.email;
                        document.querySelector('#formActualizar #edit_rol').value = usuario.rol;
                        document.querySelector('#formActualizar #edit_activo').value = usuario.activo ? "1" : "0";
                    }
                }
            });

            fetchUsuarios();
        });
    </script>
</body>

</html>
<?php

require_once __DIR__ . '/Conexion.php';
require_once 'auth.php';

function crearGasto($id_usuario, $tipo_egreso, $monto, $descripcion, $comprobante)
{
    try {
        $conexion = new Conexion();
        $conn = $conexion->ConexionBD();
        $sql = "INSERT INTO egresos (id_usuario, tipo_egreso, monto, descripcion, comprobante) 
                VALUES (:id_usuario, :tipo_egreso, :monto, :descripcion, :comprobante)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_egreso', $tipo_egreso, PDO::PARAM_STR);
        $stmt->bindValue(':monto', $monto);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':comprobante', $comprobante, PDO::PARAM_STR);
        $stmt->execute();
        return ['success' => true, 'message' => 'Gasto registrado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function leerGastos()
{
    try {
        $conexion = new Conexion();
        $conn = $conexion->ConexionBD();
        $sql = "SELECT e.id_egreso, e.id_usuario, u.nombre as nombre_usuario, e.tipo_egreso, 
                       e.monto, e.descripcion, e.fecha_egreso, e.comprobante
                FROM egresos e
                LEFT JOIN usuarios u ON e.id_usuario = u.id_usuario
                ORDER BY e.fecha_egreso DESC, e.id_egreso DESC";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id_egreso'] = isset($row['id_egreso']) ? (int)$row['id_egreso'] : null;
            $row['id_usuario'] = isset($row['id_usuario']) ? (int)$row['id_usuario'] : null;
            $row['monto'] = isset($row['monto']) ? (float)$row['monto'] : 0.00;
        }
        return ['success' => true, 'data' => $rows];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function actualizarGasto($id_egreso, $id_usuario, $tipo_egreso, $monto, $descripcion, $comprobante)
{
    try {
        $conexion = new Conexion();
        $conn = $conexion->ConexionBD();
        $sql = "UPDATE egresos 
                SET id_usuario = :id_usuario, 
                    tipo_egreso = :tipo_egreso, 
                    monto = :monto, 
                    descripcion = :descripcion, 
                    comprobante = :comprobante
                WHERE id_egreso = :id_egreso";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_egreso', $tipo_egreso, PDO::PARAM_STR);
        $stmt->bindValue(':monto', $monto);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':comprobante', $comprobante, PDO::PARAM_STR);
        $stmt->bindValue(':id_egreso', $id_egreso, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Gasto actualizado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function eliminarGasto($id_egreso)
{
    try {
        $conexion = new Conexion();
        $conn = $conexion->ConexionBD();
        $sql = "DELETE FROM egresos WHERE id_egreso = :id_egreso";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_egreso', $id_egreso, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Gasto eliminado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function obtenerUsuarios()
{
    try {
        $conexion = new Conexion();
        $conn = $conexion->ConexionBD();
        $sql = "SELECT id_usuario, nombre 
                FROM usuarios 
                WHERE activo = true 
                ORDER BY nombre";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function obtenerTiposEgreso()
{
    return [
        'Sueldos y Salarios',
        'Alquiler',
        'Servicios Públicos',
        'Insumos',
        'Mantenimiento',
        'Transporte',
        'Publicidad',
        'Impuestos',
        'Equipamiento',
        'Otros'
    ];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {
            case 'crear':
                $id_usuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : null;
                $tipo_egreso = trim($_POST['tipo_egreso'] ?? '');
                $monto = isset($_POST['monto']) ? (float)$_POST['monto'] : null;
                $descripcion = trim($_POST['descripcion'] ?? '');
                $comprobante = trim($_POST['comprobante'] ?? '');

                if (!$tipo_egreso || $monto === null || !$descripcion) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos (tipo, monto y descripción)']);
                    exit;
                }
                if (strlen($tipo_egreso) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El tipo no puede tener más de 100 caracteres']);
                    exit;
                }
                if ($monto <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a 0']);
                    exit;
                }
                if (strlen($comprobante) > 255) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El comprobante no puede tener más de 255 caracteres']);
                    exit;
                }
                $result = crearGasto($id_usuario, $tipo_egreso, $monto, $descripcion, $comprobante);
                echo json_encode($result);
                break;

            case 'actualizar':
                $id_egreso = isset($_POST['id_egreso']) ? (int)$_POST['id_egreso'] : null;
                $id_usuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : null;
                $tipo_egreso = trim($_POST['tipo_egreso'] ?? '');
                $monto = isset($_POST['monto']) ? (float)$_POST['monto'] : null;
                $descripcion = trim($_POST['descripcion'] ?? '');
                $comprobante = trim($_POST['comprobante'] ?? '');

                if (!$id_egreso || !$tipo_egreso || $monto === null || !$descripcion) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                    exit;
                }
                if (strlen($tipo_egreso) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El tipo no puede tener más de 100 caracteres']);
                    exit;
                }
                if ($monto <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a 0']);
                    exit;
                }
                if (strlen($comprobante) > 255) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El comprobante no puede tener más de 255 caracteres']);
                    exit;
                }
                $result = actualizarGasto($id_egreso, $id_usuario, $tipo_egreso, $monto, $descripcion, $comprobante);
                echo json_encode($result);
                break;

            case 'eliminar':
                $id_egreso = isset($_POST['id_egreso']) ? (int)$_POST['id_egreso'] : null;
                if (!$id_egreso) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Falta id_egreso']);
                    exit;
                }
                $result = eliminarGasto($id_egreso);
                echo json_encode($result);
                break;

            case 'obtener_usuarios':
                $usuarios = obtenerUsuarios();
                echo json_encode($usuarios);
                break;

            case 'obtener_tipos':
                $tipos = obtenerTiposEgreso();
                echo json_encode($tipos);
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
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $result = leerGastos();
        if ($result['success']) {
            echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestión de Gastos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-responsive {
            overflow-x: auto;
        }

        .action-buttons {
            white-space: nowrap;
        }

        .numeric-column {
            text-align: right;
        }

        .total-row {
            background-color: #e3f2fd;
            font-weight: bold;
        }

        .badge-gasto {
            background-color: #dc3545;
        }
        
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .pagination {
            margin-bottom: 0;
            flex-wrap: wrap;
        }

        .page-link {
            min-width: 42px;
            text-align: center;
        }

        .table-row-count {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .search-container {
            max-width: 400px;
        }

        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .pagination {
                margin-top: 0.5rem;
            }
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
            <h1 class="display-5 fw-bold">Gestión de Gastos</h1>
            <p class="lead">Registro y control de egresos</p>
        </header>

        <div id="mensaje"></div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title">Total Gastos</h5>
                        <h3 id="totalGastos" class="card-text">$0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Gastos del Mes</h5>
                        <h3 id="gastosMes" class="card-text">$0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Promedio Diario</h5>
                        <h3 id="promedioDiario" class="card-text">$0</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Registro de gastos</h2>
            <div>
                <button id="btnRefrescar" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-repeat"></i> Refrescar
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearGastoModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Gasto
                </button>
            </div>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="search-container">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por descripción, tipo, comprobante...">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end">
                            <div class="me-3">
                                <select id="filterTipo" class="form-select">
                                    <option value="">Todos los tipos</option>
                                </select>
                            </div>
                            <div>
                                <select id="filterUsuario" class="form-select">
                                    <option value="">Todos los usuarios</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="tablaGastos" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <!-- Se quitó la columna ID -->
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th class="numeric-column">Monto</th>
                        <th>Usuario</th>
                        <th>Comprobante</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                        <td id="totalTabla" class="numeric-column"><strong>$0</strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Paginación -->
        <div class="pagination-container">
            <div class="table-row-count" id="rowCount">Mostrando 0 gastos</div>
            <nav>
                <ul class="pagination" id="pagination">
                </ul>
            </nav>
        </div>
    </div>

    <div class="modal fade" id="crearGastoModal" tabindex="-1" aria-labelledby="crearGastoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="crearGastoModalLabel">Registrar Nuevo Gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrear">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tipo_egreso" class="form-label">Tipo de Gasto *</label>
                                    <select class="form-select" id="tipo_egreso" name="tipo_egreso" required>
                                        <option value="">Seleccionar tipo...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monto" class="form-label">Monto *</label>
                                    <input type="number" class="form-control" id="monto" name="monto" step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="id_usuario" class="form-label">Usuario Responsable</label>
                                    <select class="form-select" id="id_usuario" name="id_usuario">
                                        <option value="">Sin usuario asignado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="comprobante" class="form-label">N° Comprobante</label>
                                    <input type="text" class="form-control" id="comprobante" name="comprobante" maxlength="255" placeholder="Número de factura, boleta, etc.">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="500" required placeholder="Descripción detallada del gasto..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar Gasto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editarGastoModal" tabindex="-1" aria-labelledby="editarGastoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarGastoModalLabel">Editar Gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formActualizar">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id_egreso" name="id_egreso">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_tipo_egreso" class="form-label">Tipo de Gasto *</label>
                                    <select class="form-select" id="edit_tipo_egreso" name="tipo_egreso" required>
                                        <option value="">Seleccionar tipo...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_monto" class="form-label">Monto *</label>
                                    <input type="number" class="form-control" id="edit_monto" name="monto" step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_id_usuario" class="form-label">Usuario Responsable</label>
                                    <select class="form-select" id="edit_id_usuario" name="id_usuario">
                                        <option value="">Sin usuario asignado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_comprobante" class="form-label">N° Comprobante</label>
                                    <input type="text" class="form-control" id="edit_comprobante" name="comprobante" maxlength="255">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_descripcion" class="form-label">Descripción *</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3" maxlength="500" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Gasto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<script>
    const apiUrl = window.location.href;
    let gastosData = [];
    let filteredData = [];
    let usuariosData = [];
    let tiposData = [];
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

    function formatearMoneda(valor) {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP'
        }).format(valor);
    }

    function formatearFecha(fecha) {
        if (!fecha) return '';
        const date = new Date(fecha);
        return date.toLocaleDateString('es-ES');
    }

    function calcularEstadisticas(gastos) {
        const total = gastos.reduce((sum, gasto) => sum + gasto.monto, 0);

        const ahora = new Date();
        const mesActual = gastos.filter(gasto => {
            const fechaGasto = new Date(gasto.fecha_egreso);
            return fechaGasto.getMonth() === ahora.getMonth() &&
                fechaGasto.getFullYear() === ahora.getFullYear();
        });
        const totalMes = mesActual.reduce((sum, gasto) => sum + gasto.monto, 0);

        const diasTranscurridos = ahora.getDate();
        const promedioDiario = totalMes / diasTranscurridos;

        return {
            total,
            totalMes,
            promedioDiario
        };
    }

    function filtrarGastos() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const filterTipo = document.getElementById('filterTipo').value;
        const filterUsuario = document.getElementById('filterUsuario').value;
        
        filteredData = gastosData.filter(gasto => {
            const matchesSearch = 
                gasto.descripcion.toLowerCase().includes(searchTerm) || 
                gasto.tipo_egreso.toLowerCase().includes(searchTerm) ||
                (gasto.comprobante && gasto.comprobante.toLowerCase().includes(searchTerm));
            
            const matchesTipo = filterTipo === '' || gasto.tipo_egreso === filterTipo;
            
            const matchesUsuario = filterUsuario === '' || 
                (filterUsuario === 'sin_usuario' && !gasto.nombre_usuario) ||
                (gasto.nombre_usuario && gasto.nombre_usuario === filterUsuario);
            
            return matchesSearch && matchesTipo && matchesUsuario;
        });
        
        currentPage = 1;
        renderTable();
        renderPagination();
        actualizarEstadisticas();
    }

    function renderTable() {
        const tbody = document.querySelector('#tablaGastos tbody');
        tbody.innerHTML = '';
        
        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = Math.min(startIndex + rowsPerPage, filteredData.length);
        const currentPageData = filteredData.slice(startIndex, endIndex);
        
        if (currentPageData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4">No se encontraron gastos</td></tr>`;
            return;
        }
        
        let totalPagina = 0;
        
        currentPageData.forEach(g => {
            totalPagina += g.monto;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${formatearFecha(g.fecha_egreso)}</td>
                <td><span class="badge bg-danger">${escapeHtml(g.tipo_egreso)}</span></td>
                <td>${escapeHtml(g.descripcion)}</td>
                <td class="numeric-column">${formatearMoneda(g.monto)}</td>
                <td>${escapeHtml(g.nombre_usuario || 'No asignado')}</td>
                <td>${escapeHtml(g.comprobante || 'Sin comprobante')}</td>
                <td class="action-buttons">
                  <button data-id="${g.id_egreso}" class="btn btn-sm btn-outline-primary btnEditar" data-bs-toggle="modal" data-bs-target="#editarGastoModal">
                    <i class="bi bi-pencil"></i> Editar
                  </button>
                  <button data-id="${g.id_egreso}" class="btn btn-sm btn-outline-danger btnEliminar">
                    <i class="bi bi-trash"></i> Eliminar
                  </button>
                </td>`;
            tbody.appendChild(tr);
        });
        
        document.getElementById('totalTabla').innerHTML = `<strong>${formatearMoneda(totalPagina)}</strong>`;
        document.getElementById('rowCount').textContent = 
            `Mostrando ${startIndex + 1}-${endIndex} de ${filteredData.length} gastos`;
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

    function actualizarEstadisticas() {
        const stats = calcularEstadisticas(filteredData);
        document.getElementById('totalGastos').textContent = formatearMoneda(stats.total);
        document.getElementById('gastosMes').textContent = formatearMoneda(stats.totalMes);
        document.getElementById('promedioDiario').textContent = formatearMoneda(stats.promedioDiario);
    }

    async function cargarUsuarios() {
        try {
            const formData = new URLSearchParams();
            formData.append('accion', 'obtener_usuarios');

            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            });
            if (!res.ok) throw new Error('Error al cargar usuarios');
            usuariosData = await res.json();

            // Para selects de formularios
            const selects = ['id_usuario', 'edit_id_usuario'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    select.innerHTML = '<option value="">Sin usuario asignado</option>';
                    usuariosData.forEach(usuario => {
                        const option = document.createElement('option');
                        option.value = usuario.id_usuario;
                        option.textContent = usuario.nombre;
                        select.appendChild(option);
                    });
                }
            });

            // Para filtro
            const filterUsuario = document.getElementById('filterUsuario');
            if (filterUsuario) {
                filterUsuario.innerHTML = '<option value="">Todos los usuarios</option><option value="sin_usuario">Sin usuario asignado</option>';
                usuariosData.forEach(usuario => {
                    const option = document.createElement('option');
                    option.value = usuario.nombre;
                    option.textContent = usuario.nombre;
                    filterUsuario.appendChild(option);
                });
            }
        } catch (err) {
            console.error('Error cargando usuarios:', err);
        }
    }

    async function cargarTipos() {
        try {
            const formData = new URLSearchParams();
            formData.append('accion', 'obtener_tipos');

            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            });
            if (!res.ok) throw new Error('Error al cargar tipos');
            tiposData = await res.json();

            // Para selects de formularios
            const selects = ['tipo_egreso', 'edit_tipo_egreso'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    select.innerHTML = '<option value="">Seleccionar tipo...</option>';
                    tiposData.forEach(tipo => {
                        const option = document.createElement('option');
                        option.value = tipo;
                        option.textContent = tipo;
                        select.appendChild(option);
                    });
                }
            });

            // Para filtro
            const filterTipo = document.getElementById('filterTipo');
            if (filterTipo) {
                filterTipo.innerHTML = '<option value="">Todos los tipos</option>';
                tiposData.forEach(tipo => {
                    const option = document.createElement('option');
                    option.value = tipo;
                    option.textContent = tipo;
                    filterTipo.appendChild(option);
                });
            }
        } catch (err) {
            console.error('Error cargando tipos:', err);
        }
    }

    async function fetchGastos() {
        try {
            const res = await fetch(apiUrl + '?api=1');
            if (!res.ok) throw new Error('Error al obtener gastos: ' + res.status);
            const data = await res.json();
            gastosData = data;
            filteredData = [...gastosData];
            
            renderTable();
            renderPagination();
            actualizarEstadisticas();
        } catch (err) {
            mostrarMensaje(err.message, 'error');
        }
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

    document.addEventListener('DOMContentLoaded', function() {
        cargarUsuarios();
        cargarTipos();

        document.getElementById('btnRefrescar').addEventListener('click', () => {
            fetchGastos();
            cargarUsuarios();
            cargarTipos();
        });

        // Event listeners para filtros
        document.getElementById('searchInput').addEventListener('input', filtrarGastos);
        document.getElementById('filterTipo').addEventListener('change', filtrarGastos);
        document.getElementById('filterUsuario').addEventListener('change', filtrarGastos);

        // Event listener para paginación
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
            body.append('id_usuario', f.id_usuario.value);
            body.append('tipo_egreso', f.tipo_egreso.value);
            body.append('monto', f.monto.value);
            body.append('descripcion', f.descripcion.value);
            body.append('comprobante', f.comprobante.value);

            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    body
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    mostrarMensaje(data.message || 'Gasto registrado correctamente');
                    f.reset();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('crearGastoModal'));
                    modal.hide();
                    fetchGastos();
                } else {
                    mostrarMensaje(data.message || 'Error al registrar gasto', 'error');
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
            body.append('id_egreso', f.id_egreso.value);
            body.append('id_usuario', f.id_usuario.value);
            body.append('tipo_egreso', f.tipo_egreso.value);
            body.append('monto', f.monto.value);
            body.append('descripcion', f.descripcion.value);
            body.append('comprobante', f.comprobante.value);

            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    body
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    mostrarMensaje(data.message || 'Gasto actualizado correctamente');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editarGastoModal'));
                    modal.hide();
                    fetchGastos();
                } else {
                    mostrarMensaje(data.message || 'Error al actualizar gasto', 'error');
                }
            } catch (err) {
                mostrarMensaje(err.message, 'error');
            }
        });

        document.querySelector('#tablaGastos tbody').addEventListener('click', async (e) => {
            if (e.target.classList.contains('btnEliminar') || e.target.closest('.btnEliminar')) {
                const btn = e.target.classList.contains('btnEliminar') ? e.target : e.target.closest('.btnEliminar');
                const id = btn.dataset.id;

                if (!confirm('¿Está seguro de que desea eliminar este gasto?')) return;

                const body = new URLSearchParams();
                body.append('accion', 'eliminar');
                body.append('id_egreso', id);
                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Gasto eliminado correctamente');
                        fetchGastos();
                    } else mostrarMensaje(data.message || 'Error al eliminar gasto', 'error');
                } catch (err) {
                    mostrarMensaje(err.message, 'error');
                }
            }

            if (e.target.classList.contains('btnEditar') || e.target.closest('.btnEditar')) {
                const btn = e.target.classList.contains('btnEditar') ? e.target : e.target.closest('.btnEditar');
                const id = btn.dataset.id;

                const gasto = gastosData.find(g => g.id_egreso == id);
                if (gasto) {
                    document.querySelector('#formActualizar #edit_id_egreso').value = gasto.id_egreso;
                    document.querySelector('#formActualizar #edit_tipo_egreso').value = gasto.tipo_egreso;
                    document.querySelector('#formActualizar #edit_monto').value = gasto.monto;
                    document.querySelector('#formActualizar #edit_descripcion').value = gasto.descripcion;
                    document.querySelector('#formActualizar #edit_comprobante').value = gasto.comprobante || '';

                    const usuarioSelect = document.querySelector('#formActualizar #edit_id_usuario');
                    usuarioSelect.value = gasto.id_usuario || '';
                }
            }
        });

        fetchGastos();
    });
</script>
</body>

</html>
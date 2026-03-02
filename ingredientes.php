<?php

require_once __DIR__ . '/Conexion.php';
require_once 'auth.php';

function crearIngrediente($nombre_ingrediente, $descripcion, $costo_unitario, $stock, $es_extra, $precio_extra)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "INSERT INTO ingredientes (nombre_ingrediente, descripcion, costo_unitario, stock, es_extra, precio_extra) 
                VALUES (:nombre_ingrediente, :descripcion, :costo_unitario, :stock, :es_extra, :precio_extra)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre_ingrediente', $nombre_ingrediente, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':costo_unitario', $costo_unitario);
        $stmt->bindValue(':stock', $stock, PDO::PARAM_INT);
        $stmt->bindValue(':es_extra', $es_extra, PDO::PARAM_BOOL);
        $stmt->bindValue(':precio_extra', $precio_extra);
        $stmt->execute();
        return ['success' => true, 'message' => 'Ingrediente creado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function leerIngredientes()
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "SELECT id_ingrediente, nombre_ingrediente, descripcion, costo_unitario, stock, activo, es_extra, precio_extra
                FROM ingredientes
                ORDER BY nombre_ingrediente";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id_ingrediente'] = isset($row['id_ingrediente']) ? (int)$row['id_ingrediente'] : null;
            $row['costo_unitario'] = isset($row['costo_unitario']) ? (float)$row['costo_unitario'] : 0.00;
            $row['stock'] = isset($row['stock']) ? (int)$row['stock'] : 0;
            $row['activo'] = isset($row['activo']) ? (bool)$row['activo'] : false;
            $row['es_extra'] = isset($row['es_extra']) ? (bool)$row['es_extra'] : false;
            $row['precio_extra'] = isset($row['precio_extra']) ? (float)$row['precio_extra'] : 0.00;
        }
        return ['success' => true, 'data' => $rows];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function actualizarIngrediente($id_ingrediente, $nombre_ingrediente, $descripcion, $costo_unitario, $stock, $activo, $es_extra, $precio_extra)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "UPDATE ingredientes 
                SET nombre_ingrediente = :nombre_ingrediente, 
                    descripcion = :descripcion, 
                    costo_unitario = :costo_unitario, 
                    stock = :stock, 
                    activo = :activo, 
                    es_extra = :es_extra, 
                    precio_extra = :precio_extra
                WHERE id_ingrediente = :id_ingrediente";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre_ingrediente', $nombre_ingrediente, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':costo_unitario', $costo_unitario);
        $stmt->bindValue(':stock', $stock, PDO::PARAM_INT);
        $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $stmt->bindValue(':es_extra', $es_extra, PDO::PARAM_BOOL);
        $stmt->bindValue(':precio_extra', $precio_extra);
        $stmt->bindValue(':id_ingrediente', $id_ingrediente, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Ingrediente actualizado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function agregarStock($id_ingrediente, $cantidad)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "UPDATE ingredientes 
                SET stock = stock + :cantidad 
                WHERE id_ingrediente = :id_ingrediente";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->bindValue(':id_ingrediente', $id_ingrediente, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Stock agregado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function eliminarIngrediente($id_ingrediente)
{
    try {
        $conn = Conexion::ConexionBD();
        $sql = "DELETE FROM ingredientes WHERE id_ingrediente = :id_ingrediente";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_ingrediente', $id_ingrediente, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'message' => 'Ingrediente eliminado correctamente'];
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
                $nombre_ingrediente = trim($_POST['nombre_ingrediente'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $costo_unitario = isset($_POST['costo_unitario']) ? (float)$_POST['costo_unitario'] : null;
                $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
                $es_extra = isset($_POST['es_extra']) ? filter_var($_POST['es_extra'], FILTER_VALIDATE_BOOLEAN) : false;
                $precio_extra = isset($_POST['precio_extra']) ? (float)$_POST['precio_extra'] : 0.00;

                if (!$nombre_ingrediente || $costo_unitario === null) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos (nombre y costo unitario)']);
                    exit;
                }
                if (strlen($nombre_ingrediente) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre no puede tener más de 100 caracteres']);
                    exit;
                }
                if ($costo_unitario < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El costo unitario no puede ser negativo']);
                    exit;
                }
                if ($stock < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El stock no puede ser negativo']);
                    exit;
                }
                if ($es_extra && $precio_extra <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Si es extra, el precio extra debe ser mayor a 0']);
                    exit;
                }
                $result = crearIngrediente($nombre_ingrediente, $descripcion, $costo_unitario, $stock, $es_extra, $precio_extra);
                echo json_encode($result);
                break;

            case 'actualizar':
                $id_ingrediente = isset($_POST['id_ingrediente']) ? (int)$_POST['id_ingrediente'] : null;
                $nombre_ingrediente = trim($_POST['nombre_ingrediente'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $costo_unitario = isset($_POST['costo_unitario']) ? (float)$_POST['costo_unitario'] : null;
                $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
                $activo = isset($_POST['activo']) ? filter_var($_POST['activo'], FILTER_VALIDATE_BOOLEAN) : false;
                $es_extra = isset($_POST['es_extra']) ? filter_var($_POST['es_extra'], FILTER_VALIDATE_BOOLEAN) : false;
                $precio_extra = isset($_POST['precio_extra']) ? (float)$_POST['precio_extra'] : 0.00;

                if (!$id_ingrediente || !$nombre_ingrediente || $costo_unitario === null) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                    exit;
                }
                if (strlen($nombre_ingrediente) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El nombre no puede tener más de 100 caracteres']);
                    exit;
                }
                if ($costo_unitario < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El costo unitario no puede ser negativo']);
                    exit;
                }
                if ($stock < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El stock no puede ser negativo']);
                    exit;
                }
                if ($es_extra && $precio_extra <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Si es extra, el precio extra debe ser mayor a 0']);
                    exit;
                }
                $result = actualizarIngrediente($id_ingrediente, $nombre_ingrediente, $descripcion, $costo_unitario, $stock, $activo, $es_extra, $precio_extra);
                echo json_encode($result);
                break;

            case 'agregar_stock':
                $id_ingrediente = isset($_POST['id_ingrediente']) ? (int)$_POST['id_ingrediente'] : null;
                $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;

                if (!$id_ingrediente || $cantidad <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID de ingrediente y cantidad válida son requeridos']);
                    exit;
                }
                $result = agregarStock($id_ingrediente, $cantidad);
                echo json_encode($result);
                break;

            case 'eliminar':
                $id_ingrediente = isset($_POST['id_ingrediente']) ? (int)$_POST['id_ingrediente'] : null;
                if (!$id_ingrediente) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Falta id_ingrediente']);
                    exit;
                }
                $result = eliminarIngrediente($id_ingrediente);
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
    $result = leerIngredientes();
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
    <title>Gestión de Ingredientes</title>
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

        .badge-extra {
            background-color: #6f42c1;
        }

        .stock-low {
            color: #dc3545;
            font-weight: bold;
        }

        .stock-medium {
            color: #ffc107;
            font-weight: bold;
        }

        .stock-good {
            color: #198754;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 0.75rem;
        }

        .small-muted {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>

<body class="bg-light">
    <?php
    $currentPage = 'ingredientes';
    include 'menu.php';
    ?>
    <div class="container my-4">
        <header class="py-3 mb-4 border-bottom">
            <h1 class="display-5 fw-bold">Gestión de Ingredientes</h1>
        </header>

        <div id="mensaje"></div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Lista de ingredientes</h2>
            <div>
                <button id="btnRefrescar" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-repeat"></i> Refrescar
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearIngredienteModal">
                    <i class="bi bi-plus-circle"></i> Nuevo Ingrediente
                </button>
            </div>
        </div>

        <div class="row mb-3 g-2">
            <div class="col-md-6">
                <input id="buscador" class="form-control" placeholder="Buscar por nombre o descripción..." />
            </div>
            <div class="col-md-3">
                <select id="pageSizeSelect" class="form-select">
                    <option value="5">5 por página</option>
                    <option value="10" selected>10 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                </select>
            </div>
            <div class="col-md-3 text-end small-muted" id="resultInfo"></div>
        </div>

        <div class="table-responsive">
            <table id="tablaIngredientes" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="numeric-column">Costo Unitario</th>
                        <th class="numeric-column">Stock</th>
                        <th>Estado</th>
                        <th>Es Extra</th>
                        <th class="numeric-column">Precio Extra</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="pagination-container mt-2">
            <nav>
                <ul id="pagination" class="pagination mb-0"></ul>
            </nav>
            <div class="small-muted" id="pageSummary"></div>
        </div>
    </div>

    <div class="modal fade" id="crearIngredienteModal" tabindex="-1" aria-labelledby="crearIngredienteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="crearIngredienteModalLabel">Crear Nuevo Ingrediente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrear">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nombre_ingrediente" class="form-label">Nombre del Ingrediente *</label>
                                    <input type="text" class="form-control" id="nombre_ingrediente" name="nombre_ingrediente" required maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="costo_unitario" class="form-label">Costo Unitario *</label>
                                    <input type="number" class="form-control" id="costo_unitario" name="costo_unitario" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="500"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="stock" class="form-label">Stock</label>
                                    <input type="number" class="form-control" id="stock" name="stock" value="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="es_extra" name="es_extra">
                                    <label class="form-check-label" for="es_extra">Es Extra</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="precio_extra" class="form-label">Precio Extra</label>
                                    <input type="number" class="form-control" id="precio_extra" name="precio_extra" step="0.01" min="0" value="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Ingrediente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editarIngredienteModal" tabindex="-1" aria-labelledby="editarIngredienteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarIngredienteModalLabel">Editar Ingrediente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formActualizar">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id_ingrediente" name="id_ingrediente">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nombre_ingrediente" class="form-label">Nombre del Ingrediente *</label>
                                    <input type="text" class="form-control" id="edit_nombre_ingrediente" name="nombre_ingrediente" required maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_costo_unitario" class="form-label">Costo Unitario *</label>
                                    <input type="number" class="form-control" id="edit_costo_unitario" name="costo_unitario" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3" maxlength="500"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="edit_stock" class="form-label">Stock</label>
                                    <input type="number" class="form-control" id="edit_stock" name="stock" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit_activo" name="activo">
                                    <label class="form-check-label" for="edit_activo">Activo</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit_es_extra" name="es_extra">
                                    <label class="form-check-label" for="edit_es_extra">Es Extra</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="edit_precio_extra" class="form-label">Precio Extra</label>
                                    <input type="number" class="form-control" id="edit_precio_extra" name="precio_extra" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Ingrediente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="agregarStockModal" tabindex="-1" aria-labelledby="agregarStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="agregarStockModalLabel">Agregar Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formAgregarStock">
                    <div class="modal-body">
                        <input type="hidden" id="stock_id_ingrediente" name="id_ingrediente">
                        <div class="mb-3">
                            <label for="ingrediente_nombre" class="form-label">Ingrediente</label>
                            <input type="text" class="form-control" id="ingrediente_nombre" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="stock_actual" class="form-label">Stock Actual</label>
                            <input type="text" class="form-control" id="stock_actual" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="cantidad" class="form-label">Cantidad a Agregar *</label>
                            <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Agregar Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <script>
        const apiUrl = window.location.href;
        let ingredientesData = [];
        let filteredData = [];
        let currentPage = 1;
        let pageSize = 10;

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

        function getStockClass(stock) {
            if (stock === 0) return 'stock-low';
            if (stock <= 10) return 'stock-medium';
            return 'stock-good';
        }

        async function fetchIngredientes() {
            try {
                const res = await fetch(apiUrl + '?api=1');
                if (!res.ok) throw new Error('Error al obtener ingredientes: ' + res.status);
                const data = await res.json();
                ingredientesData = data;
                applyFilterAndRender();
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
                .replace(/\"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function applyFilterAndRender() {
            const q = document.getElementById('buscador').value.trim().toLowerCase();
            if (!q) filteredData = [...ingredientesData];
            else {
                filteredData = ingredientesData.filter(i => {
                    const nombre = (i.nombre_ingrediente || '').toString().toLowerCase();
                    const desc = (i.descripcion || '').toString().toLowerCase();
                    return nombre.includes(q) || desc.includes(q);
                });
            }

            const totalPages = Math.max(1, Math.ceil(filteredData.length / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;

            renderTable();
            renderPagination();
            updateResultInfo();
        }

        function renderTable() {
            const tbody = document.querySelector('#tablaIngredientes tbody');
            tbody.innerHTML = '';

            const start = (currentPage - 1) * pageSize;
            const pageItems = filteredData.slice(start, start + pageSize);

            pageItems.forEach(i => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
            <td>${escapeHtml(i.nombre_ingrediente)}</td>
            <td>${escapeHtml(i.descripcion || '')}</td>
            <td class="numeric-column">${formatearMoneda(i.costo_unitario)}</td>
            <td class="numeric-column ${getStockClass(i.stock)}">${i.stock}</td>
            <td><span class="badge ${i.activo ? 'bg-success' : 'bg-secondary'}">${i.activo ? 'Activo' : 'Inactivo'}</span></td>
            <td><span class="badge ${i.es_extra ? 'badge-extra' : 'bg-secondary'}">${i.es_extra ? 'Sí' : 'No'}</span></td>
            <td class="numeric-column">${i.es_extra ? formatearMoneda(i.precio_extra) : '-'}</td>
            <td class="action-buttons">
              <button data-id="${i.id_ingrediente}" class="btn btn-sm btn-outline-success btnAgregarStock" data-bs-toggle="modal" data-bs-target="#agregarStockModal">
                <i class="bi bi-plus-circle"></i> Stock
              </button>
              <button data-id="${i.id_ingrediente}" class="btn btn-sm btn-outline-primary btnEditar" data-bs-toggle="modal" data-bs-target="#editarIngredienteModal">
                <i class="bi bi-pencil"></i> Editar
              </button>
              <button data-id="${i.id_ingrediente}" class="btn btn-sm btn-outline-danger btnEliminar">
                <i class="bi bi-trash"></i> Eliminar
              </button>
            </td>`;
                tbody.appendChild(tr);
            });

            if (pageItems.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td colspan="8" class="text-center small-muted">No se encontraron ingredientes.</td>`;
                tbody.appendChild(tr);
            }
        }

        function renderPagination() {
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            const totalItems = filteredData.length;
            const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

            function createPageItem(page, label = null, active = false, disabled = false) {
                const li = document.createElement('li');
                li.className = 'page-item ' + (active ? 'active' : '') + (disabled ? ' disabled' : '');
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.dataset.page = page;
                a.textContent = label || page;
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (!disabled) {
                        currentPage = page;
                        renderTable();
                        renderPagination();
                        updateResultInfo();
                        document.querySelector('#tablaIngredientes').scrollIntoView({ behavior: 'smooth' });
                    }
                });
                li.appendChild(a);
                return li;
            }

            pagination.appendChild(createPageItem(Math.max(1, currentPage - 1), '«', false, currentPage === 1));

            const windowSize = 5;
            let start = Math.max(1, currentPage - Math.floor(windowSize / 2));
            let end = Math.min(totalPages, start + windowSize - 1);
            if (end - start < windowSize - 1) {
                start = Math.max(1, end - windowSize + 1);
            }

            if (start > 1) {
                pagination.appendChild(createPageItem(1, '1'));
                if (start > 2) {
                    const li = document.createElement('li');
                    li.className = 'page-item disabled';
                    li.innerHTML = '<span class="page-link">...</span>';
                    pagination.appendChild(li);
                }
            }

            for (let p = start; p <= end; p++) {
                pagination.appendChild(createPageItem(p, null, p === currentPage));
            }

            if (end < totalPages) {
                if (end < totalPages - 1) {
                    const li = document.createElement('li');
                    li.className = 'page-item disabled';
                    li.innerHTML = '<span class="page-link">...</span>';
                    pagination.appendChild(li);
                }
                pagination.appendChild(createPageItem(totalPages, totalPages));
            }

            pagination.appendChild(createPageItem(Math.min(totalPages, currentPage + 1), '»', false, currentPage === totalPages));
        }

        function updateResultInfo() {
            const info = document.getElementById('resultInfo');
            const pageSummary = document.getElementById('pageSummary');
            const total = filteredData.length;
            const start = total === 0 ? 0 : (currentPage - 1) * pageSize + 1;
            const end = Math.min(total, currentPage * pageSize);
            info.textContent = `${total} resultado(s)`;
            pageSummary.textContent = `Mostrando ${start}–${end} de ${total}`;
        }

        document.getElementById('es_extra').addEventListener('change', function() {
            document.getElementById('precio_extra').disabled = !this.checked;
        });

        document.getElementById('edit_es_extra').addEventListener('change', function() {
            document.getElementById('edit_precio_extra').disabled = !this.checked;
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('btnRefrescar').addEventListener('click', () => { currentPage = 1; fetchIngredientes(); });

            document.getElementById('pageSizeSelect').addEventListener('change', function() {
                pageSize = parseInt(this.value, 10) || 10;
                currentPage = 1;
                applyFilterAndRender();
            });

            document.getElementById('buscador').addEventListener('input', function() {
                currentPage = 1;
                if (this._timeout) clearTimeout(this._timeout);
                this._timeout = setTimeout(() => applyFilterAndRender(), 200);
            });

            document.getElementById('formCrear').addEventListener('submit', async (e) => {
                e.preventDefault();
                const f = e.target;
                const body = new URLSearchParams();
                body.append('accion', 'crear');
                body.append('nombre_ingrediente', f.nombre_ingrediente.value);
                body.append('descripcion', f.descripcion.value);
                body.append('costo_unitario', f.costo_unitario.value);
                body.append('stock', f.stock.value);
                body.append('es_extra', f.es_extra.checked);
                body.append('precio_extra', f.precio_extra.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Ingrediente creado correctamente');
                        f.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('crearIngredienteModal'));
                        modal.hide();
                        currentPage = 1;
                        fetchIngredientes();
                    } else {
                        mostrarMensaje(data.message || 'Error al crear ingrediente', 'error');
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
                body.append('id_ingrediente', f.id_ingrediente.value);
                body.append('nombre_ingrediente', f.nombre_ingrediente.value);
                body.append('descripcion', f.descripcion.value);
                body.append('costo_unitario', f.costo_unitario.value);
                body.append('stock', f.stock.value);
                body.append('activo', f.activo.checked);
                body.append('es_extra', f.es_extra.checked);
                body.append('precio_extra', f.precio_extra.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Ingrediente actualizado correctamente');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editarIngredienteModal'));
                        modal.hide();
                        fetchIngredientes();
                    } else {
                        mostrarMensaje(data.message || 'Error al actualizar ingrediente', 'error');
                    }
                } catch (err) {
                    mostrarMensaje(err.message, 'error');
                }
            });

            document.getElementById('formAgregarStock').addEventListener('submit', async (e) => {
                e.preventDefault();
                const f = e.target;
                const body = new URLSearchParams();
                body.append('accion', 'agregar_stock');
                body.append('id_ingrediente', f.id_ingrediente.value);
                body.append('cantidad', f.cantidad.value);

                try {
                    const res = await fetch(apiUrl, {
                        method: 'POST',
                        body
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        mostrarMensaje(data.message || 'Stock agregado correctamente');
                        f.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('agregarStockModal'));
                        modal.hide();
                        fetchIngredientes();
                    } else {
                        mostrarMensaje(data.message || 'Error al agregar stock', 'error');
                    }
                } catch (err) {
                    mostrarMensaje(err.message, 'error');
                }
            });

            document.querySelector('#tablaIngredientes tbody').addEventListener('click', async (e) => {
                if (e.target.classList.contains('btnEliminar') || e.target.closest('.btnEliminar')) {
                    const btn = e.target.classList.contains('btnEliminar') ? e.target : e.target.closest('.btnEliminar');
                    const id = btn.dataset.id;

                    if (!confirm('¿Está seguro de que desea eliminar el ingrediente?')) return;

                    const body = new URLSearchParams();
                    body.append('accion', 'eliminar');
                    body.append('id_ingrediente', id);
                    try {
                        const res = await fetch(apiUrl, {
                            method: 'POST',
                            body
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            mostrarMensaje(data.message || 'Ingrediente eliminado correctamente');
                            currentPage = 1;
                            fetchIngredientes();
                        } else mostrarMensaje(data.message || 'Error al eliminar ingrediente', 'error');
                    } catch (err) {
                        mostrarMensaje(err.message, 'error');
                    }
                }

                if (e.target.classList.contains('btnEditar') || e.target.closest('.btnEditar')) {
                    const btn = e.target.classList.contains('btnEditar') ? e.target : e.target.closest('.btnEditar');
                    const id = btn.dataset.id;

                    const ingrediente = ingredientesData.find(i => i.id_ingrediente == id);
                    if (ingrediente) {
                        document.querySelector('#formActualizar #edit_id_ingrediente').value = ingrediente.id_ingrediente;
                        document.querySelector('#formActualizar #edit_nombre_ingrediente').value = ingrediente.nombre_ingrediente;
                        document.querySelector('#formActualizar #edit_descripcion').value = ingrediente.descripcion || '';
                        document.querySelector('#formActualizar #edit_costo_unitario').value = ingrediente.costo_unitario;
                        document.querySelector('#formActualizar #edit_stock').value = ingrediente.stock;
                        document.querySelector('#formActualizar #edit_activo').checked = ingrediente.activo;
                        document.querySelector('#formActualizar #edit_es_extra').checked = ingrediente.es_extra;
                        document.querySelector('#formActualizar #edit_precio_extra').value = ingrediente.precio_extra;
                        document.querySelector('#formActualizar #edit_precio_extra').disabled = !ingrediente.es_extra;
                    }
                }

                if (e.target.classList.contains('btnAgregarStock') || e.target.closest('.btnAgregarStock')) {
                    const btn = e.target.classList.contains('btnAgregarStock') ? e.target : e.target.closest('.btnAgregarStock');
                    const id = btn.dataset.id;

                    const ingrediente = ingredientesData.find(i => i.id_ingrediente == id);
                    if (ingrediente) {
                        document.querySelector('#formAgregarStock #stock_id_ingrediente').value = ingrediente.id_ingrediente;
                        document.querySelector('#formAgregarStock #ingrediente_nombre').value = ingrediente.nombre_ingrediente;
                        document.querySelector('#formAgregarStock #stock_actual').value = ingrediente.stock;
                        document.querySelector('#formAgregarStock #cantidad').value = '';
                    }
                }
            });

            document.getElementById('precio_extra').disabled = true;
            document.getElementById('edit_precio_extra').disabled = true;

            fetchIngredientes();
        });
    </script>
</body>

</html>

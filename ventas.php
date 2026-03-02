<?php

$currentPage = 'ventas';
include 'menu.php';
include 'Conexion.php';
require_once 'auth.php';

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_venta'])) {
    $id_usuario = $_SESSION['id_usuario'];
    $tipo_pago = $_POST['tipo_pago'];
    $productos_data = json_decode($_POST['productos_data'], true);

    try {
        $database = new Conexion();
        $db = Conexion::ConexionBD();
        $db->beginTransaction();

        foreach ($productos_data as $producto) {
            if (!empty($producto['extras'])) {
                foreach ($producto['extras'] as $id_ingrediente) {
                    $query_stock = "SELECT stock, nombre_ingrediente 
                                   FROM ingredientes 
                                   WHERE id_ingrediente = :id_ingrediente 
                                   AND activo = true";
                    $stmt_stock = $db->prepare($query_stock);
                    $stmt_stock->bindParam(":id_ingrediente", $id_ingrediente);
                    $stmt_stock->execute();
                    $ingrediente_info = $stmt_stock->fetch(PDO::FETCH_ASSOC);

                    if (!$ingrediente_info) {
                        throw new Exception("El ingrediente ID $id_ingrediente no existe o no está activo");
                    }

                    if ($ingrediente_info['stock'] < 1) {
                        throw new Exception("Stock insuficiente para: " . $ingrediente_info['nombre_ingrediente'] .
                            ". Stock disponible: " . $ingrediente_info['stock']);
                    }
                }
            }
        }

        $total_venta = 0;

        $query_venta = "INSERT INTO ventas (id_usuario, total_venta, tipo_pago) 
                       VALUES (:id_usuario, 0, :tipo_pago) RETURNING id_venta";
        $stmt_venta = $db->prepare($query_venta);
        $stmt_venta->bindParam(":id_usuario", $id_usuario);
        $stmt_venta->bindParam(":tipo_pago", $tipo_pago);
        $stmt_venta->execute();
        $id_venta = $stmt_venta->fetch(PDO::FETCH_ASSOC)['id_venta'];

        foreach ($productos_data as $producto) {
            $id_producto = $producto['id_producto'];
            $cantidad = $producto['cantidad'];

            $query_producto = "SELECT precio_base FROM productos WHERE id_producto = :id_producto";
            $stmt_producto = $db->prepare($query_producto);
            $stmt_producto->bindParam(":id_producto", $id_producto);
            $stmt_producto->execute();
            $producto_info = $stmt_producto->fetch(PDO::FETCH_ASSOC);

            $precio_unitario = $producto_info['precio_base'];
            $subtotal_producto = $precio_unitario * $cantidad;
            $total_venta += $subtotal_producto;

            $query_det_venta = "INSERT INTO det_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                               VALUES (:id_venta, :id_producto, :cantidad, :precio_unitario, :subtotal) 
                               RETURNING id_det_venta";
            $stmt_det_venta = $db->prepare($query_det_venta);
            $stmt_det_venta->bindParam(":id_venta", $id_venta);
            $stmt_det_venta->bindParam(":id_producto", $id_producto);
            $stmt_det_venta->bindParam(":cantidad", $cantidad);
            $stmt_det_venta->bindParam(":precio_unitario", $precio_unitario);
            $stmt_det_venta->bindParam(":subtotal", $subtotal_producto);
            $stmt_det_venta->execute();
            $id_det_venta = $stmt_det_venta->fetch(PDO::FETCH_ASSOC)['id_det_venta'];

            if (!empty($producto['extras'])) {
                $query_extra = "INSERT INTO det_ven_extras (id_det_venta, id_ingrediente, cantidad, precio_extra) 
                               VALUES (:id_det_venta, :id_ingrediente, :cantidad_extra, :precio_extra)";
                $stmt_extra = $db->prepare($query_extra);

                foreach ($producto['extras'] as $id_ingrediente) {
                    $query_precio_extra = "SELECT precio_extra FROM ingredientes WHERE id_ingrediente = :id_ingrediente";
                    $stmt_precio = $db->prepare($query_precio_extra);
                    $stmt_precio->bindParam(":id_ingrediente", $id_ingrediente);
                    $stmt_precio->execute();
                    $precio_extra = $stmt_precio->fetch(PDO::FETCH_ASSOC)['precio_extra'];

                    $total_venta += $precio_extra * $cantidad;

                    $stmt_extra->bindParam(":id_det_venta", $id_det_venta);
                    $stmt_extra->bindParam(":id_ingrediente", $id_ingrediente);
                    $stmt_extra->bindParam(":precio_extra", $precio_extra);
                    $stmt_extra->bindParam(":cantidad_extra", $cantidad);
                    $stmt_extra->execute();
                }
            }
        }

        $query_update_total = "UPDATE ventas SET total_venta = :total_venta WHERE id_venta = :id_venta";
        $stmt_update = $db->prepare($query_update_total);
        $stmt_update->bindParam(":total_venta", $total_venta);
        $stmt_update->bindParam(":id_venta", $id_venta);
        $stmt_update->execute();

        $db->commit();
        $mensaje = "✅ Venta registrada! ID: $id_venta - Total: $" . number_format($total_venta, 2);

        echo "<script>localStorage.removeItem('carritoVentas');</script>";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}


$database = new Conexion();
$db = Conexion::ConexionBD();

$query_categorias = "SELECT c.*, c.acepta_extras 
                     FROM categorias c 
                     WHERE c.activo = true 
                     AND c.id_categoria IN (1, 2, 3, 6, 7, 8, 1509,1510)
                     ORDER BY c.id_categoria";
$stmt_categorias = $db->prepare($query_categorias);
$stmt_categorias->execute();
$categorias_data = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

$query_productos = "SELECT p.id_producto, p.nombre_producto, p.precio_base, p.descripcion,
                           c.id_categoria, c.nombre_categoria, c.acepta_extras
                    FROM productos p
                    INNER JOIN categorias c ON p.id_categoria = c.id_categoria
                    WHERE p.activo = true 
                    AND c.id_categoria IN (1, 2, 3, 6, 7, 8, 1509, 1510)
                    ORDER BY c.id_categoria, p.nombre_producto";
$stmt_productos = $db->prepare($query_productos);
$stmt_productos->execute();
$productos_data = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

$query_ingredientes = "SELECT id_ingrediente, nombre_ingrediente, precio_extra, stock 
                       FROM ingredientes 
                       WHERE activo = true AND es_extra = true
                       ORDER BY nombre_ingrediente";
$stmt_ingredientes = $db->prepare($query_ingredientes);
$stmt_ingredientes->execute();
$ingredientes_data = $stmt_ingredientes->fetchAll(PDO::FETCH_ASSOC);

$productos_por_categoria = [];
foreach ($productos_data as $p) {
    $productos_por_categoria[$p['id_categoria']][] = $p;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sistema de Ventas - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body,
        html {
            height: 100%;
            background-color: #f8f9fa;
        }


        .categoria-tabs {
            padding: .5rem;
            overflow-x: auto;
            white-space: nowrap;
            border-bottom: 1px solid #e9ecef;
            scrollbar-width: none;
        }

        .categoria-tabs::-webkit-scrollbar {
            display: none;
        }

        .categoria-tab {
            cursor: pointer;
            padding: 0.4rem 0.8rem;
            font-size: .85rem;
            margin-right: .4rem;
            border-radius: 50px;
            white-space: nowrap;
        }

        .categoria-activa {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
        }

        .productos-scroll {
            overflow-y: auto;
            padding: 1rem;
            height: calc(100vh - 120px);
        }

        .producto-card {
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
            border: 1px solid #e9ecef;
            height: 100%;
            background-color: #ffffff;
            border-radius: .5rem;
        }

        .producto-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: var(--bs-primary);
        }

        .producto-icon {
            width: 48px;
            height: 48px;
            font-size: 1.1rem;
        }

        .carrito-container {
            background-color: #ffffff;
            border-radius: .75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin: 1rem 0;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 3rem);
        }

        .carrito-scroll {
            flex-grow: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .carrito-footer {
            border-top: 1px solid #dee2e6;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0 0 .75rem .75rem;
        }

        .carrito-item .card-body {
            border-bottom: 1px dashed #e9ecef;
        }

        .carrito-item:last-child .card-body {
            border-bottom: none;
        }

        @media (max-width: 991.98px) {
            .productos-scroll {
                height: auto;
                max-height: calc(100vh - 120px);
            }

            .carrito-container {
                margin-top: 0;
                min-height: auto;
            }

        }
    </style>
</head>

<body>
    <div class="container-fluid pt-3">
        <div class="row">

            <div class="col-lg-8 col-12 mb-lg-0 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-dark text-white p-3">
                        <h1 class="h5 mb-0"><i class="bi bi-cup-hot-fill me-2 text-warning"></i> Venta Rápida</h1>
                    </div>

                    <div class="bg-light categoria-tabs" id="categorias-tabs" role="tablist" aria-label="Categorías">
                        <?php foreach ($categorias_data as $index => $categoria): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary categoria-tab <?php echo $index === 0 ? 'categoria-activa' : ''; ?>"
                                data-categoria="<?php echo $categoria['id_categoria']; ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                <i class="bi bi-tag me-1"></i> <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="productos-scroll" id="productos-grid">
                        <?php foreach ($categorias_data as $index => $categoria): ?>
                            <div class="categoria-productos mb-4" id="categoria-<?php echo $categoria['id_categoria']; ?>" style="<?php echo $index === 0 ? '' : 'display:none;'; ?>">
                                <h6 class="text-muted border-bottom pb-1 mb-3"><?php echo htmlspecialchars($categoria['nombre_categoria']); ?></h6>
                                <div class="row g-3" id="productos-categoria-<?php echo $categoria['id_categoria']; ?>">
                                    <?php
                                    $productos_cat = $productos_por_categoria[$categoria['id_categoria']] ?? [];
                                    if (count($productos_cat) === 0) {
                                        echo '<div class="col-12"><div class="alert alert-secondary text-center" role="alert"><i class="bi bi-info-circle me-2"></i> No hay productos activos en esta categoría.</div></div>';
                                    } else {
                                        foreach ($productos_cat as $producto):
                                    ?>
                                            <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6">
                                                <div class="card producto-card shadow-sm" tabindex="0"
                                                    data-producto='<?php echo json_encode($producto, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                                    <div class="card-body p-3 text-center">
                                                        <div class="bg-warning text-dark rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center producto-icon">
                                                            <i class="bi bi-<?php echo $producto['acepta_extras'] ? 'egg-fried' : 'cup-straw'; ?>"></i>
                                                        </div>
                                                        <h6 class="card-title mb-1 fw-bold text-truncate" style="font-size: 0.9rem;">
                                                            <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                                                        </h6>
                                                        <div class="fw-bolder text-success mb-1" style="font-size: 1.0rem;">
                                                            $<?php echo number_format($producto['precio_base'], 2); ?>
                                                        </div>
                                                        <?php if ($producto['acepta_extras']): ?>
                                                            <span class="badge bg-info bg-opacity-10 text-info" style="font-size: 0.75rem;">
                                                                <i class="bi bi-plus-circle me-1"></i> Customizable
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php
                                        endforeach;
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="carrito-container shadow-lg">
                    <div class="bg-success text-white p-3 rounded-top-3">
                        <h2 class="h5 mb-0"><i class="bi bi-cart3 me-2"></i> Carrito de Compras</h2>
                    </div>

                    <div class="carrito-scroll">
                        <div id="carrito-vacio" class="text-center text-muted py-5">
                            <i class="bi bi-cart-x display-3"></i>
                            <p class="mt-3 mb-0 fs-5">Carrito vacío</p>
                            <small>Selecciona productos a la izquierda.</small>
                        </div>
                        <div id="carrito-items" style="display:none;"></div>
                    </div>

                    <div class="carrito-footer">
                        <div class="d-flex justify-content-between mb-2">
                            <strong class="text-muted">Subtotal:</strong>
                            <span id="subtotal" class="fw-bold">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 border-top pt-2">
                            <strong class="fs-4 text-primary">TOTAL:</strong>
                            <span id="total-venta" class="fs-4 fw-bolder text-success">$0.00</span>
                        </div>

                        <form method="POST" action="" id="form-venta">
                            <input type="hidden" name="productos_data" id="productos-data">

                            <div class="mb-3">
                                <label class="form-label mb-1 fw-bold">Método de Pago:</label>
                                <select name="tipo_pago" class="form-select" required>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                </select>
                            </div>

                            <button type="submit" name="registrar_venta" class="btn btn-success btn-lg w-100" id="btn-registrar-venta" disabled>
                                <i class="bi bi-bag-check me-2"></i> Registrar Venta
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <div class="modal fade" id="modalExtras" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title h6" id="modalExtrasTitle"><i class="bi bi-wrench-adjustable me-2"></i> Personalizar Producto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body py-3 modal-extras">
                    <div id="modalExtrasContent"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success btn-sm" id="btnConfirmarExtras">
                        <i class="bi bi-check-lg"></i> Agregar al Carrito
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JS - Funciones y lógica se mantienen, solo se ajusta la función actualizarCarrito para el nuevo HTML

        const ingredientes = <?php echo json_encode($ingredientes_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let carrito = [];
        let productoPendiente = null;
        let modalExtrasInstance = null;

        document.addEventListener('DOMContentLoaded', () => {
            // Inicializar Tabs
            document.querySelectorAll('.categoria-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.categoria-tab').forEach(t => {
                        t.classList.remove('categoria-activa', 'btn-primary');
                        t.classList.add('btn-outline-primary');
                        t.setAttribute('aria-pressed', 'false');
                    });
                    this.classList.add('categoria-activa', 'btn-primary');
                    this.classList.remove('btn-outline-primary');
                    this.setAttribute('aria-pressed', 'true');

                    const categoriaId = this.getAttribute('data-categoria');
                    document.querySelectorAll('.categoria-productos').forEach(div => div.style.display = 'none');
                    const target = document.getElementById('categoria-' + categoriaId);
                    if (target) target.style.display = 'block';
                });
            });

            document.querySelectorAll('.producto-card').forEach(card => {
                card.addEventListener('click', () => {
                    const producto = JSON.parse(card.getAttribute('data-producto'));
                    agregarAlCarrito(producto);
                    card.style.borderColor = '#198754';
                    setTimeout(() => card.style.borderColor = '', 700);
                });
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        card.click();
                    }
                });
            });

            const carritoGuardado = localStorage.getItem('carritoVentas');
            if (carritoGuardado) {
                try {
                    carrito = JSON.parse(carritoGuardado);
                    actualizarCarrito();
                } catch (e) {
                    localStorage.removeItem('carritoVentas');
                    carrito = [];
                }
            }

            modalExtrasInstance = new bootstrap.Modal(document.getElementById('modalExtras'));

            document.getElementById('btnConfirmarExtras').addEventListener('click', confirmarProductoConExtras);

            document.getElementById('form-venta').addEventListener('submit', function(e) {
                if (carrito.length === 0) {
                    e.preventDefault();
                    alert('El carrito está vacío');
                    return;
                }

                const errorStock = validarStockAntesDeVenta();
                if (errorStock) {
                    e.preventDefault();
                    alert('❌ ' + errorStock + '\n\nNo se puede completar la venta.');
                    return;
                }

                const totalText = document.getElementById('total-venta').textContent || '$0.00';
                if (!confirm(`¿Confirmar venta por ${totalText}?`)) {
                    e.preventDefault();
                }
            });
        });

        function agregarAlCarrito(producto) {
            const aceptaExtras = producto.acepta_extras == true || producto.acepta_extras === '1' || producto.acepta_extras === 1 || producto.acepta_extras === 't';
            if (aceptaExtras) {
                productoPendiente = producto;
                mostrarModalExtras(producto);
            } else {
                const item = {
                    ...producto,
                    extras: [],
                    cantidad: 1
                };
                pushProductoCarrito(item);
            }
        }

        function mostrarModalExtras(producto) {
            const modalContent = document.getElementById('modalExtrasContent');
            modalContent.innerHTML = '';

            const precioBase = Number(producto.precio_base) || 0;
            let html = `
            <div class="alert alert-primary mb-3 py-2">
                <h6 class="mb-0 fw-bold">${escapeHtml(producto.nombre_producto)}</h6>
                <small>Precio base: <strong>$${precioBase.toFixed(2)}</strong></small>
            </div>
            <h6 class="mb-2 text-primary"><i class="bi bi-plus-circle me-1"></i> Extras Opcionales:</h6>
            <div class="row g-2">`;

            ingredientes.forEach(ing => {
                const id = Number(ing.id_ingrediente);
                const precio = Number(ing.precio_extra) || 0;
                const stock = Number(ing.stock) || 0;
                const sinStock = stock < 1;
                const disabledAttr = sinStock ? 'disabled' : '';
                const stockText = sinStock ? ' (SIN STOCK)' : ` (Disp: ${stock})`;
                const textClass = sinStock ? 'text-danger' : 'text-success';

                html += `
                <div class="col-12">
                    <div class="form-check form-check-inline form-check-reverse d-flex justify-content-between align-items-center bg-light p-2 rounded">
                        <div>
                            <input class="form-check-input extra-checkbox" type="checkbox"
                                value="${id}"
                                data-precio="${precio}"
                                id="extra-${id}"
                                ${disabledAttr}>
                            <label class="form-check-label ${sinStock ? 'text-muted' : 'fw-bold'}" for="extra-${id}">
                                ${escapeHtml(ing.nombre_ingrediente)}
                            </label>
                            <small class="${textClass} d-block" style="font-size: .75rem;">${stockText}</small>
                        </div>
                        <span class="badge bg-secondary-subtle text-dark ms-2 fw-bold">
                            +$${precio.toFixed(2)}
                        </span>
                    </div>
                </div>
            `;
            });
            html += `</div>`;

            modalContent.innerHTML = html;
            modalExtrasInstance.show();
        }

        function confirmarProductoConExtras() {
            if (!productoPendiente) return;

            const extrasSeleccionados = Array.from(document.querySelectorAll('.extra-checkbox:checked'))
                .map(cb => Number(cb.value));

            for (const idIngrediente of extrasSeleccionados) {
                const ingrediente = ingredientes.find(ing => Number(ing.id_ingrediente) === idIngrediente);
                if (ingrediente && (Number(ingrediente.stock) || 0) < 1) {
                    alert(`❌ No hay stock disponible para: ${ingrediente.nombre_ingrediente}`);
                    return;
                }
            }

            const item = {
                ...productoPendiente,
                extras: extrasSeleccionados,
                cantidad: 1
            };
            pushProductoCarrito(item);
            modalExtrasInstance.hide();
            productoPendiente = null;
        }

        function pushProductoCarrito(item) {
            const buscarExtrasKey = (arr) => JSON.stringify((arr || []).slice().sort((a, b) => a - b));
            const key = buscarExtrasKey(item.extras);
            const indexExistente = carrito.findIndex(c =>
                c.id_producto === item.id_producto &&
                buscarExtrasKey(c.extras) === key
            );

            if (indexExistente !== -1) {
                carrito[indexExistente].cantidad = Number(carrito[indexExistente].cantidad) + Number(item.cantidad);
            } else {
                carrito.push(item);
            }
            actualizarCarrito();
        }

        function actualizarCarrito() {
            const carritoItems = document.getElementById('carrito-items');
            const carritoVacio = document.getElementById('carrito-vacio');
            const subtotalElement = document.getElementById('subtotal');
            const totalElement = document.getElementById('total-venta');
            const btnRegistrar = document.getElementById('btn-registrar-venta');

            if (carrito.length === 0) {
                carritoItems.style.display = 'none';
                carritoVacio.style.display = 'block';
                carritoItems.innerHTML = '';
                subtotalElement.textContent = `$0.00`;
                totalElement.textContent = `$0.00`;
                btnRegistrar.disabled = true;
            } else {
                carritoVacio.style.display = 'none';
                carritoItems.style.display = 'block';
                let total = 0; // Usaré 'total' directamente en el loop para reflejar el subtotal + extras
                let html = '';

                carrito.forEach((item, index) => {
                    const precioBase = Number(item.precio_base) || 0;
                    const precioExtras = (item.extras || []).reduce((sum, idExtra) => {
                        const ingrediente = ingredientes.find(i => Number(i.id_ingrediente) === Number(idExtra));
                        return sum + (ingrediente ? Number(ingrediente.precio_extra) : 0);
                    }, 0);

                    const precioUnitario = precioBase + precioExtras;
                    const subtotalItem = precioUnitario * Number(item.cantidad);
                    total += subtotalItem;

                    const nombresExtras = (item.extras || []).map(id => {
                        const ing = ingredientes.find(i => Number(i.id_ingrediente) === Number(id));
                        return ing ? ing.nombre_ingrediente : '';
                    }).filter(Boolean).join(', ');

                    const itemClass = index % 2 === 0 ? 'bg-light' : 'bg-white';

                    html += `
                    <div class="carrito-item px-1 py-2 ${itemClass}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1 me-2" style="font-size: .95rem;">
                                <strong class="d-block text-primary">${Number(item.cantidad)}x ${escapeHtml(item.nombre_producto)}</strong>
                                ${nombresExtras ? `<small class="text-muted d-block ms-3"><i class="bi bi-plus-circle me-1"></i> ${escapeHtml(nombresExtras)}</small>` : ''}
                                <small class="text-secondary d-block mt-1">$${precioUnitario.toFixed(2)} c/u</small>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold d-block text-dark mb-2">$${subtotalItem.toFixed(2)}</span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Cantidad">
                                    <button type="button" class="btn btn-outline-danger py-0" onclick="modificarCantidad(${index}, -1)">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <span class="btn btn-light py-0 px-2 fw-bold">${Number(item.cantidad)}</span>
                                    <button type="button" class="btn btn-outline-success py-0" onclick="modificarCantidad(${index}, 1)">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                });

                carritoItems.innerHTML = html;
                subtotalElement.textContent = `$${total.toFixed(2)}`;
                totalElement.textContent = `$${total.toFixed(2)}`;
                btnRegistrar.disabled = false;
            }

            localStorage.setItem('carritoVentas', JSON.stringify(carrito));
            document.getElementById('productos-data').value = JSON.stringify(carrito);
        }

        function modificarCantidad(index, cambio) {
            if (!carrito[index]) return;
            carrito[index].cantidad = Number(carrito[index].cantidad) + Number(cambio);
            if (carrito[index].cantidad < 1) {
                eliminarDelCarrito(index);
            } else {
                actualizarCarrito();
            }
        }

        function eliminarDelCarrito(index) {
            if (index < 0 || index >= carrito.length) return;
            carrito.splice(index, 1);
            actualizarCarrito();
        }

        function validarStockAntesDeVenta() {
            let ingredientesRequeridos = {};

            carrito.forEach(item => {
                if (item.extras && item.extras.length > 0) {
                    item.extras.forEach(idIngrediente => {
                        const cantidad = Number(item.cantidad) || 1;
                        if (!ingredientesRequeridos[idIngrediente]) {
                            ingredientesRequeridos[idIngrediente] = 0;
                        }
                        ingredientesRequeridos[idIngrediente] += cantidad;
                    });
                }
            });

            for (const [idIngrediente, cantidadRequerida] of Object.entries(ingredientesRequeridos)) {
                const ingrediente = ingredientes.find(ing => Number(ing.id_ingrediente) === Number(idIngrediente));
                if (!ingrediente) {
                    return `Error: Ingrediente ID ${idIngrediente} no encontrado`;
                }

                const stockDisponible = Number(ingrediente.stock) || 0;
                if (stockDisponible < cantidadRequerida) {
                    return `Stock insuficiente para: ${ingrediente.nombre_ingrediente}. 
                       Requeridos: ${cantidadRequerida}, Disponibles: ${stockDisponible}`;
                }
            }

            return null; 
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return String(unsafe)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }
    </script>

    <?php if ($mensaje): ?>
        <script>
            alert('<?php echo addslashes($mensaje); ?>');
            setTimeout(() => window.location.href = 'ventas.php', 100);
        </script>
    <?php endif; ?>

    <?php if ($error): ?>
        <script>
            alert('<?php echo addslashes($error); ?>');
        </script>
    <?php endif; ?>
</body>

</html>
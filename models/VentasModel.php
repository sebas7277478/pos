<?php
class VentasModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }
    public function getProducto($idProducto)
    {
        $sql = "SELECT * FROM productos WHERE id = ?";
        return $this->select($sql, [$idProducto]);
    }
    public function getProductoGanancia($id)
    {
        $sql = "SELECT precio_compra FROM productos WHERE id = ?";
        return $this->select($sql, [$id]);
    }
    public function registrarVenta($productos, $total, $fecha, $hora, $metodo, $descuento, $serie, $pago, $idCliente, $idusuario)
    {
        $sql = "INSERT INTO ventas (productos, total, fecha, hora, metodo, descuento, serie, pago, id_cliente, id_usuario) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $array = array($productos, $total, $fecha, $hora, $metodo, $descuento, $serie, $pago, $idCliente, $idusuario);
        return $this->insertar($sql, $array);
    }
    public function actualizarStock($cantidad, $ventas, $idProducto)
    {
        $sql = "UPDATE productos SET cantidad = ?, ventas=? WHERE id = ?";
        $array = array($cantidad, $ventas, $idProducto);
        return $this->save($sql, $array);
    }
    public function registrarCredito($monto, $fecha, $hora, $idVenta)
    {
        $sql = "INSERT INTO creditos (monto, fecha, hora, id_venta) VALUES (?,?,?,?)";
        $array = array($monto, $fecha, $hora, $idVenta);
        return $this->insertar($sql, $array);
    }
    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }

    public function getVenta($idVenta)
    {
        $sql = "SELECT v.*, c.identidad, c.num_identidad, c.nombre, c.telefono, c.correo, c.direccion FROM ventas v INNER JOIN clientes c ON v.id_cliente = c.id WHERE v.id = ?";
        return $this->select($sql, [$idVenta]);
    }

    public function listarVentasServerSide($limit, $offset)
    {
        $limit  = (int) $limit;
        $offset = (int) $offset;

        // SQL calcula la ganancia de una vez por cada venta
        $sql = "SELECT
                v.id,
                v.fecha,
                v.hora,
                v.total,
                c.nombre,
                v.serie,
                v.metodo,
                v.estado,
                (SELECT SUM(ganancia) FROM venta_detalle WHERE id_venta = v.id) as ganancia_total
            FROM ventas v
            INNER JOIN clientes c ON c.id = v.id_cliente
            ORDER BY v.id DESC
            LIMIT $limit OFFSET $offset";

        return $this->selectAll($sql);
    }


    public function totalVentas()
    {
        return $this->select("SELECT COUNT(*) total FROM ventas")['total'];
    }

    ////////////////////////////

    public function anular($idVenta)
    {
        $sql = "UPDATE ventas SET estado = ? WHERE id = ?";
        $array = array(0, $idVenta);
        return $this->save($sql, $array);
    }
    public function anularCredito($idVenta)
    {
        $sql = "UPDATE creditos SET estado = ? WHERE id_venta = ?";
        $array = array(2, $idVenta);
        return $this->save($sql, $array);
    }

    public function getSerie()
    {
        $sql = "SELECT MAX(id) AS total FROM ventas";
        return $this->select($sql);
    }

    public function getDetalleVenta($idVenta)
    {
        $sql = "SELECT 
                vd.cantidad,
                vd.precio_venta,
                p.descripcion
            FROM venta_detalle vd
            INNER JOIN productos p ON p.id = vd.id_producto
            WHERE vd.id_venta = ?";

        return $this->selectAll($sql, [$idVenta]);
    }

    //movimiento
    public function registrarMovimiento($movimiento, $accion, $cantidad, $stockActual, $idProducto, $id_usuario)
    {
        $sql = "INSERT INTO inventario (movimiento, accion, cantidad, stock_actual, id_producto, id_usuario) VALUES (?,?,?,?,?,?)";
        $array = array($movimiento, $accion, $cantidad, $stockActual, $idProducto, $id_usuario);
        return $this->insertar($sql, $array);
    }

    public function getCaja($id_usuario)
    {
        $sql = "SELECT * FROM cajas WHERE estado = 1 AND id_usuario = $id_usuario";
        return $this->select($sql);
    }

    public function procesarVentaEfectiva($datosVenta, $productos, $idUsuario)
    {
        try {
            $this->con->beginTransaction();

            // 1. Insertar Cabecera de Venta
            $sqlVenta = "INSERT INTO ventas (total, metodo, serie, descuento, pago, id_cliente, id_usuario, fecha, hora) VALUES (?,?,?,?,?,?,?,?,?)";
            $idVenta = $this->insertar($sqlVenta, [
                $datosVenta['total'],
                $datosVenta['metodo'],
                $datosVenta['serie'],
                $datosVenta['descuento'],
                $datosVenta['pago'],
                $datosVenta['idCliente'],
                $idUsuario,
                date('Y-m-d'),
                date('H:i:s')
            ]);

            if ($idVenta <= 0) {
                throw new Exception("Error al registrar la venta.");
            }

            // 2. Insertar Detalles y Actualizar Stock en bloque
            foreach ($productos as $p) {
                // Obtenemos precio compra y stock actual en UNA sola consulta SQL
                $stmt = $this->con->prepare(
                    "SELECT precio_compra, cantidad 
                            FROM productos 
                            WHERE id = ? 
                            FOR UPDATE"
                );
                $stmt->execute([$p['id']]);
                $prodInfo = $stmt->fetch(PDO::FETCH_ASSOC);

                if (empty($prodInfo) || $prodInfo['cantidad'] < $p['cantidad']) {
                    throw new Exception("Stock insuficiente o producto no encontrado para ID " . $p['id']);
                }

                $ganancia = ($p['precio'] - $prodInfo['precio_compra']) * $p['cantidad'];

                // Insertar detalle de venta
                $sqlDetalle = "INSERT INTO venta_detalle (id_venta, id_producto, cantidad, precio_venta, precio_compra, ganancia) VALUES (?,?,?,?,?,?)";
                $this->insertar($sqlDetalle, [$idVenta, $p['id'], $p['cantidad'], $p['precio'], $prodInfo['precio_compra'], $ganancia]);

                // Actualizar stock y ventas en una sola consulta SQL
                $nuevoStock = $prodInfo['cantidad'] - $p['cantidad'];
                $this->save("UPDATE productos SET cantidad = cantidad - ?, ventas = ventas + ? WHERE id = ?", [$p['cantidad'], $p['cantidad'], $p['id']]);

                // Registrar movimiento de inventario
                $movimiento = 'Venta N°: ' . $idVenta;
                $this->registrarMovimiento($movimiento, 'salida', $p['cantidad'], $nuevoStock, $p['id'], $idUsuario);
            }

            // 3. Si es crédito, registrar (simplificado)
            if ($datosVenta['metodo'] == 'CREDITO') {
                $montoCredito = $datosVenta['total'] - $datosVenta['descuento'];
                $sqlCredito = "INSERT INTO creditos (monto, fecha, hora, id_venta) VALUES (?,?,?,?)";
                $this->insertar($sqlCredito, [$montoCredito, date('Y-m-d'), date('H:i:s'), $idVenta]);
            }

            $this->con->commit();
            return $idVenta;
        } catch (Exception $e) {
            $this->con->rollBack();
            // log_error($e->getMessage()); // Usar un logger real
            return false;
        }
    }

    public function getPrecioCompra($idProducto)
    {
        return $this->select(
            "SELECT precio_compra FROM productos WHERE id = ?",
            [$idProducto]
        )['precio_compra'];
    }

    public function getGananciaVenta($idVenta)
    {
        return $this->select(
            "SELECT SUM(ganancia) total FROM venta_detalle WHERE id_venta = ?",
            [$idVenta]
        )['total'] ?? 0;
    }

    public function anularVentaCompleta($idVenta, $idUsuario)
    {

        try {

            $this->con->beginTransaction();

            // 0. Obtener venta y productos
            $venta = $this->getVenta($idVenta);
            if (empty($venta))
                throw new Exception("Venta no encontrada: " . $idVenta);

            // 1. Anular la venta (estado = 0)
            $sqlAnularVenta = "UPDATE ventas SET estado = 0 WHERE id = ?";
            $this->save($sqlAnularVenta, [$idVenta]);

            // 2. Obtener el crédito asociado a la venta
            $sqlCredito = "SELECT id FROM creditos WHERE id_venta = ?";
            $credito = $this->select($sqlCredito, [$idVenta]);

            if ($credito) {
                $idCredito = $credito['id'];

                // 3. Eliminar abonos relacionados al crédito
                $sqlEliminarAbonos = "DELETE FROM abonos WHERE id_credito = ?";
                $this->save($sqlEliminarAbonos, [$idCredito]);

                // 4. Anular el crédito (cambiar estado a 2)
                $sqlAnularCredito = "UPDATE creditos SET estado = 2 WHERE id = ?";
                $this->save($sqlAnularCredito, [$idCredito]);
            }

            // 5. Restaurar stock y registrar movimiento de entrada
            $productos = $this->getDetalleVentaStock($idVenta);

            foreach ($productos as $prod) {

                $prodActual = $this->getProducto($prod['id_producto']);
                if (!$prodActual) continue;

                $nuevaCantidad = $prodActual['cantidad'] + $prod['cantidad'];
                $nuevasVentas  = max(0, $prodActual['ventas'] - $prod['cantidad']);

                $this->actualizarStock($nuevaCantidad, $nuevasVentas, $prod['id_producto']);

                $movimiento = 'Anulación Venta N°: ' . $idVenta;
                $this->registrarMovimiento(
                    $movimiento,
                    'entrada',
                    $prod['cantidad'],
                    $nuevaCantidad,
                    $prod['id_producto'],
                    $idUsuario
                );
            }

            // Confirmar cambios
            $this->con->commit();

            return true;
        } catch (Exception $e) {
            // Si hay error, revertir todo
            $this->con->rollBack();
            error_log("Error al anular venta completa: " . $e->getMessage());
            return false;
        }
    }

    public function getVentasSinDetalle($limit = 50)
    {
        $sql = "SELECT id, productos
            FROM ventas
            WHERE id NOT IN (
                SELECT DISTINCT id_venta FROM venta_detalle
            )
            LIMIT $limit";

        return $this->selectAll($sql);
    }

    public function getGananciaDiaria()
    {
        $sql = "SELECT 
                v.fecha,
                SUM(vd.ganancia) AS total
            FROM venta_detalle vd
            INNER JOIN ventas v ON v.id = vd.id_venta
            WHERE v.estado = 1
            GROUP BY v.fecha
            ORDER BY v.fecha DESC";

        return $this->selectAll($sql);
    }

    public function getGananciaMensual()
    {
        $sql = "SELECT 
                DATE_FORMAT(v.fecha, '%Y-%m') AS mes,
                SUM(vd.ganancia) AS total
            FROM venta_detalle vd
            INNER JOIN ventas v ON v.id = vd.id_venta
            WHERE v.estado = 1
            GROUP BY mes
            ORDER BY mes DESC";

        return $this->selectAll($sql);
    }

    public function getGananciaHoy()
    {
        $sql = "SELECT 
                IFNULL(SUM(vd.ganancia),0) AS total
            FROM venta_detalle vd
            INNER JOIN ventas v ON v.id = vd.id_venta
            WHERE v.estado = 1
            AND v.fecha = CURDATE()";

        return $this->select($sql);
    }

    public function gananciaPorFechas(string $desde, string $hasta)
    {
        $sql = "SELECT 
                SUM(dv.cantidad * (p.precio_venta - p.precio_compra)) AS ganancia
            FROM ventas v
            INNER JOIN venta_detalle dv ON v.id = dv.id_venta
            INNER JOIN productos p ON dv.id_producto = p.id
            WHERE v.fecha BETWEEN ? AND ?
              AND v.estado = 1";

        return $this->select($sql, [$desde, $hasta]);
    }

    public function gananciaPorMes(int $anio, int $mes)
    {
        $sql = "SELECT 
                SUM(dv.cantidad * (p.precio_venta - p.precio_compra)) AS ganancia
            FROM ventas v
            INNER JOIN venta_detalle dv ON v.id = dv.id_venta
            INNER JOIN productos p ON dv.id_producto = p.id
            WHERE YEAR(v.fecha) = ?
              AND MONTH(v.fecha) = ?
              AND v.estado = 1";

        return $this->select($sql, [$anio, $mes]);
    }

    public function listarPorFechas(string $desde, string $hasta)
    {
        $sql = "SELECT 
                v.id,
                v.fecha,
                v.hora,
                v.total,
                v.serie,
                v.metodo,
                v.estado,
                CONCAT(c.nombre) AS cliente,
                IFNULL(SUM(dv.cantidad * (p.precio_venta - p.precio_compra)), 0) AS ganancia
            FROM ventas v
            INNER JOIN clientes c ON v.id_cliente = c.id
            INNER JOIN venta_detalle dv ON v.id = dv.id_venta
            INNER JOIN productos p ON dv.id_producto = p.id
            WHERE v.fecha BETWEEN ? AND ? AND v.estado = 1
            GROUP BY v.id
            ORDER BY v.fecha DESC";

        return $this->selectAll($sql, [$desde, $hasta]);
    }

    public function getDetalleVentaStock($idVenta)
    {
        $sql = "SELECT id_producto, cantidad
            FROM venta_detalle
            WHERE id_venta = ?";
        return $this->selectAll($sql, [$idVenta]);
    }
}

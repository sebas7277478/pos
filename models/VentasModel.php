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
        $sql = "SELECT v.*, c.identidad, c.num_identidad, c.nombre, c.telefono, c.correo, c.direccion FROM ventas v INNER JOIN clientes c ON v.id_cliente = c.id WHERE v.id = $idVenta";
        return $this->select($sql);
    }

    public function getVentas()
    {
        $sql = "SELECT v.*, c.nombre FROM ventas v INNER JOIN clientes c ON v.id_cliente = c.id ORDER BY v.id DESC";
        return $this->selectAll($sql);
    }

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
    public function registrarVentaCompleta($productos, $total, $fecha, $hora, $metodo, $descuento, $serie, $pago, $idCliente, $idUsuario)
    {
        try {

            // 0. Validar stock antes de iniciar transacción (evita abrir y revertir innecesariamente)
            foreach ($productos as $producto) {
                $result = $this->getProducto($producto['id']);
                if (!$result) {
                    throw new Exception("Producto no encontrado: ID " . $producto['id']);
                }
                if ($result['cantidad'] < $producto['cantidad']) {
                    throw new Exception("Stock insuficiente para: " . $producto['nombre']);
                }
            }

            $this->con->beginTransaction();

            // 1. Registrar la venta
            $jsonProductos = json_encode($productos);
            $idVenta = $this->registrarVenta($jsonProductos, $total, $fecha, $hora, $metodo, $descuento, $serie, $pago, $idCliente, $idUsuario);
            if ($idVenta <= 0) {
                throw new Exception("Error al registrar la venta.");
            }

            // 2. Actualizar stock de cada producto
            foreach ($productos as $producto) {
                $result = $this->getProducto($producto['id']);
                // volver a comprobar estado actual por seguridad
                if (!$result)
                    throw new Exception("Producto no encontrado: ID " . $producto['id']);


                $nuevaCantidad = (int) $result['cantidad'] - (int) $producto['cantidad'];
                if ($nuevaCantidad < 0)
                    throw new Exception("Stock insuficiente para: " . $producto['nombre']);


                $totalVentas = (int) $result['ventas'] + (int) $producto['cantidad'];
                $rows = $this->actualizarStock($nuevaCantidad, $totalVentas, $producto['id']);
                if ($rows === false)
                    throw new Exception("Error al actualizar stock para ID " . $producto['id']);


                // 3. Registrar movimiento
                $movimiento = 'Venta N°: ' . $idVenta;
                $movRows = $this->registrarMovimiento($movimiento, 'salida', $producto['cantidad'], $nuevaCantidad, $producto['id'], $idUsuario);
                if ($movRows === false)
                    throw new Exception("Error al registrar movimiento para ID " . $producto['id']);
            }

            // 4. Si es crédito, registrar
            if ($metodo == 'CREDITO') {
                $montoCredito = $total - $descuento;
                $cre = $this->registrarCredito($montoCredito, $fecha, $hora, $idVenta);
                if ($cre === false)
                    throw new Exception("Error al registrar credito para venta " . $idVenta);
            }

            $this->con->commit();
            return $idVenta;
        } catch (Exception $e) {
            $this->con->rollBack();
            return 0;
        }
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
            $productos = json_decode($venta['productos'], true);
            if (is_array($productos)) {
                foreach ($productos as $prod) {
                    $prodActual = $this->getProducto($prod['id']);
                    if (!$prodActual) {
                        // Si no existe el registro de producto, no abortamos todo el proceso, pero logueamos
                        error_log("[anularVentaCompleta] producto no encontrado al restaurar stock ID: " . $prod['id']);
                        continue;
                    }


                    $nuevaCantidad = (int) $prodActual['cantidad'] + (int) $prod['cantidad'];
                    $nuevasVentas = max(0, (int) $prodActual['ventas'] - (int) $prod['cantidad']);


                    $this->actualizarStock($nuevaCantidad, $nuevasVentas, $prod['id']);


                    $movimiento = 'Anulación Venta N°: ' . $idVenta;
                    $this->registrarMovimiento($movimiento, 'entrada', $prod['cantidad'], $nuevaCantidad, $prod['id'], $idUsuario);
                }
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


}


?>
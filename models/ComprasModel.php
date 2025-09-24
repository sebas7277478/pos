<?php
class ComprasModel extends Query
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
    public function registrarCompra($productos, $total, $fecha, $hora, $metodo, $serie, $idproveedor, $idusuario)
    {
        $sql = "INSERT INTO compras (productos, total, fecha, hora, metodo, serie, id_proveedor, id_usuario) VALUES (?,?,?,?,?,?,?,?)";
        $array = array($productos, $total, $fecha, $hora, $metodo, $serie, $idproveedor, $idusuario);
        return $this->insertar($sql, $array);
    }
    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }
    public function getCompra($idCompra)
    {
        $sql = "SELECT c.*, p.ruc, p.nombre, p.telefono, p.direccion FROM compras c INNER JOIN proveedor p ON c.id_proveedor = p.id WHERE c.id = ?";
        return $this->select($sql, [$idCompra]);
    }
    //actualizar stock}
    public function actualizarStock($cantidad, $idProducto)
    {
        $sql = "UPDATE productos SET cantidad = ? WHERE id = ?";
        $array = array($cantidad, $idProducto);
        return $this->save($sql, $array);
    }

    public function registrarCredito($monto, $fecha, $hora, $idproveedor, $id_usuario, $id_compra)
    {
        $sql = "INSERT INTO creditos_compras (monto, fecha, hora, idproveedor, id_usuario, id_compra) VALUES (?,?,?,?,?,?)";
        $array = array($monto, $fecha, $hora, $idproveedor, $id_usuario, $id_compra);
        return $this->insertar($sql, $array);
    }

    public function getCompras()
    {
        $sql = "SELECT c.*, p.nombre FROM compras c INNER JOIN proveedor p ON c.id_proveedor = p.id ORDER BY c.id DESC";
        return $this->selectAll($sql);
    }

    public function anular($idCompra)
    {
        $sql = "UPDATE compras SET estado = ? WHERE id = ?";
        $array = array(0, $idCompra);
        return $this->save($sql, $array);
    }
    //movimiento
    public function registrarMovimiento($movimiento, $accion, $cantidad, $stockActual, $idProducto, $id_usuario)
    {
        $sql = "INSERT INTO inventario (movimiento, accion, cantidad, stock_actual, id_producto, id_usuario) VALUES (?,?,?,?,?,?)";
        $array = array($movimiento, $accion, $cantidad, $stockActual, $idProducto, $id_usuario);
        return $this->insertar($sql, $array);
    }

    public function buscarCreditoActivo($valor)
    {
        $sql = "SELECT crc.*, pr.nombre, pr.telefono, pr.direccion FROM creditos_compras crc INNER JOIN compras c ON crc.id_compra = c.id INNER JOIN proveedor pr ON c.id_proveedor = pr.id WHERE pr.nombre LIKE '%" . $valor . "%' AND crc.estado_credito = 1 LIMIT 10";
        return $this->selectAll($sql);
    }

    public function getAbonoCredito($id)
    {
        $sql = "SELECT SUM(abono) AS total FROM abonos_compras WHERE id_credito_compra = ?";
        return $this->select($sql, [$id]);
    }

    public function registrarAbonoComprasSeguro($monto, $fecha, $idCreditoCompra, $id_usuario)
    {
        try {
            $this->con->beginTransaction();


            $sql = "INSERT INTO abonos_compras(abono, fecha, id_credito_compra, id_usuario) VALUES (?,?,?,?)";
            $this->insertar($sql, [$monto, $fecha, $idCreditoCompra, $id_usuario]);


            // verificar si saldo se completó
            $credito = $this->getCredito($idCreditoCompra);
            $resultAbono = $this->getAbonoCredito($idCreditoCompra);
            $abonado = ($resultAbono['total'] == null) ? 0 : $resultAbono['total'];
            $restante = $credito['monto'] - $abonado;


            if ($restante < 0.1 && $credito['estado_credito'] == 1) {
                $this->actualizarCreditoCompras(0, $idCreditoCompra);
            }


            $this->con->commit();
            return true;
        } catch (Exception $e) {
            $this->con->rollBack();
            error_log("[registrarAbonoComprasSeguro] " . $e->getMessage());
            return false;
        }
    }

    public function getCredito($idCredito)
    {
        $sql = "SELECT * FROM creditos_compras WHERE id = ?";
        return $this->select($sql, [$idCredito]);
    }
    public function actualizarCreditoCompras($estado, $idCreditoCompra)
    {
        $sql = "UPDATE creditos_compras SET estado_credito = ? WHERE id = ?";
        $array = array($estado, $idCreditoCompra);
        return $this->save($sql, $array);
    }
    // --- Registrar compra completa con transacción ---
    public function registrarCompraCompleta($productos, $total, $fecha, $hora, $metodo, $serie, $idproveedor, $idusuario)
    {
        try {
            $this->con->beginTransaction();


            // 1. Registrar compra
            $jsonProductos = json_encode($productos, JSON_UNESCAPED_UNICODE);
            $idCompra = $this->registrarCompra($jsonProductos, $total, $fecha, $hora, $metodo, $serie, $idproveedor, $idusuario);
            if (!is_numeric($idCompra) || $idCompra <= 0)
                throw new Exception('Error al registrar compra');


            // 2. Actualizar stock y registrar movimientos
            foreach ($productos as $producto) {
                $prod = $this->getProducto($producto['id']);
                if (!$prod)
                    throw new Exception('Producto no encontrado: ' . $producto['id']);


                $nuevaCantidad = (int) $prod['cantidad'] + (int) $producto['cantidad'];
                $rows = $this->actualizarStock($nuevaCantidad, $producto['id']);
                if ($rows === false)
                    throw new Exception('Error al actualizar stock ID: ' . $producto['id']);


                $movimiento = 'Compra N°: ' . $idCompra . ' - ' . $metodo;
                $movRows = $this->registrarMovimiento($movimiento, 'entrada', $producto['cantidad'], $nuevaCantidad, $producto['id'], $idusuario);
                if ($movRows === false)
                    throw new Exception('Error al registrar movimiento ID: ' . $producto['id']);
            }


            // 3. Si es credito, registrar credito
            if (strtoupper($metodo) == 'CREDITO') {
                $cre = $this->registrarCredito($total, $fecha, $hora, $idproveedor, $idusuario, $idCompra);
                if ($cre === false)
                    throw new Exception('Error al registrar credito para compra');
            }


            $this->con->commit();
            return $idCompra;
        } catch (Exception $e) {
            $this->con->rollBack();
            error_log('[registrarCompraCompleta] ' . $e->getMessage());
            return 0;
        }
    }
    // --- Anular compra completa: devolver stock y registrar movimientos dentro de transacción ---
    public function anularCompraCompleta($idCompra, $idUsuario)
    {
        try {
            $this->con->beginTransaction();


            // Obtener compra
            $compra = $this->getCompra($idCompra);
            if (empty($compra))
                throw new Exception('Compra no encontrada: ' . $idCompra);


            // Anular compra
            $this->anular($idCompra);


            // Restaurar stock y registrar movimientos
            $productos = json_decode($compra['productos'], true);
            if (is_array($productos)) {
                foreach ($productos as $prod) {
                    $prodActual = $this->getProducto($prod['id']);
                    if (!$prodActual) {
                        error_log('[anularCompraCompleta] producto no encontrado al restaurar stock ID: ' . $prod['id']);
                        continue;
                    }
                    $nuevaCantidad = max(0, (int) $prodActual['cantidad'] - (int) $prod['cantidad']);
                    $this->actualizarStock($nuevaCantidad, $prod['id']);
                    $movimiento = 'Devolución Compra N°: ' . $idCompra;
                    $this->registrarMovimiento($movimiento, 'salida', $prod['cantidad'], $nuevaCantidad, $prod['id'], $idUsuario);
                }
            }


            $this->con->commit();
            return true;
        } catch (Exception $e) {
            $this->con->rollBack();
            error_log('[anularCompraCompleta] ' . $e->getMessage());
            return false;
        }
    }
}
?>
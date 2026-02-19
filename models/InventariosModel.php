<?php
class InventariosModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Devuelve todos los movimientos del usuario
     */
    public function getMovimientos($id_usuario)
    {
        $id = intval($id_usuario);
        $sql = "SELECT i.*, p.descripcion 
                FROM inventario i 
                INNER JOIN productos p ON i.id_producto = p.id 
                WHERE i.id_usuario = $id
                ORDER BY i.fecha DESC";
        return $this->selectAll($sql);
    }

    /**
     * Devuelve movimientos filtrando por mes y año.
     * IMPORTANTE: casteamos $anio y $mes a int para prevenir inyección.
     */
    public function getMovimientosMes($anio, $mes, $id_usuario)
    {
        $a = intval($anio);
        $m = intval($mes);
        $id = intval($id_usuario);

        $sql = "SELECT i.*, p.descripcion 
                FROM inventario i 
                INNER JOIN productos p ON i.id_producto = p.id 
                WHERE MONTH(i.fecha) = $m 
                  AND YEAR(i.fecha) = $a 
                  AND i.id_usuario = $id
                ORDER BY i.fecha DESC";
        return $this->selectAll($sql);
    }

    /**
     * Producto (single row) — usamos prepared statement
     */
    public function getProducto($idProducto)
    {
        $sql = "SELECT * FROM productos WHERE id = ?";
        return $this->select($sql, [intval($idProducto)]);
    }

    /**
     * Actualiza la cantidad del producto
     */
    public function procesarAjusteConMovimiento($cantidadNueva, $idProducto, $movimiento, $accion, $cantidad, $id_usuario)
    {
        try {
            $this->con->beginTransaction();

            // Actualizar producto
            $sqlProd = "UPDATE productos SET cantidad = ? WHERE id = ?";
            $this->save($sqlProd, [$cantidadNueva, $idProducto]);

            // Registrar movimiento
            $sqlMov = "INSERT INTO inventario (movimiento, accion, cantidad, stock_actual, id_producto, id_usuario) 
                       VALUES (?,?,?,?,?,?)";
            $this->insertar($sqlMov, [$movimiento, $accion, $cantidad, $cantidadNueva, $idProducto, $id_usuario]);

            $this->con->commit();
            return true;

        } catch (Exception $e) {
            $this->con->rollBack();
            error_log("Error en procesarAjusteConMovimiento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra movimiento en tabla inventario
     */
    public function registrarMovimiento($movimiento, $accion, $cantidad, $stockActual, $idProducto, $id_usuario)
    {
        $sql = "INSERT INTO inventario (movimiento, accion, cantidad, stock_actual, id_producto, id_usuario) VALUES (?,?,?,?,?,?)";
        $array = [$movimiento, $accion, $cantidad, $stockActual, intval($idProducto), intval($id_usuario)];
        return $this->insertar($sql, $array);
    }

    /**
     * Kardex de un producto para un usuario
     */
    public function getKardex($idProducto, $id_usuario)
    {
        $idP = intval($idProducto);
        $idU = intval($id_usuario);
        $sql = "SELECT i.accion, i.cantidad, i.stock_actual, i.fecha, p.descripcion 
                FROM inventario i 
                INNER JOIN productos p ON i.id_producto = p.id 
                WHERE i.id_producto = $idP 
                  AND i.id_usuario = $idU
                ORDER BY i.fecha ASC";
        return $this->selectAll($sql);
    }

    /**
     * Configuración empresa
     */
    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion LIMIT 1";
        return $this->select($sql);
    }
}

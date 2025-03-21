<?php
class ComprasModel extends Query{
    public function __construct() {
        parent::__construct();
    }
    public function getProducto($idProducto)
    {
        $sql = "SELECT * FROM productos WHERE id = $idProducto";
        return $this->select($sql);
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
        $sql = "SELECT c.*, p.ruc, p.nombre, p.telefono, p.direccion FROM compras c INNER JOIN proveedor p ON c.id_proveedor = p.id WHERE c.id = $idCompra";
        return $this->select($sql);
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
        $sql = "SELECT c.*, p.nombre FROM compras c INNER JOIN proveedor p ON c.id_proveedor = p.id ORDER BY id DESC";
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
        $sql = "SELECT crc.*, pr.nombre, pr.telefono, pr.direccion FROM creditos_compras crc INNER JOIN compras c ON crc.id_compra = c.id INNER JOIN proveedor pr ON c.id_proveedor = pr.id WHERE pr.nombre LIKE '%".$valor."%' AND crc.estado_credito = 1 LIMIT 10";
        return $this->selectAll($sql);
    }

    public function getAbonoCredito($id)
    {
        $sql = "SELECT SUM(abono) AS total FROM abonos_compras WHERE id_credito_compra = $id";
        return $this->select($sql);
    }

    public function registrarAbonoCompras($monto, $fecha, $idCreditoCompra, $id_usuario)
    {
        $sql = "INSERT INTO abonos_compras(abono, fecha, id_credito_compra, id_usuario) VALUES (?,?,?,?)";
        $array = array($monto, $fecha, $idCreditoCompra, $id_usuario);
        return $this->insertar($sql, $array);
    }

    public function getCredito($idCredito)
    {
        $sql = "SELECT * FROM creditos_compras WHERE id = $idCredito";
        return $this->select($sql);
    }
    public function actualizarCreditoCompras($estado, $idCreditoCompra)
    {
        $sql = "UPDATE creditos_compras SET estado_credito = ? WHERE id = ?";
        $array = array($estado, $idCreditoCompra);
        return $this->save($sql, $array);
    }
}
?>
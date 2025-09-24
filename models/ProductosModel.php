<?php
class ProductosModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }
    public function getProductos($estado)
    {
        $sql = "SELECT p.*, m.medida, c.categoria FROM productos p INNER JOIN medidas m ON p.id_medida = m.id INNER JOIN categorias c ON p.id_categoria = c.id WHERE p.estado = ?";
        return $this->selectAll($sql, [$estado]);
    }

    public function getDatos($table)
    {
        $sql = "SELECT * FROM $table WHERE estado = 1";
        return $this->selectAll($sql);
    }

    public function registrar($codigo, $nombre, $precio_compra, $precio_venta, $vencimiento, $id_medida, $id_categoria, $foto)
    {
        try {
            $this->con->beginTransaction();

            $sql = "INSERT INTO productos (codigo, descripcion, precio_compra, precio_venta, vencimiento, id_medida, id_categoria, foto) 
                    VALUES (?,?,?,?,?,?,?,?)";
            $array = [$codigo, $nombre, $precio_compra, $precio_venta, $vencimiento, $id_medida, $id_categoria, $foto];
            $res = $this->insertar($sql, $array);

            $this->con->commit();
            return $res;

        } catch (Exception $e) {
            $this->con->rollBack();
            error_log("Error en registrar producto: " . $e->getMessage());
            return 0;
        }
    }

    public function getValidar($campo, $valor, $accion, $id)
    {
        if ($accion == 'registrar' && $id == 0) {
            $sql = "SELECT id FROM productos WHERE $campo = ? LIMIT 1";
            return $this->select($sql, [$valor]);
        } else {
            $sql = "SELECT id FROM productos WHERE $campo = ? AND id != ? LIMIT 1";
            return $this->select($sql, [$valor, $id]);
        }
    }

    public function eliminar($estado, $idProducto)
    {
        try {
            $this->con->beginTransaction();

            $sql = "UPDATE productos SET estado = ? WHERE id = ?";
            $res = $this->save($sql, [$estado, $idProducto]);

            $this->con->commit();
            return $res;

        } catch (Exception $e) {
            $this->con->rollBack();
            error_log("Error en eliminar/restaurar producto: " . $e->getMessage());
            return 0;
        }
    }

    public function editar($idProducto)
    {
        $sql = "SELECT * FROM productos WHERE id = ?";
        return $this->select($sql, [$idProducto]);
    }

    public function actualizar($codigo, $nombre, $precio_compra, $precio_venta, $vencimiento, $id_medida, $id_categoria, $foto, $id)
    {
        try {
            $this->con->beginTransaction();

            $sql = "UPDATE productos 
                    SET codigo=?, descripcion=?, precio_compra=?, precio_venta=?, vencimiento=?, id_medida=?, id_categoria=?, foto=? 
                    WHERE id=?";
            $array = [$codigo, $nombre, $precio_compra, $precio_venta, $vencimiento, $id_medida, $id_categoria, $foto, $id];
            $res = $this->save($sql, $array);

            $this->con->commit();
            return $res;

        } catch (Exception $e) {
            $this->con->rollBack();
            error_log("Error en actualizar producto: " . $e->getMessage());
            return 0;
        }
    }

    public function buscarPorCodigo($valor)
    {
        $sql = "SELECT id, descripcion, cantidad, precio_compra, precio_venta FROM productos WHERE codigo = '$valor' AND estado = 1";
        return $this->select($sql);
    }

    public function buscarPorNombre($valor)
    {
        $sql = "SELECT id, descripcion, cantidad, precio_compra, precio_venta
            FROM productos
            WHERE descripcion LIKE ? AND estado = 1
            LIMIT 10";
        $array = ["%$valor%"];
        return $this->selectAll($sql, $array);
    }

    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }

}
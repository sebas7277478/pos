<?php
class CreditosModel extends Query
{
    const ESTADO_COMPLETADO = 0;
    const ESTADO_PENDIENTE = 1;
    const ESTADO_ANULADO = 2;

    public function __construct()
    {
        parent::__construct();
    }

    public function getCreditos()
    {
        $sql = "SELECT cr.*, cl.nombre FROM creditos cr INNER JOIN ventas v ON cr.id_venta = v.id INNER JOIN clientes cl ON v.id_cliente = cl.id";
        return $this->selectAll($sql);
    }
    public function getTotalAbonado($idCredito)
    {
        $sql = "SELECT SUM(abono) AS total FROM abonos WHERE id_credito = ?";
        $res = $this->select($sql, [$idCredito]);
        return $res['total'] ?? 0;
    }

    public function buscarPorNombre($valor)
    {
        $sql = "SELECT cr.*, cl.id AS id_cliente, cl.nombre, cl.telefono, cl.direccion, cr.fecha, cr.monto
                FROM creditos cr
                INNER JOIN ventas v ON cr.id_venta = v.id
                INNER JOIN clientes cl ON v.id_cliente = cl.id
                WHERE cl.nombre LIKE ? AND cr.estado = ?
                LIMIT 10";
        return $this->selectAll($sql, ["%$valor%", self::ESTADO_PENDIENTE]);
    }

    public function registrarAbono($monto, $idCredito, $id_usuario)
    {
        try {
            $this->con->beginTransaction();

            // Insertamos el abono
            $sql = "INSERT INTO abonos (abono, id_credito, id_usuario) VALUES (?,?,?)";
            $this->insertar($sql, [$monto, $idCredito, $id_usuario]);

            // Verificamos deuda restante
            $sqlCredito = "SELECT monto FROM creditos WHERE id = ?";
            $credito = $this->select($sqlCredito, [$idCredito]);
            $abonado = $this->getTotalAbonado($idCredito);
            $restante = $credito['monto'] - $abonado;

            if ($restante <= 0) {
                $sqlUpdate = "UPDATE creditos SET estado = ? WHERE id = ?";
                $this->save($sqlUpdate, [self::ESTADO_COMPLETADO, $idCredito]);
            }

            $this->con->commit();
            return true;

        } catch (Exception $e) {
            $this->con->rollBack();
            return false;
        }
    }
    public function getCredito($idCredito)
    {
        $sql = "SELECT cr.*, v.productos, cl.identidad, cl.num_identidad, cl.nombre, cl.telefono, cl.direccion FROM creditos cr INNER JOIN ventas v ON cr.id_venta = v.id INNER JOIN clientes cl ON v.id_cliente = cl.id WHERE cr.id = $idCredito";
        return $this->select($sql);
    }

    public function actualizarCredito($estado, $idCredito)
    {
        $sql = "UPDATE creditos SET estado = ? WHERE id = ?";
        $array = array($estado, $idCredito);
        return $this->save($sql, $array);
    }

    public function getAbonos($idCredito)
    {
        $sql = "SELECT * FROM abonos WHERE id_credito = $idCredito";
        return $this->selectAll($sql);
    }

    public function getHistorialAbonos()
    {
        $sql = "SELECT a.*, cli.num_identidad FROM abonos a INNER JOIN creditos c ON a.id_credito = c.id INNER JOIN ventas v ON c.id_venta = v.id INNER JOIN clientes cli ON v.id_cliente = cli.id";
        return $this->selectAll($sql);
    }

    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }

    public function getCreditosActivosPorCliente($idCliente)
    {
        $sql = "SELECT cr.* 
                FROM creditos cr 
                INNER JOIN ventas v ON cr.id_venta = v.id 
                WHERE v.id_cliente = ? AND cr.estado = ?
                ORDER BY cr.id ASC";
        return $this->selectAll($sql, [$idCliente, self::ESTADO_PENDIENTE]);
    }

    public function getDeudaTotalCliente($idCliente)
    {
        $creditos = $this->getCreditosActivosPorCliente($idCliente);
        $total = 0;

        foreach ($creditos as $credito) {
            $abonado = $this->getTotalAbonado($credito['id']);
            $restante = $credito['monto'] - $abonado;
            $total += max(0, $restante);
        }

        return $total;
    }

    public function getCliente($idCliente)
    {
        $sql = "SELECT cl.* FROM clientes cl WHERE cl.id = $idCliente";
        return $this->select($sql);
    }
}

?>
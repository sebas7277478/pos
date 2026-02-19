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
        $sql = "SELECT cr.*, cl.nombre, 
                COALESCE((SELECT SUM(abono) FROM abonos WHERE id_credito = cr.id), 0) AS abonado,
                (cr.monto - COALESCE((SELECT SUM(abono) FROM abonos WHERE id_credito = cr.id), 0)) AS restante
                FROM creditos cr 
                INNER JOIN ventas v ON cr.id_venta = v.id 
                INNER JOIN clientes cl ON v.id_cliente = cl.id";
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
        $sql = "SELECT cr.*, cl.id AS id_cliente, cl.nombre, cl.telefono, cl.direccion, 
                COALESCE((SELECT SUM(abono) FROM abonos WHERE id_credito = cr.id), 0) AS abonado
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

    public function getResumenDeudaCliente($idCliente)
    {
        // Deuda restante actual (solo créditos pendientes)
        $sql = "SELECT 
            COALESCE(SUM(c.monto - COALESCE((
                SELECT SUM(a.abono) 
                FROM abonos a 
                WHERE a.id_credito = c.id
            ), 0)), 0) as deuda_restante
        FROM creditos c
        INNER JOIN ventas v ON c.id_venta = v.id
        WHERE v.id_cliente = ? 
        AND c.estado = 1";

        $data = $this->select($sql, [$idCliente]);

        // Obtener el último abono realizado
        $ultimoAbono = $this->getUltimoAbonoCliente($idCliente);

        return [
            'ultimo_abono' => $ultimoAbono,
            'deuda_restante' => $data['deuda_restante'] ?? 0
        ];
    }

    public function getUltimoAbonoCliente($idCliente)
    {
        $sql = "SELECT SUM(a.abono) as monto_abonado, MAX(a.fecha) as fecha_abono
            FROM abonos a
            INNER JOIN creditos c ON a.id_credito = c.id
            INNER JOIN ventas v ON c.id_venta = v.id
            WHERE v.id_cliente = ?
            AND a.fecha = (
                SELECT MAX(a2.fecha)
                FROM abonos a2
                INNER JOIN creditos c2 ON a2.id_credito = c2.id
                INNER JOIN ventas v2 ON c2.id_venta = v2.id
                WHERE v2.id_cliente = ?
            )";

        $res = $this->select($sql, [$idCliente, $idCliente]);
        return $res['monto_abonado'] ?? 0;
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
        $sql = "SELECT (SUM(cr.monto) - 
                COALESCE((SELECT SUM(a.abono) FROM abonos a 
                          INNER JOIN creditos c2 ON a.id_credito = c2.id 
                          INNER JOIN ventas v2 ON c2.id_venta = v2.id 
                          WHERE v2.id_cliente = ? AND c2.estado = ?), 0)) AS deuda_total
                FROM creditos cr
                INNER JOIN ventas v ON cr.id_venta = v.id
                WHERE v.id_cliente = ? AND cr.estado = ?";
        $res = $this->select($sql, [$idCliente, self::ESTADO_PENDIENTE, $idCliente, self::ESTADO_PENDIENTE]);
        return $res['deuda_total'] ?? 0;
    }

    public function getCliente($idCliente)
    {
        $sql = "SELECT cl.* FROM clientes cl WHERE cl.id = $idCliente";
        return $this->select($sql);
    }

    public function getCreditosActivosConAbonos($idCliente)
    {
        $sql = "SELECT 
                cr.id,
                cr.monto,
                cr.id_venta,
                cr.fecha,
                COALESCE((SELECT SUM(a.abono) FROM abonos a WHERE a.id_credito = cr.id), 0) AS abonado,
                (cr.monto - COALESCE((SELECT SUM(a.abono) FROM abonos a WHERE a.id_credito = cr.id), 0)) AS restante
            FROM creditos cr 
            INNER JOIN ventas v ON cr.id_venta = v.id 
            WHERE v.id_cliente = ? AND cr.estado = ?
            ORDER BY cr.id ASC";

        return $this->selectAll($sql, [$idCliente, self::ESTADO_PENDIENTE]);
    }

    public function registrarAbonosGlobalmente($abonos, $creditosCompletados, $id_usuario)
    {
        try {
            $this->con->beginTransaction();

            // Preparar statement para inserción masiva
            $sql = "INSERT INTO abonos (abono, id_credito, id_usuario, apertura) VALUES (?, ?, ?, 1)";
            $stmt = $this->con->prepare($sql);

            // Insertar todos los abonos
            foreach ($abonos as $abono) {
                $stmt->execute([
                    $abono['monto'],
                    $abono['id_credito'],
                    $id_usuario
                ]);
            }

            // Actualizar créditos completados en una sola query si hay
            if (!empty($creditosCompletados)) {
                $placeholders = implode(',', array_fill(0, count($creditosCompletados), '?'));
                $sqlUpdate = "UPDATE creditos SET estado = ? WHERE id IN ($placeholders)";

                $params = array_merge([self::ESTADO_COMPLETADO], $creditosCompletados);
                $this->save($sqlUpdate, $params);
            }

            $this->con->commit();
            return true;
        } catch (Exception $e) {
            $this->con->rollBack();
            error_log("Error en registrarAbonosGlobalmente: " . $e->getMessage());
            throw $e;
        }
    }
}

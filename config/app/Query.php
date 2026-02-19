<?php
class Query extends Conexion
{
    protected $pdo, $con;
    public function __construct()
    {
        $this->pdo = new Conexion();
        $this->con = $this->pdo->conectar();
    }
    public function select($sql, $array = [])
    {
        $result = $this->con->prepare($sql);
        $result->execute($array);
        return $result->fetch(PDO::FETCH_ASSOC);
    }
    public function selectAll($sql, $array = [])
    {
        $result = $this->con->prepare($sql);
        $result->execute($array);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    public function insertar($sql, $array)
    {
        $result = $this->con->prepare($sql);
        $data = $result->execute($array);
        if ($data) {
            $res = $this->con->lastInsertId();
        } else {
            $res = 0;
        }
        return $res;
    }
    public function save($sql, $array)
    {
        $result = $this->con->prepare($sql);
        $data = $result->execute($array);
        if ($data) {
            $res = 1;
        } else {
            $res = 0;
        }
        return $res;
    }

    public function beginTransaction()
    {
        $this->con->beginTransaction();
    }

    public function commit()
    {
        $this->con->commit();
    }

    public function rollBack()
    {
        $this->con->rollBack();
    }

}
?>
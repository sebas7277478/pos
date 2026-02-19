<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;

class Cajas extends Controller
{
    private $id_usuario;
    public function __construct()
    {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['id_usuario'])) {
            header('Location: ' . BASE_URL);
            exit;
        }
        if (!verificar('cajas')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $this->id_usuario = $_SESSION['id_usuario'];
    }
    public function index()
    {
        $data['script'] = 'cajas.js';
        $data['title'] = 'Movimientos de Caja';
        $data['caja'] = $this->model->getCaja($this->id_usuario);
        $this->views->getView('cajas', 'index', $data);
    }
    public function abrirCaja()
    {
        header('Content-Type: application/json; charset=utf-8');
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);
        if (empty($datos['monto'])) {
            http_response_code(400);
            echo json_encode(['msg' => 'EL MONTO ES REQUERIDO', 'type' => 'warning']);
            exit;
        }

        $monto = strClean($datos['monto']);
        if (!is_numeric($monto) || (float) $monto < 0) {
            http_response_code(400);
            echo json_encode(['msg' => 'MONTO INVÁLIDO', 'type' => 'error']);
            exit;
        }
        $monto = (float) $monto;

        $verificar = $this->model->getCaja($this->id_usuario);
        if (!empty($verificar)) {
            echo json_encode(['msg' => 'LA CAJA YA ESTA ABIERTA', 'type' => 'warning']);
            exit;
        }

        $fecha_apertura = date('Y-m-d');
        $data = $this->model->abrirCaja($monto, $fecha_apertura, $this->id_usuario);
        if ($data > 0) {
            echo json_encode(['msg' => 'CAJA ABIERTA', 'type' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['msg' => 'ERROR AL ABRIR LA CAJA', 'type' => 'error']);
        }
        exit;
    }

    public function listar()
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->model->getCajas();
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['accion'] = '<a href="' . BASE_URL . 'cajas/historialRepote/' . $data[$i]['id'] . '" target="_blank" class="btn btn-danger"><i class="fas fa-file-pdf"></i></a>';
        }
        echo json_encode($data);
        exit;
    }

    public function registraGasto()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_POST['monto']) || !isset($_POST['descripcion'])) {
            http_response_code(400);
            echo json_encode(['msg' => 'DATOS INCOMPLETOS', 'type' => 'warning']);
            exit;
        }

        $montoRaw = strClean($_POST['monto']);
        if (!is_numeric($montoRaw) || (float) $montoRaw <= 0) {
            http_response_code(400);
            echo json_encode(['msg' => 'MONTO INVÁLIDO', 'type' => 'warning']);
            exit;
        }
        $monto = (float) $montoRaw;

        $descripcion = strClean($_POST['descripcion']);

        $verificarMonto = $this->getDatos();
        $Ingresos = (float) ($verificarMonto['saldo'] ?? 0);
        if ($monto > $Ingresos) {
            http_response_code(400);
            echo json_encode(['msg' => 'SALDO DISPONIBLE: ' . number_format($Ingresos, 2), 'type' => 'error']);
            exit;
        }

        $destino = null;
        if (!empty($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $foto = $_FILES['foto'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if ($foto['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['msg' => 'ERROR UPLOAD: ' . $foto['error'], 'type' => 'error']);
                exit;
            }
            if ($foto['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['msg' => 'LA IMAGEN EXCEDE 2MB', 'type' => 'warning']);
                exit;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $foto['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed)) {
                http_response_code(400);
                echo json_encode(['msg' => 'TIPO DE ARCHIVO NO PERMITIDO', 'type' => 'warning']);
                exit;
            }
            $folder = __DIR__ . '/../assets/images/gastos/';
            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }
            $ext = $mime === 'image/png' ? '.png' : '.jpg';
            $fecha = date('YmdHis') . '_' . bin2hex(random_bytes(4));
            $destinoRel = 'assets/images/gastos/' . $fecha . $ext;
            $destino = $folder . $fecha . $ext;

            if (!move_uploaded_file($foto['tmp_name'], $destino)) {
                http_response_code(500);
                echo json_encode(['msg' => 'ERROR AL GUARDAR IMAGEN', 'type' => 'error']);
                exit;
            }
            $destino = $destinoRel;
        }

        $data = $this->model->registraGasto($monto, $descripcion, $destino, $this->id_usuario);
        if ($data > 0) {
            echo json_encode(['msg' => 'GASTO REGISTRADO', 'type' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['msg' => 'ERROR AL REGISTRAR GASTOS', 'type' => 'error']);
        }
        exit;
    }

    public function listarGastos()
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->model->getGastos();
        for ($i = 0; $i < count($data); $i++) {
            $foto = !empty($data[$i]['foto']) ? BASE_URL . $data[$i]['foto'] : BASE_URL . 'assets/images/no-image.png';
            $data[$i]['foto'] = '<a href="' . $foto . '" target="_blank">
                <img class="img-thumbnail" src="' . $foto . '" width="200">
                </a>';
        }
        echo json_encode($data);
        exit;
    }

    public function movimientos()
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->getDatos();
        $data['moneda'] = MONEDA;
        echo json_encode($data);
        exit;
    }

    public function getDatos()
    {
        $ventas = (float) ($this->model->getVentas('total', $this->id_usuario)['total'] ?? 0);
        $descuento = (float) ($this->model->getVentas('descuento', $this->id_usuario)['total'] ?? 0);
        $apartados = (float) ($this->model->getApartados($this->id_usuario)['total'] ?? 0);
        $creditos = (float) ($this->model->getAbonos($this->id_usuario)['total'] ?? 0);
        $creditosCompras = (float) ($this->model->getAbonosCompras($this->id_usuario)['total'] ?? 0);
        $compras = (float) ($this->model->getCompras($this->id_usuario)['total'] ?? 0);
        $gastos = (float) ($this->model->getTotalGastos($this->id_usuario)['total'] ?? 0);
        $montoInicial = (float) ($this->model->getCaja($this->id_usuario)['monto_inicial'] ?? 0);

        $egresos = $compras + $gastos + $creditosCompras;
        $ingresos = ($ventas + $apartados + $creditos) - $descuento;
        $saldo = ($ingresos + $montoInicial) - $egresos;

        return [
            'egresos' => $egresos,
            'ingresos' => $ingresos,
            'montoInicial' => $montoInicial,
            'gastos' => $gastos,
            'saldo' => $saldo,
            'egresosDecimal' => number_format($egresos, 2),
            'ingresosDecimal' => number_format($ingresos, 2),
            'inicialDecimal' => number_format($montoInicial, 2),
            'gastosDecimal' => number_format($gastos, 2),
            'saldoDecimal' => number_format($saldo, 2),
        ];
    }
    public function reporte()
    {
        $data['title'] = 'Reporte Actual';
        $data['actual'] = true;
        $data['empresa'] = $this->model->getEmpresa();
        $data['movimientos'] = $this->getDatos();

        ob_start();
        $this->views->getView('cajas', 'reporte', $data);
        $html = ob_get_clean();

        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isJavascriptEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('reporte.pdf', array('Attachment' => false));
    }

    public function cerrarCaja()
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->getDatos();
        $ventasInfo = $this->model->getTotalVentas($this->id_usuario);
        $fecha_cierre = date('Y-m-d H:i:s');

        $montoFinal = $data['saldo'];
        $totalVentas = (int) ($ventasInfo['total'] ?? 0);
        $egresos = $data['egresos'];
        $gastos = $data['gastos'];

        $result = $this->model->cerrarCaja($fecha_cierre, $montoFinal, $totalVentas, $egresos, $gastos, $this->id_usuario);
        if ($result == 1) {
            echo json_encode(['msg' => 'CAJA CERRADA', 'type' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['msg' => 'ERROR AL CERRAR LA CAJA', 'type' => 'error']);
        }
        exit;
    }


    public function historialRepote($idCaja)
    {
        $idCaja = (int) $idCaja;
        ob_start();
        $data['title'] = 'Reporte: ' . $idCaja;
        $data['idCaja'] = $idCaja;
        $data['actual'] = false;
        $data['empresa'] = $this->model->getEmpresa();

        $datos = $this->model->getHistorialCajas($idCaja);
        $data['movimientos']['inicialDecimal'] = number_format($datos['monto_inicial'], 2);
        $data['movimientos']['ingresosDecimal'] = number_format($datos['monto_final'], 2); 
        $data['movimientos']['egresosDecimal'] = number_format($datos['egresos'], 2);
        $data['movimientos']['gastosDecimal'] = number_format($datos['gastos'], 2);
        $data['movimientos']['saldoDecimal'] = number_format($datos['monto_final'], 2);

        $this->views->getView('cajas', 'reporte', $data);

        $html = ob_get_clean();

        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isJavascriptEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('reporte.pdf', array('Attachment' => false));
    }
}

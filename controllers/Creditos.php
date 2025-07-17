<?php
require 'vendor/autoload.php';

require __DIR__ . '/ticket/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

use Dompdf\Dompdf;

class Creditos extends Controller
{
    private $id_usuario;
    public function __construct()
    {
        parent::__construct();
        session_start();
        if (empty($_SESSION['id_usuario'])) {
            header('Location: ' . BASE_URL);
            exit;
        }
        if (!verificar('credito ventas')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $this->id_usuario = $_SESSION['id_usuario'];
    }
    public function index()
    {
        $data['script'] = 'creditos.js';
        $data['title'] = 'Administrar Creditos';
        $this->views->getView('creditos', 'index', $data);
    }
    public function listar()
    {
        $data = $this->model->getCreditos();
        for ($i = 0; $i < count($data); $i++) {
            $credito = $this->model->getCredito($data[$i]['id']);
            $result = $this->model->getAbono($data[$i]['id']);
            $abonado = ($result['total'] == null) ? 0 : $result['total'];
            $restante = $credito['monto'] - $abonado;
            if ($restante < 0.1 && $credito['estado'] = 1) {
                $this->model->actualizarCredito(0, $data[$i]['id']);
            }
            $data[$i]['monto'] = number_format($data[$i]['monto'], 2);
            $data[$i]['abonado'] = number_format($abonado, 2);
            $data[$i]['restante'] = number_format($restante, 2);
            $data[$i]['venta'] = 'N°: ' . $data[$i]['id_venta'];
            $data[$i]['acciones'] = '<a class="btn btn-danger" href="' . BASE_URL . 'creditos/reporte/' . $data[$i]['id'] . '" target="_blank"><i class="fas fa-file-pdf"></i></a>';
            if ($data[$i]['estado'] == 1) {
                $data[$i]['estado'] = '<span class="badge bg-warning">PENDIENTE</span>';
            } else if ($data[$i]['estado'] == 2) {
                $data[$i]['estado'] = '<span class="badge bg-danger">ANULADO</span>';
            } else {
                $data[$i]['estado'] = '<span class="badge bg-success">COMPLETADO</span>';
            }

        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function buscar()
    {
        $array = array();
        $valor = strClean($_GET['term']);
        $data = $this->model->buscarPorNombre($valor);        
        foreach ($data as $row) {
            $deuda = $this->model->getDeudaTotalCliente($row['id_cliente']);
            $resultAbono = $this->model->getAbono($row['id']);
            $abonado = ($resultAbono['total'] == null) ? 0 : $resultAbono['total'];
            //calcular restante  (monto - abono)
            $restante = $row['monto'] - $abonado;
            $result['monto'] = $deuda;
            $result['abonado'] = number_format($abonado, 2, '.', '');
            $result['restante'] = number_format($deuda, 2, '.', '');
            $result['fecha'] = $row['fecha'];
            $result['id'] = $row['id_cliente'];
            $result['label'] = $row['nombre'] . '   ' . 'Deuda Actual: ' . '  '. $deuda;
            $result['telefono'] = $row['telefono'];
            $result['direccion'] = $row['direccion'];            
            array_push($array, $result);
        }
        echo json_encode($array, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function registrarAbono()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);
        if (!empty($datos)) {
            $idCredito = strClean($datos['idCredito']);
            $monto = strClean($datos['monto_abonar']);
            $data = $this->model->registrarAbono($monto, $idCredito, $this->id_usuario);
            if ($data > 0) {
                $res = array('msg' => 'ABONO REGISTRADO', 'type' => 'success');
            } else {
                $res = array('msg' => 'ERROR AL REGISTRAR', 'type' => 'error');
            }
        } else {
            $res = array('msg' => 'TODO LOS CAMPOS SON REQUERIDO', 'type' => 'warning');
        }
        echo json_encode($res);
        die();
    }

    public function reporte($idCliente)
    {
        ob_start();
        $this->impresionDirecta($idCliente);
    }

    public function listarAbonos()
    {
        $data = $this->model->getHistorialAbonos();
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['credito'] = 'N°: ' . $data[$i]['id_credito'];
        }
        echo json_encode($data);
        die();
    }

    public function abonarGlobalmente()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);

        if (!empty($datos['idCredito']) && !empty($datos['monto_abonar'])) {
            $idCliente = $datos['idCredito'];
            $monto = floatval($datos['monto_abonar']);

            $creditos = $this->model->getCreditosActivosPorCliente($idCliente);

            if (empty($creditos)) {
                echo json_encode(['msg' => 'El cliente no tiene créditos activos', 'type' => 'warning']);
                return;
            }

            foreach ($creditos as $credito) {
                if ($monto <= 0)
                    break;

                $abonado = $this->model->getAbono($credito['id'])['total'] ?? 0;
                $restante = $credito['monto'] - $abonado;

                if ($restante <= 0)
                    continue;

                $abonoAplicado = min($restante, $monto);
                $this->model->registrarAbono($abonoAplicado, $credito['id'], $this->id_usuario);
                $monto -= $abonoAplicado;

                // Cerrar crédito si está completamente pagado
                if ($abonoAplicado >= $restante) {
                    $this->model->actualizarCredito(0, $credito['id']);
                }
            }

            echo json_encode(['msg' => 'ABONO DISTRIBUIDO', 'type' => 'success']);            
        } else {
            echo json_encode(['msg' => 'DATOS INSUFICIENTES', 'type' => 'error']);
        }
    }

    public function impresionDirecta($idCliente)
    {
        $empresa = $this->model->getEmpresa();
        $deuda = $this->model->getDeudaTotalCliente($idCliente);
        $nombre_impresora = "4BARCODE 3B-365B";
        $connector = new WindowsPrintConnector($nombre_impresora);
        $printer = new Printer($connector);

        # Vamos a alinear al centro lo próximo que imprimamos
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        $printer->text($empresa['nombre'] . "\n");
        $printer->text('Nit: ' . $empresa['ruc'] . "\n");
        $printer->text('Telefono: ' . $empresa['telefono'] . "\n");
        $printer->text('Dirección: ' . strip_tags($empresa['direccion']) . "\n");
        #La fecha también
        $printer->text(date("Y-m-d H:i:s") . "\n\n");

        #Datos del cliente
        $printer->text('Deuda restante' . "\n");
        $printer->text('--------------------' . "\n");
        /*Alinear a la izquierda para la cantidad y el nombre*/
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text(MONEDA . number_format($deuda, 2) . "\n");

        /*
            Terminamos de imprimir
            los productos, ahora va el total
        */
        $printer->text("--------\n");
        $printer->text("TOTAL: " . MONEDA . number_format($deuda, 2) . "\n");
        $printer->text("--------\n");
        /*
            Podemos poner también un pie de página
        */
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text(strip_tags($empresa['mensaje']));



        /*Alimentamos el papel 3 veces*/
        $printer->feed(3);

        /*
            Cortamos el papel. Si nuestra impresora
            no tiene soporte para ello, no generará
            ningún error
        */
        $printer->cut();

        /*
            Por medio de la impresora mandamos un pulso.
            Esto es útil cuando la tenemos conectada
            por ejemplo a un cajón
        */
        $printer->pulse();

        /*
            Para imprimir realmente, tenemos que "cerrar"
            la conexión con la impresora. Recuerda incluir esto al final de todos los archivos
        */
        $printer->close();
    }
}
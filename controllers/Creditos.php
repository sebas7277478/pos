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

        foreach ($data as &$credito) {
            $abonado = $this->model->getTotalAbonado($credito['id']);
            $restante = $credito['monto'] - $abonado;

            // Si ya está pagado, cerramos el crédito
            if ($restante < 0.1 && $credito['estado'] == CreditosModel::ESTADO_PENDIENTE) {
                $this->model->actualizarCredito(CreditosModel::ESTADO_COMPLETADO, $credito['id']);
                $credito['estado'] = CreditosModel::ESTADO_COMPLETADO;
            }

            $credito['monto'] = number_format($credito['monto'], 2);
            $credito['abonado'] = number_format($abonado, 2);
            $credito['restante'] = number_format(max(0, $restante), 2);
            $credito['venta'] = 'N°: ' . $credito['id_venta'];

            $credito['estado'] = $this->formatearEstado($credito['estado']);
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    private function formatearEstado($estado)
    {
        switch ($estado) {
            case CreditosModel::ESTADO_PENDIENTE:
                return '<span class="badge bg-warning">PENDIENTE</span>';
            case CreditosModel::ESTADO_ANULADO:
                return '<span class="badge bg-danger">ANULADO</span>';
            default:
                return '<span class="badge bg-success">COMPLETADO</span>';
        }
    }

    public function buscar()
    {
        $array = [];
        $valor = strClean($_GET['term'] ?? '');

        $data = $this->model->buscarPorNombre($valor);

        foreach ($data as $row) {
            $deuda = $this->model->getDeudaTotalCliente($row['id_cliente']);
            $abonado = $this->model->getTotalAbonado($row['id']);
            $restante = $row['monto'] - $abonado;

            $result = [
                'monto' => $deuda,
                'abonado' => number_format($abonado, 2, '.', ''),
                'restante' => number_format($deuda, 2, '.', ''),
                'fecha' => $row['fecha'],
                'id' => $row['id_cliente'],
                'label' => $row['nombre'] . ' | Deuda Actual: ' . $deuda,
                'telefono' => $row['telefono'],
                'direccion' => $row['direccion']
            ];

            $array[] = $result;
        }

        echo json_encode($array, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function registrarAbono()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);

        if (!empty($datos['idCredito']) && !empty($datos['monto_abonar'])) {
            $idCredito = intval($datos['idCredito']);
            $monto = floatval($datos['monto_abonar']);

            if ($monto <= 0) {
                $res = ['msg' => 'El monto debe ser mayor a 0', 'type' => 'warning'];
            } else {
                $data = $this->model->registrarAbono($monto, $idCredito, $this->id_usuario);
                $res = $data > 0
                    ? ['msg' => 'ABONO REGISTRADO', 'type' => 'success']
                    : ['msg' => 'ERROR AL REGISTRAR', 'type' => 'error'];
            }
        } else {
            $res = ['msg' => 'TODOS LOS CAMPOS SON REQUERIDOS', 'type' => 'warning'];
        }

        echo json_encode($res);
        die();
    }

    public function reporte($idCliente)
    {
        $this->impresionDirecta($idCliente);
    }

    public function listarAbonos()
    {
        $data = $this->model->getHistorialAbonos();

        foreach ($data as &$row) {
            $row['credito'] = 'N°: ' . $row['id_credito'];
        }

        echo json_encode($data);
        die();
    }

    public function abonarGlobalmente()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);

        if (!empty($datos['idCredito']) && !empty($datos['monto_abonar'])) {
            $idCliente = intval($datos['idCredito']);
            $monto = floatval($datos['monto_abonar']);

            if ($monto <= 0) {
                echo json_encode(['msg' => 'El monto debe ser mayor a 0', 'type' => 'warning']);
                return;
            }

            $creditos = $this->model->getCreditosActivosPorCliente($idCliente);

            if (empty($creditos)) {
                echo json_encode(['msg' => 'El cliente no tiene créditos activos', 'type' => 'warning']);
                return;
            }

            try {

                foreach ($creditos as $credito) {
                    if ($monto <= 0) {
                        break;
                    }

                    $abonado = $this->model->getTotalAbonado($credito['id']);
                    $restante = $credito['monto'] - $abonado;

                    if ($restante <= 0) {
                        continue;
                    }

                    $abonoAplicado = min($restante, $monto);
                    $this->model->registrarAbono($abonoAplicado, $credito['id'], $this->id_usuario);
                    $monto -= $abonoAplicado;
                }

                echo json_encode(['msg' => 'ABONO DISTRIBUIDO', 'type' => 'success']);

            } catch (Exception $e) {
                echo json_encode([
                    'msg' => 'ERROR AL DISTRIBUIR ABONO: ' . $e->getMessage(),
                    'type' => 'error'
                ]);
            }

        } else {
            echo json_encode(['msg' => 'DATOS INSUFICIENTES', 'type' => 'error']);
        }
    }

    public function impresionDirecta($idCliente)
    {
        $empresa = $this->model->getEmpresa();
        $cliente = $this->model->getCliente($idCliente);
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
        $printer->text('Datos del Cliente' . "\n");
        $printer->text('--------------------' . "\n");
        /*Alinear a la izquierda para la cantidad y el nombre*/
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($cliente['identidad'] . ': ' . $cliente['num_identidad'] . "\n\n");

        $printer->text('--------------------' . "\n");

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
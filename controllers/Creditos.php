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
            // El cálculo ya viene de SQL, solo formateamos
            $restante = $credito['restante'];

            if ($restante < 0.1 && $credito['estado'] == CreditosModel::ESTADO_PENDIENTE) {
                $this->model->actualizarCredito(CreditosModel::ESTADO_COMPLETADO, $credito['id']);
                $credito['estado'] = CreditosModel::ESTADO_COMPLETADO;
            }

            $credito['monto'] = number_format($credito['monto'], 2);
            $credito['abonado'] = number_format($credito['abonado'], 2);
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
        $valor = strClean($_GET['term'] ?? '');
        $data = $this->model->buscarPorNombre($valor);
        $array = [];
        foreach ($data as $row) {
            $deuda = $this->model->getDeudaTotalCliente($row['id_cliente']);
            $array[] = [
                'monto' => $deuda,
                'abonado' => number_format($row['abonado'], 2, '.', ''),
                'restante' => number_format($deuda, 2, '.', ''),
                'fecha' => $row['fecha'],
                'id' => $row['id_cliente'],
                'label' => $row['nombre'] . ' | Deuda: ' . number_format($deuda, 2),
                'telefono' => $row['telefono'],
                'direccion' => $row['direccion']
            ];
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
        header('Content-Type: application/json; charset=utf-8');
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);

        if (!empty($datos['idCredito']) && !empty($datos['monto_abonar'])) {
            $idCliente = intval($datos['idCredito']);
            $monto = floatval($datos['monto_abonar']);

            if ($monto <= 0) {
                echo json_encode(['msg' => 'El monto debe ser mayor a 0', 'type' => 'warning']);
                exit;
            }

            // OPTIMIZACIÓN: Obtener créditos con sus totales abonados en UNA SOLA CONSULTA
            $creditos = $this->model->getCreditosActivosConAbonos($idCliente);

            if (empty($creditos)) {
                echo json_encode(['msg' => 'El cliente no tiene créditos activos', 'type' => 'warning']);
                exit;
            }

            try {
                // OPTIMIZACIÓN: Preparar todos los abonos ANTES de insertar
                $abonosParaInsertar = [];
                $creditosParaActualizar = [];

                foreach ($creditos as $credito) {
                    if ($monto <= 0) {
                        break;
                    }

                    $restante = $credito['restante'];

                    if ($restante <= 0) {
                        continue;
                    }

                    $abonoAplicado = min($restante, $monto);

                    // Preparar datos para inserción batch
                    $abonosParaInsertar[] = [
                        'monto' => $abonoAplicado,
                        'id_credito' => $credito['id']
                    ];

                    // Si el abono completa el crédito, marcarlo para actualización
                    if ($restante - $abonoAplicado <= 0.01) {
                        $creditosParaActualizar[] = $credito['id'];
                    }

                    $monto -= $abonoAplicado;
                }

                // OPTIMIZACIÓN: Una sola transacción para todo
                $resultado = $this->model->registrarAbonosGlobalmente(
                    $abonosParaInsertar,
                    $creditosParaActualizar,
                    $this->id_usuario
                );

                if ($resultado) {
                    echo json_encode(['msg' => 'ABONO DISTRIBUIDO', 'type' => 'success']);
                } else {
                    echo json_encode(['msg' => 'ERROR AL DISTRIBUIR ABONO', 'type' => 'error']);
                }
            } catch (Exception $e) {
                error_log("Error en abonarGlobalmente: " . $e->getMessage());
                echo json_encode([
                    'msg' => 'ERROR AL DISTRIBUIR ABONO',
                    'type' => 'error'
                ]);
            }
        } else {
            echo json_encode(['msg' => 'DATOS INSUFICIENTES', 'type' => 'error']);
        }
        exit;
    }

    public function impresionDirecta($idCliente)
    {
        try {
            $empresa = $this->model->getEmpresa();
            $cliente = $this->model->getCliente($idCliente);

            // Usamos la nueva función del modelo para obtener el resumen completo
            $resumen = $this->model->getResumenDeudaCliente($idCliente);

            $nombre_impresora = NOMBRE_IMPRESORA;
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
            $printer->text('--------------------------------' . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Cliente: " . $cliente['nombre'] . "\n");
            $printer->text($cliente['identidad'] . ': ' . $cliente['num_identidad'] . "\n\n");
            $printer->text('--------------------------------' . "\n");

            // AÑADIMOS LA INFORMACIÓN ADICIONAL SOLICITADA
            $printer->text('RESUMEN DE CREDITO' . "\n");
            $printer->text('--------------------------------' . "\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Abono Realizado:      " . MONEDA . number_format($resumen['ultimo_abono'], 2) . "\n");

            $printer->text('--------------------------------' . "\n");
            $printer->setEmphasis(true); // Negrita para el restante
            $printer->text("DEUDA RESTANTE:   " . MONEDA . number_format($resumen['deuda_restante'], 2) . "\n");
            $printer->setEmphasis(false);
            $printer->text('--------------------------------' . "\n");

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text(strip_tags($empresa['mensaje']));
            $printer->feed(3);
            $printer->cut();
            $printer->pulse();
            $printer->close();
        } catch (Exception $e) {
            error_log("Error de impresión: " . $e->getMessage());
        }
    }
}

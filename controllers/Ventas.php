<?php
require 'vendor/autoload.php';

require __DIR__ . '/ticket/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Ventas extends Controller
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
        if (!verificar('ventas')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $this->id_usuario = $_SESSION['id_usuario'];
    }
    public function index()
    {
        $data['title'] = 'Ventas';
        $data['script'] = 'ventas.js';
        $data['busqueda'] = 'busqueda.js';
        $data['carrito'] = 'posVenta';
        $resultSerie = $this->model->getSerie();
        $serie = ($resultSerie['total'] == null) ? 1 : $resultSerie['total'] + 1;
        $data['serie'] = $this->generate_numbers($serie, 1, 8);
        $this->views->getView('ventas', 'index', $data);
    }

    public function registrarVenta()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);

        if (empty($datos['productos'])) {
            echo json_encode(['msg' => 'CARRITO VACÍO', 'type' => 'warning']);
            return;
        }

        // Validaciones iniciales
        $verifcarCaja = $this->model->getCaja($this->id_usuario);
        if (empty($verifcarCaja['monto_inicial'])) {
            echo json_encode(['msg' => 'LA CAJA ESTÁ CERRADA', 'type' => 'warning']);
            return;
        }

        // 1. Preparar datos y calcular total en UN SOLO ciclo en el controlador
        $productosParaModelo = [];
        $total = 0;
        foreach ($datos['productos'] as $p) {
            $subTotal = $p['precio'] * $p['cantidad'];
            $total += $subTotal;
            // Solo necesitamos ID, cantidad y precio para la lógica del modelo
            $productosParaModelo[] = [
                'id' => $p['id'],
                'cantidad' => $p['cantidad'],
                'precio' => $p['precio']
            ];
        }

        $resultSerie = $this->model->getSerie();
        $numSerie = ($resultSerie['total'] == null) ? 1 : $resultSerie['total'] + 1;
        $serie = $this->generate_numbers($numSerie, 1, 8)[0];

        $datosVenta = [
            'total' => $total,
            'metodo' => $datos['metodo'],
            'descuento' => !empty($datos['descuento']) ? $datos['descuento'] : 0,
            'serie' => $serie,
            'idCliente' => $datos['idCliente'],
            'pago' => $datos['pago']
        ];

        // 2. Llamar al modelo optimizado
        $idVenta = $this->model->procesarVentaEfectiva($datosVenta, $productosParaModelo, $this->id_usuario);

        if ($idVenta > 0) {
            echo json_encode(['msg' => 'VENTA GENERADA', 'type' => 'success', 'idVenta' => $idVenta]);
            if (!empty($datos['impresion'])) {
                $this->impresionDirecta($idVenta);
            }
        } else {
            echo json_encode(['msg' => 'ERROR AL GENERAR LA VENTA o Stock Insuficiente', 'type' => 'error']);
        }
    }

    public function reporte($datos)
    {
        ob_start();

        $array = explode(',', $datos);
        $tipo = $array[0];
        $idVenta = $array[1];

        $data['venta'] = $this->model->getVenta($idVenta);

        if (empty($data['venta'])) {
            echo 'Pagina no Encontrada';
            exit;
        }

        $data['productos'] = $this->model->getDetalleVenta($idVenta);

        if ($tipo == 'ticked') {
            $this->impresionDirecta($idVenta);
            //$dompdf->setPaper(array(0, 0, 222, 841), 'portrait');
        } else {
            $data['title'] = 'Reporte';
            $data['empresa'] = $this->model->getEmpresa();
            $this->views->getView('ventas', $tipo, $data);
            $html = ob_get_clean();
            $dompdf = new Dompdf();
            $options = $dompdf->getOptions();
            $options->set('isJavascriptEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdf->setOptions($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'vertical');
            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            $dompdf->stream('reporte.pdf', array('Attachment' => false));
        }
    }

    public function listarServerSide()
    {
        $draw   = intval($_POST['draw'] ?? 0);
        $start  = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);

        // La ganancia total ya viene calculada por SQL
        $ventas = $this->model->listarVentasServerSide($length, $start);
        $total  = $this->model->totalVentas();

        // Formatear salida SIN lógica pesada ni consultas extra
        foreach ($ventas as &$v) {
            $v['ganancia'] = number_format($v['ganancia_total'] ?? 0, 2); // Simplemente mostramos el valor

            if ($v['estado'] == 1) {
                $v['acciones'] = '<div><a class="btn btn-warning" onclick="anularVenta(' . $v['id'] . ')"><i class="fas fa-trash"></i></a><a class="btn btn-danger" onclick="verReporte(' . $v['id'] . ')"><i class="fas fa-file-pdf"></i></a></div>';
            } else {
                $v['acciones'] = '<div><span class="badge bg-info">Anulado</span><a class="btn btn-danger" onclick="verReporte(' . $v['id'] . ')"><i class="fas fa-file-pdf"></i></a></div>';
            }
        }

        echo json_encode(["draw" => $draw, "recordsTotal" => $total, "recordsFiltered" => $total, "data" => $ventas], JSON_UNESCAPED_UNICODE);
        die();
    }


    public function anular($idVenta)
    {
        if (!is_numeric($idVenta)) {
            echo json_encode(['msg' => 'ID inválido', 'type' => 'error']);
            return;
        }

        $res = $this->model->anularVentaCompleta($idVenta, $this->id_usuario);
        if ($res) {
            echo json_encode(['msg' => 'VENTA ANULADA', 'type' => 'success']);
        } else {
            echo json_encode(['msg' => 'ERROR AL ANULAR LA VENTA', 'type' => 'error']);
        }
    }


    public function impresionDirecta($idVenta)
    {
        $empresa = $this->model->getEmpresa();
        $venta = $this->model->getVenta($idVenta);
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
        $printer->text('--------------------' . "\n");
        /*Alinear a la izquierda para la cantidad y el nombre*/
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($venta['identidad'] . ': ' . $venta['num_identidad'] . "\n\n");

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text('Detalles del Producto' . "\n");
        $printer->text('--------------------' . "\n");
        $productos = $this->model->getDetalleVenta($idVenta);
        if (!is_array($productos)) {
            $productos = [];
        }
        foreach ($productos as $producto) {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(
                $producto['cantidad'] . " x " . $producto['descripcion']
            );

            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->text(
                MONEDA . number_format($producto['precio_venta'], 2) . "\n"
            );
        }


        $printer->text("--------\n");
        $printer->text("Descuento: " . MONEDA . number_format($venta['descuento'], 2) . "\n");
        $printer->text("--------\n");
        $printer->text("TOTAL: " . MONEDA . number_format($venta['total'] - $venta['descuento'], 2) . "\n");
        if (!empty($venta['pago']) && $venta['pago'] > 0) {
            $printer->text("--------\n");
            $printer->text("Pago con: " . MONEDA . number_format($venta['pago'], 2) . "\n");
            $printer->text("--------\n");
            $printer->text("Cambio: " . MONEDA . number_format($venta['pago'] - ($venta['total'] - $venta['descuento']), 2) . "\n\n");
            $printer->text("--------\n");
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($venta['metodo'] . "\n");
        $printer->text(strip_tags($empresa['mensaje']));
        $printer->feed(3);
        $printer->cut();
        $printer->pulse();
        $printer->close();

        exit;
    }

    public function verificarStock($idProducto)
    {
        $data = $this->model->getProducto($idProducto);
        echo json_encode($data);
        die();
    }

    function generate_numbers($start, $count, $digits)
    {
        $result = array();
        for ($n = $start; $n < $start + $count; $n++) {
            $result[] = str_pad($n, $digits, "0", STR_PAD_LEFT);
        }
        return $result;
    }

    // ENVIAR TICKET AL CORREO DEL CLIENTE
    public function enviarCorreo($idVenta)
    {
        $data['empresa'] = $this->model->getEmpresa();
        $data['venta'] = $this->model->getVenta($idVenta);
        ob_start();
        $data['title'] = 'Reporte';
        $this->views->getView('ventas', 'ticket_cliente', $data);
        $html = ob_get_clean();
        if (!empty($data)) {
            $mail = new PHPMailer(true);
            try {
                //Server settings
                //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
                $mail->SMTPDebug = 0;                      //Enable verbose debug output
                $mail->isSMTP();                                            //Send using SMTP
                $mail->Host = HOST_SMTP;                     //Set the SMTP server to send through
                $mail->SMTPAuth = true;                                   //Enable SMTP authentication
                $mail->Username = USER_SMTP;                     //SMTP username
                $mail->Password = CLAVE_SMTP;                               //SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
                $mail->Port = PUERTO_SMTP;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                //Recipients
                $mail->setFrom($data['empresa']['correo'], $data['empresa']['nombre']);
                $mail->addAddress($data['venta']['correo']);

                //Content
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';                                  //Set email format to HTML
                $mail->Subject = 'Comprobante - ' . TITLE;
                $mail->Body = $html;

                $mail->send();

                $res = array('msg' => 'CORREO ENVIADO CON LOS DATOS DE LA VENTA', 'type' => 'success');
            } catch (Exception $e) {
                $res = array('msg' => 'ERROR AL ENVIAR EL CORREO: ' . $mail->ErrorInfo, 'type' => 'error');
            }
        } else {
            $res = array('msg' => 'VENTA NO ENCONTRADA', 'type' => 'warning');
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function gananciasDiarias()
    {
        $data = $this->model->getGananciaDiaria();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function gananciasMensuales()
    {
        $data = $this->model->getGananciaMensual();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function gananciaHoy()
    {
        $data = $this->model->getGananciaHoy();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function exportarExcel()
    {
        if ($_SESSION['rol'] != 1) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');

        // Ventas del rango
        $ventas = $this->model->listarPorFechas($desde, $hasta);

        // 👉 GANANCIA TOTAL USANDO EL MODELO
        $ganancia = $this->model->gananciaPorFechas($desde, $hasta);

        $this->generarExcelVentas($ventas, $ganancia['ganancia'], $desde, $hasta);
    }

    private function generarExcelVentas(array $ventas, float $gananciaTotal, string $desde, string $hasta)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte Ventas');

        // =============================
        // ENCABEZADO
        // =============================
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'REPORTE DE VENTAS');

        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', "Desde: $desde  |  Hasta: $hasta");

        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');

        // =============================
        // CABECERAS TABLA
        // =============================
        $fila = 4;

        $cabeceras = [
            'Fecha',
            'Hora',
            'Total',
            'Cliente',
            'Serie',
            'Método',
            'Ganancia',
            'Estado'
        ];

        $col = 'A';
        foreach ($cabeceras as $texto) {
            $sheet->setCellValue($col . $fila, $texto);
            $sheet->getStyle($col . $fila)->getFont()->setBold(true);
            $sheet->getStyle($col . $fila)->getAlignment()->setHorizontal('center');
            $col++;
        }

        // =============================
        // DATOS
        // =============================
        $fila++;

        foreach ($ventas as $venta) {

            $sheet->setCellValue("A{$fila}", $venta['fecha']);
            $sheet->setCellValue("B{$fila}", $venta['hora']);
            $sheet->setCellValue("C{$fila}", $venta['total']);
            $sheet->setCellValue("D{$fila}", $venta['cliente']);
            $sheet->setCellValue("E{$fila}", $venta['serie']);
            $sheet->setCellValue("F{$fila}", $venta['metodo']);
            $sheet->setCellValue("G{$fila}", $venta['ganancia']);
            $sheet->setCellValue("H{$fila}", $venta['estado'] == 1 ? 'ACTIVO' : 'ANULADO');

            $fila++;
        }

        // =============================
        // GANANCIA TOTAL
        // =============================
        $fila += 1;

        $sheet->mergeCells("A{$fila}:F{$fila}");
        $sheet->setCellValue("A{$fila}", 'GANANCIA TOTAL');
        $sheet->setCellValue("G{$fila}", number_format($gananciaTotal, 2));

        $sheet->getStyle("A{$fila}:G{$fila}")->getFont()->setBold(true);

        // =============================
        // AJUSTES
        // =============================
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // =============================
        // DESCARGA
        // =============================
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="reporte_ventas_' . $desde . '_al_' . $hasta . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function migrarGananciasAntiguas()
    {
        $ventas = $this->model->getVentasSinDetalle(1000);

        if (empty($ventas)) {
            echo '✔ Migración completa';
            die();
        }

        foreach ($ventas as $venta) {

            $productos = json_decode($venta['productos'], true);

            if (!is_array($productos)) continue;

            foreach ($productos as $p) {

                $precioCompra = $this->model->getPrecioCompra($p['id']);
                $ganancia = ($p['precio'] - $precioCompra) * $p['cantidad'];

                $this->model->guardarDetalleVenta([
                    'id_venta'      => $venta['id'],
                    'id_producto'   => $p['id'],
                    'cantidad'      => $p['cantidad'],
                    'precio_venta'  => $p['precio'],
                    'precio_compra' => $precioCompra,
                    'ganancia'      => $ganancia
                ]);
            }
        }

        echo '✔ Lote migrado correctamente';
        die();
    }
}

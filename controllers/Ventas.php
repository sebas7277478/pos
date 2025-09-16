<?php
require 'vendor/autoload.php';

require __DIR__ . '/ticket/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

use Dompdf\Dompdf;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
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
        $array['productos'] = array();
        $total = 0;

        if (empty($datos['productos'])) {
            echo json_encode(['msg' => 'CARRITO VACÍO', 'type' => 'warning']);
            return;
        }

        $fecha = date('Y-m-d');
        $hora = date('H:i:s');
        $metodo = $datos['metodo'];
        $idCliente = $datos['idCliente'];
        $descuento = !empty($datos['descuento']) ? $datos['descuento'] : 0;
        $pago = !empty($datos['pago']) ? $datos['pago'] : 0;

        if (empty($idCliente)) {
            echo json_encode(['msg' => 'EL CLIENTE ES REQUERIDO', 'type' => 'warning']);
            return;
        }

        if (empty($metodo)) {
            echo json_encode(['msg' => 'EL MÉTODO DE PAGO ES REQUERIDO', 'type' => 'warning']);
            return;
        }

        $verifcarCaja = $this->model->getCaja($this->id_usuario);
        if (empty($verifcarCaja['monto_inicial'])) {
            echo json_encode(['msg' => 'LA CAJA ESTÁ CERRADA', 'type' => 'warning']);
            return;
        }

        // Armar productos y calcular total
        foreach ($datos['productos'] as $producto) {
            $result = $this->model->getProducto($producto['id']);
            if (!$result)
                continue;

            $data['id'] = $result['id'];
            $data['nombre'] = $result['descripcion'];
            $data['precio'] = $producto['precio'];
            $data['cantidad'] = $producto['cantidad'];
            $subTotal = $producto['precio'] * $producto['cantidad'];
            array_push($array['productos'], $data);
            $total += $subTotal;
        }

        $resultSerie = $this->model->getSerie();
        $numSerie = ($resultSerie['total'] == null) ? 1 : $resultSerie['total'] + 1;
        $serie = $this->generate_numbers($numSerie, 1, 8)[0];

        // Registrar venta completa con transacciones
        $idVenta = $this->model->registrarVentaCompleta(
            $array['productos'],
            $total,
            $fecha,
            $hora,
            $metodo,
            $descuento,
            $serie,
            $pago ?: $total,
            $idCliente,
            $this->id_usuario
        );

        if ($idVenta > 0) {
            if (!empty($datos['impresion'])) {
                $this->impresionDirecta($idVenta);
            }
            echo json_encode(['msg' => 'VENTA GENERADA', 'type' => 'success', 'idVenta' => $idVenta]);
        } else {
            echo json_encode(['msg' => 'ERROR AL GENERAR LA VENTA', 'type' => 'error']);
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

    public function listar()
    {
        $data = $this->model->getVentas();
        for ($i = 0; $i < count($data); $i++) {

            // Inicializar ganancia
            $ganancia = 0;

            // Decodificar los productos (asegúrate que se llama así el campo)
            $productos = json_decode($data[$i]['productos'], true);

            foreach ($productos as $prod) {
                $idProducto = $prod['id'];
                $precioVenta = $prod['precio'];
                $cantidad = $prod['cantidad'];

                // Obtener precio de compra del producto (puedes usar un modelo aparte o cargarlo previamente)
                $producto = $this->model->getProductoGanancia($idProducto); // debes tener este método en tu modelo
                $precioCompra = $producto['precio_compra'];

                $ganancia += ($precioVenta - $precioCompra) * $cantidad;
            }

            // Agregar la ganancia al array que se enviará al front
            $data[$i]['ganancia'] = number_format($ganancia, 2);

            if ($data[$i]['estado'] == 1) {
                $data[$i]['acciones'] = '<div>
                <a class="btn btn-warning" href="#" onclick="anularVenta(' . $data[$i]['id'] . ')"><i class="fas fa-trash"></i></a>
                <a class="btn btn-danger" href="#" onclick="verReporte(' . $data[$i]['id'] . ')"><i class="fas fa-file-pdf"></i></a>
                </div>';
            } else {
                $data[$i]['acciones'] = '<div>
                <span class="badge bg-info">Anulado</span>
                <a class="btn btn-danger" href="#" onclick="verReporte(' . $data[$i]['id'] . ')"><i class="fas fa-file-pdf"></i></a>
                </div>';
            }
        }
        echo json_encode($data);
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
        $printer->text($venta['identidad'] . ': ' . $venta['num_identidad'] . "\n\n");

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text('Detalles del Producto' . "\n");
        $printer->text('--------------------' . "\n");
        $productos = json_decode($venta['productos'], true);
        foreach ($productos as $producto) {
            /*Alinear a la izquierda para la cantidad y el nombre*/
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text($producto['cantidad'] . '  ' . "x" . '  ' . $producto['nombre'] . '    ');

            /*Y a la derecha para el importe*/
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->text(MONEDA . number_format($producto['precio'], 2) . "\n");
        }

        /*
            Terminamos de imprimir
            los productos, ahora va el total
        */
        $printer->text("--------\n");
        $printer->text("Descuento: " . MONEDA . number_format($venta['descuento'], 2) . "\n");
        $printer->text("--------\n");
        $printer->text("TOTAL: " . MONEDA . number_format($venta['total'] - $venta['descuento'], 2) . "\n");
        $printer->text("--------\n");
        $printer->text("Pago con: " . MONEDA . number_format($venta['pago'], 2) . "\n");
        $printer->text("--------\n");
        $printer->text("Cambio: " . MONEDA . number_format($venta['pago'] - $venta['total'] + $venta['descuento'], 2) . "\n\n");
        $printer->text("--------\n");
        /*
            Podemos poner también un pie de página
        */
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($venta['metodo'] . "\n");
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
}

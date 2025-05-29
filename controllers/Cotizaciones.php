<?php
require 'vendor/autoload.php';
require __DIR__ . '/ticket/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Dompdf\Dompdf;
class Cotizaciones extends Controller
{
    public function __construct()
    {
        parent::__construct();
        session_start();
        if (empty($_SESSION['id_usuario'])) {
            header('Location: ' . BASE_URL);
            exit;
        }
        if (!verificar('cotizaciones')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
    }
    public function index()
    {
        $data['title'] = 'Cotizaciones';
        $data['script'] = 'cotizaciones.js';
        $data['busqueda'] = 'busqueda.js';
        $data['carrito'] = 'posCotizaciones';
        $this->views->getView('cotizaciones', 'index', $data);
    }
    public function registrarCotizacion()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);
        $array['productos'] = array();
        $total = 0;
        if (!empty($datos['productos'])) {
            $fecha = date('Y-m-d');
            $hora = date('H:i:s');
            $metodo = $datos['metodo'];
            $validez = $datos['validez'];
            $descuento = (!empty($datos['descuento'])) ? $datos['descuento'] : 0;
            $idCliente = $datos['idCliente'];
            if (empty($idCliente)) {
                $res = array('msg' => 'EL CLIENTE ES REQUERIDO', 'type' => 'warning');
            } else if (empty($metodo)) {
                $res = array('msg' => 'EL METODO ES REQUERIDO', 'type' => 'warning');
            } else if (empty($validez)) {
                $res = array('msg' => 'LA VALIDEZ ES REQUERIDO', 'type' => 'warning');
            } else {
                foreach ($datos['productos'] as $producto) {
                    $result = $this->model->getProducto($producto['id']);
                    $data['id'] = $result['id'];
                    $data['nombre'] = $result['descripcion'];
                    $data['precio'] = $result['precio_venta'];
                    $data['cantidad'] = $producto['cantidad'];
                    $subTotal = $result['precio_venta'] * $producto['cantidad'];
                    array_push($array['productos'], $data);
                    $total += $subTotal;
                }
                $datosProductos = json_encode($array['productos']);
                $cotizacion = $this->model->registrarCotizacion($datosProductos, $total, $fecha, $hora, $metodo, $validez, $descuento, $idCliente);
                if ($cotizacion > 0) {
                    $res = array('msg' => 'COTIZACIÓN GENERADA', 'type' => 'success', 'idCotizacion' => $cotizacion);
                } else {
                    $res = array('msg' => 'ERROR AL GENERAR LA COTIZACIÓN', 'type' => 'error');
                }
            }
        } else {
            $res = array('msg' => 'CARRITO VACIO', 'type' => 'warning');
        }
        echo json_encode($res);
        die();
    }

    public function reporte($datos)
    {
        ob_start();
        $array = explode(',', $datos);
        $tipo = $array[0];
        $idCotizacion = $array[1];

        if ($tipo == 'ticked') {
            $this->impresionDirecta($idCotizacion);
           // $dompdf->setPaper(array(0, 0, 130, 841), 'portrait');
        } else {
            $data['title'] = 'Reporte';
            $data['empresa'] = $this->model->getEmpresa();
            $data['cotizacion'] = $this->model->getCotizacion($idCotizacion);
            if (empty($data['cotizacion'])) {
                echo 'Pagina no Encontrada';
                exit;
            }
            $this->views->getView('cotizaciones', $tipo, $data);
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
        $data = $this->model->getCotizaciones();
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['acciones'] = '<a class="btn btn-danger" href="#" onclick="verReporte(' . $data[$i]['id'] . ')"><i class="fas fa-file-pdf"></i></a>';
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function impresionDirecta($idCotizacion)
    {
        $empresa = $this->model->getEmpresa();
        $data['cotizacion'] = $this->model->getCotizacion($idCotizacion);
        $nombre_impresora = "4BARCODE3B-365B";
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
        $printer->text($data['cotizacion']['identidad'] . ': ' . $data['cotizacion']['num_identidad'] . "\n\n");
        
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text('Detalles del Producto' . "\n");
        $printer->text('--------------------' . "\n");
        $productos = json_decode($data['cotizacion']['productos'], true);
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
        $printer->text("Descuento: " . MONEDA . number_format($data['cotizacion']['descuento'], 2) . "\n");
        $printer->text("--------\n");
        $printer->text("TOTAL: " . MONEDA . number_format($data['cotizacion']['total'] - $data['cotizacion']['descuento'], 2) . "\n\n");
        $printer->text("--------\n");
        /*
            Podemos poner también un pie de página
        */
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($data['cotizacion']['metodo'] . "\n");
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
?>

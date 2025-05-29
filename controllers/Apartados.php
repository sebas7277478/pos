<?php
require 'vendor/autoload.php';

require __DIR__ . '/ticket/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

use Dompdf\Dompdf;

class Apartados extends Controller
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
        if (!verificar('apartados')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $this->id_usuario = $_SESSION['id_usuario'];
    }
    public function index()
    {
        $data['script'] = 'apartados.js';
        $data['title'] = 'Apartados';
        $data['busqueda'] = 'busqueda.js';
        $data['carrito'] = 'posApartados';
        $this->views->getView('apartados', 'index', $data);
    }
    public function registrarApartado()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);
        $array['productos'] = array();
        $total = 0;
        if (!empty($datos['productos'])) {
            $fecha_create = date('Y-m-d');
            $fecha_apartado = $datos['fecha_apartado'] . ' ' . date('H:i:s');
            $fecha_retiro = $datos['fecha_retiro'] . ' 23:59:59';
            $abono = $datos['abono'];
            $color = $datos['color'];
            $idCliente = $datos['idCliente'];
            if (empty($idCliente)) {
                $res = array('msg' => 'EL CLIENTE ES REQUERIDO', 'type' => 'warning');
            } else if (empty($fecha_apartado)) {
                $res = array('msg' => 'FECHA APARTADO ES REQUERIDO', 'type' => 'warning');
            } else if (empty($fecha_retiro)) {
                $res = array('msg' => 'FECHA RETIRO ES REQUERIDO', 'type' => 'warning');
            } else if (empty($abono)) {
                $res = array('msg' => 'ABONO ES REQUERIDO', 'type' => 'warning');
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
                $apartado = $this->model->registrarApartado($datosProductos, $fecha_create, $fecha_apartado, $fecha_retiro, $abono, $total, $color, $idCliente, $this->id_usuario);
                if ($apartado > 0) {
                    foreach ($datos['productos'] as $producto) {
                        $result = $this->model->getProducto($producto['id']);
                        //actualizar stock
                        $nuevaCantidad = $result['cantidad'] - $producto['cantidad'];
                        $totalVentas = $result['ventas'] + $producto['cantidad'];
                        $this->model->actualizarStock($nuevaCantidad, $totalVentas, $result['id']);
                        //movimientos
                        $movimiento = 'Apartado N°: ' . $apartado;
                        $this->model->registrarMovimiento($movimiento, 'salida', $producto['cantidad'], $nuevaCantidad, $producto['id'], $this->id_usuario);
                    }
                    $this->model->registrarDetalle($abono, $apartado, $this->id_usuario);
                    $res = array('msg' => 'PRODUCTOS APARTADOS', 'type' => 'success', 'idApartado' => $apartado);
                } else {
                    $res = array('msg' => 'ERROR AL APARTAR LOS PRODUCTOS', 'type' => 'error');
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
        $idApartado = $array[1];

        if ($tipo == 'ticked') {
            $this->impresionDirecta($idApartado);
            //$dompdf->setPaper(array(0, 0, 130, 841), 'portrait');
        } else {

            $data['title'] = 'Reporte';
            $data['empresa'] = $this->model->getEmpresa();
            $data['apartado'] = $this->model->getApartado($idApartado);
            if (empty($data['apartado'])) {
                echo 'Pagina no Encontrada';
                exit;
            }
            $this->views->getView('apartados', $tipo, $data);
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
        $data = $this->model->getApartados();
        for ($i = 0; $i < count($data); $i++) {
            $estado = ($data[$i]['estado'] == 0) ? 'Completado' : 'Pendiente';
            $data[$i]['title'] = $estado . ' - ' . $data[$i]['nombre'];
            $data[$i]['start'] = $data[$i]['fecha_apartado'];
            $data[$i]['end'] = $data[$i]['fecha_retiro'];
        }
        echo json_encode($data);
        die();
    }

    public function verDatos($idApartado)
    {
        $data = $this->model->getApartado($idApartado);
        echo json_encode($data);
        die();
    }

    public function procesarEntrega($idApartado)
    {
        $apartado = $this->model->getApartado($idApartado);
        $data = $this->model->procesarEntrega($apartado['total'], 0, $idApartado);
        if ($data == 1) {
            $this->model->actualizarDetalle($apartado['total'], $idApartado);
            $res = array('msg' => 'PROCESADO CON ÉXITO', 'type' => 'success');
        } else {
            $res = array('msg' => 'ERROR AL PROCESAR', 'type' => 'error');
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function listarHistorial()
    {
        $data = $this->model->getApartados();
        for ($i = 0; $i < count($data); $i++) {
            if ($data[$i]['estado'] == 0) {
                $data[$i]['estado'] = '<span class="badge bg-success">Completado</span>';
            } else {
                $data[$i]['estado'] = '<span class="badge bg-danger">Pendiente</span>';
            }
            $data[$i]['cliente'] = '<span class="badge" style="background: ' . $data[$i]['color'] . ';">' . $data[$i]['nombre'] . '</span>';
            $data[$i]['acciones'] = '<a class="btn btn-danger" href="#" onclick="verReporte(' . $data[$i]['id'] . ')"><i class="fas fa-file-pdf"></i></a>';
        }
        echo json_encode($data);
        die();
    }

    public function impresionDirecta($idApartado)
    {
        $empresa = $this->model->getEmpresa();
        $data['apartado'] = $this->model->getApartado($idApartado);
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
        $printer->text($data['apartado']['identidad'] . ': ' . $data['apartado']['num_identidad'] . "\n\n");
       
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text('Detalles del Producto' . "\n");
        $printer->text('--------------------' . "\n");
        $productos = json_decode($data['apartado']['productos'], true);
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
        $printer->text("Abono: " . MONEDA . number_format($data['apartado']['abono'], 2) . "\n");
        $printer->text("--------\n");
        $printer->text("Deuda: " . MONEDA . number_format($data['apartado']['total'] - $data['apartado']['abono'], 2) . "\n\n");
       
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

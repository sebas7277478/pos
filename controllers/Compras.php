<?php
require 'vendor/autoload.php';

require __DIR__ . '/ticket/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

use Dompdf\Dompdf;

class Compras extends Controller
{
    private $id_usuario, $caja;
    public function __construct()
    {
        parent::__construct();
        require_once 'controllers/Cajas.php';
        $this->caja = new Cajas();
        if (empty($_SESSION['id_usuario'])) {
            header('Location: ' . BASE_URL);
            exit;
        }
        if (!verificar('compras')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $this->id_usuario = $_SESSION['id_usuario'];
    }
    public function index()
    {
        $data['title'] = 'Compras';
        $data['script'] = 'compras.js';
        $data['busqueda'] = 'busqueda.js';
        $data['carrito'] = 'posCompra';
        $this->views->getView('compras', 'index', $data);
    }
    public function registrarCompra()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);
        $array['productos'] = array();
        if (!empty($datos['productos'])) {
            $idproveedor = $datos['idProveedor'];
            $indice = $datos['serie'];
            $numberSerie = $this->generate_numbers($indice, 1, 8);
            $fecha = date('Y-m-d');
            $hora = date('H:i:s');
            $metodo = $datos['metodo'];
            $serie = $numberSerie[0];
            if (empty($idproveedor)) {
                $res = array('msg' => 'EL PROVEEDOR ES REQUERIDO', 'type' => 'warning');
            } else if (empty($serie)) {
                $res = array('msg' => 'LA SERIE ES REQUERIDO', 'type' => 'warning');
            } else {
                $total = 0;
                $saldo = $this->caja->getDatos();
                foreach ($datos['productos'] as $producto) {
                    $result = $this->model->getProducto($producto['id']);
                    $data['id'] = $result['id'];
                    $data['nombre'] = $result['descripcion'];
                    $data['precio'] = $result['precio_compra'];
                    $data['cantidad'] = $producto['cantidad'];
                    $subTotal = $result['precio_compra'] * $producto['cantidad'];
                    array_push($array['productos'], $data);
                    $total += $subTotal;
                }
                if ($metodo == 'CONTADO') {
                    if ($saldo['saldo'] >= $total) {
                        $datosProductos = json_encode($array['productos']);
                        $compra = $this->model->registrarCompra($datosProductos, $total, $fecha, $hora, $metodo, $serie, $idproveedor, $this->id_usuario);
                        if ($compra > 0) {
                            foreach ($datos['productos'] as $producto) {
                                $result = $this->model->getProducto($producto['id']);
                                //actualizar stock
                                $nuevaCantidad = $result['cantidad'] + $producto['cantidad'];
                                $this->model->actualizarStock($nuevaCantidad, $result['id']);
                                $movimiento = 'Compra N°: ' . $compra . ' - ' . $metodo;
                                $this->model->registrarMovimiento($movimiento, 'entrada', $producto['cantidad'], $nuevaCantidad, $producto['id'], $this->id_usuario);
                            }
                            $res = array('msg' => 'COMPRA GENERADA', 'type' => 'success', 'idCompra' => $compra);
                        } else {
                            $res = array('msg' => 'ERROR AL CREAR COMPRA', 'type' => 'error');
                        }
                    } else {
                        $res = array('msg' => 'SALDO DISPONIBLE: ' . MONEDA . $saldo['saldo'], 'type' => 'warning');
                    }
                }
                if ($metodo == 'CREDITO') {
                    $datosProductos = json_encode($array['productos']);

                    $compra = $this->model->registrarCompra($datosProductos, $total, $fecha, $hora, $metodo, $serie, $idproveedor, $this->id_usuario);
                    $this->model->registrarCredito($total, $fecha, $hora, $idproveedor, $this->id_usuario, $compra);

                    if ($compra > 0) {
                        foreach ($datos['productos'] as $producto) {
                            $result = $this->model->getProducto($producto['id']);
                            //actualizar stock
                            $nuevaCantidad = $result['cantidad'] + $producto['cantidad'];
                            $this->model->actualizarStock($nuevaCantidad, $result['id']);
                            $movimiento = 'Compra N°: ' . $compra . ' - ' . $metodo;
                            $this->model->registrarMovimiento($movimiento, 'entrada', $producto['cantidad'], $nuevaCantidad, $producto['id'], $this->id_usuario);
                        }
                        $res = array('msg' => 'COMPRA GENERADA', 'type' => 'success', 'idCompra' => $compra);
                    } else {
                        $res = array('msg' => 'ERROR AL CREAR COMPRA', 'type' => 'error');
                    }
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
        $idCompra = $array[1];

        if ($tipo == 'ticked') {
            $this->impresionDirecta($idCompra);
        } else {
            $data['title'] = 'Reporte';
            $data['empresa'] = $this->model->getEmpresa();
            $data['compra'] = $this->model->getCompra($idCompra);
            if (empty($data['compra'])) {
                echo 'Pagina no Encontrada';
                exit;
            }
            $this->views->getView('compras', $tipo, $data);
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
            $dompdf->stream('ticked.pdf', array('Attachment' => false));
        }

    }

    public function listar()
    {
        $data = $this->model->getCompras();
        for ($i = 0; $i < count($data); $i++) {
            if ($data[$i]['estado'] == 1) {

                $data[$i]['acciones'] = '<div>
                <a class="btn btn-warning" href="#" onclick="anularCompra(' . $data[$i]['id'] . ')"><i class="fas fa-trash"></i></a>
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

    public function anular($idCompra)
    {
        if (isset($_GET) && is_numeric($idCompra)) {
            $data = $this->model->anular($idCompra);
            if ($data == 1) {
                $resultCompra = $this->model->getCompra($idCompra);
                $compraProducto = json_decode($resultCompra['productos'], true);
                foreach ($compraProducto as $producto) {
                    $result = $this->model->getProducto($producto['id']);
                    $nuevaCantidad = $result['cantidad'] - $producto['cantidad'];
                    $this->model->actualizarStock($nuevaCantidad, $producto['id']);
                    //movimientos
                    $movimiento = 'Devolución Compra N°: ' . $idCompra;
                    $this->model->registrarMovimiento($movimiento, 'salida', $producto['cantidad'], $nuevaCantidad, $producto['id'], $this->id_usuario);
                }
                $res = array('msg' => 'COMPRA ANULADO', 'type' => 'success');
            } else {
                $res = array('msg' => 'ERROR AL ANULAR', 'type' => 'error');
            }
        } else {
            $res = array('msg' => 'ERROR DESCONOCIDO', 'type' => 'error');
        }
        echo json_encode($res);
        die();
    }

    public function buscarCreditoActivo()
    {
        $array = array();
        $valor = strClean($_GET['term']);
        $data = $this->model->buscarCreditoActivo($valor);
        foreach ($data as $row) {
            $resultAbono = $this->model->getAbonoCredito($row['id']);
            $abonado = ($resultAbono['total'] == null) ? 0 : $resultAbono['total'];
            $restante = $row['monto'] - $abonado;
            $result['monto'] = $row['monto'];
            $result['abonado'] = $abonado;
            $result['restante'] = $restante;
            $result['fecha'] = $row['fecha'];
            $result['id'] = $row['id'];
            $result['label'] = $row['nombre'] . '    Fecha Compra: ' . $row['fecha'] . '  Deuda Actual: ' . $restante;
            $result['telefono'] = $row['telefono'];
            $result['direccion'] = $row['direccion'];
            $result['id_compra'] = $row['id_compra'];
            array_push($array, $result);
        }
        echo json_encode($array, JSON_UNESCAPED_UNICODE);
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

    public function registrarAbonoCompras()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);
        if (!empty($datos)) {
            $saldo = $this->caja->getDatos();
            $fecha = $datos['fecha'];
            $idCreditoCompra = $datos['idCreditoCompra'];
            $monto = strClean($datos['monto_abonar']);
            if ($saldo['saldo'] >= $monto) {
                $data = $this->model->registrarAbonoCompras($monto, $fecha, $idCreditoCompra, $this->id_usuario);
                if ($data > 0) {
                    $res = array('msg' => 'PAGO REGISTRADO', 'type' => 'success');
                } else {
                    $res = array('msg' => 'ERROR AL REGISTRAR', 'type' => 'error');
                }
            } else {
                $res = array('msg' => 'SALDO DISPONIBLE: ' . MONEDA . $saldo['saldo'], 'type' => 'warning');
            }
        } else {
            $res = array('msg' => 'TODOS LOS CAMPOS SON REQUERIDOS', 'type' => 'warning');
        }
        echo json_encode($res);

        $credito = $this->model->getCredito($datos['idCreditoCompra']);
        $resultAbono = $this->model->getAbonoCredito($datos['idCreditoCompra']);
        $abonado = ($resultAbono['total'] == null) ? 0 : $resultAbono['total'];
        $restante = $credito['monto'] - $abonado;
        if ($restante < 0.1 && $credito['estado'] = 1) {
            $this->model->actualizarCreditoCompras(0, $datos['idCreditoCompra']);
        }
        die();
    }

    public function impresionDirecta($idCompra)
    {
        $empresa = $this->model->getEmpresa();
        $compra = $this->model->getCompra($idCompra);
        $nombre_impresora = "POS58 Printer";
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
        $printer->text('Datos del Proveedor' . "\n");
        $printer->text('--------------------' . "\n");
        /*Alinear a la izquierda para la cantidad y el nombre*/
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text('Nit' . ': ' . $compra['ruc'] . "\n");
        $printer->text('Proveedor: ' . $compra['nombre'] . "\n");
        $printer->text('Telefono: ' . $compra['telefono'] . "\n");
        $printer->text('Dirección: ' . strip_tags($compra['direccion']) . "\n\n");

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text('Detalles del Producto' . "\n");
        $printer->text('--------------------' . "\n");
        $productos = json_decode($compra['productos'], true);
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
        $printer->text("TOTAL: " . MONEDA . number_format($compra['total'], 2) . "\n");
        $printer->text("--------\n\n");
        /*
            Podemos poner también un pie de página
        */
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($compra['metodo'] . "\n");
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
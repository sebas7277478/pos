<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;

class Inventarios extends Controller
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
        if (!verificar('inventario')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $this->id_usuario = $_SESSION['id_usuario'];
    }

    public function index()
    {
        $data['title'] = 'Inventarios';
        $data['script'] = 'inventarios.js';
        $this->views->getView('inventarios', 'index', $data);
    }

    /**
     * $datos: si viene vacío devuelve todos los movimientos del usuario,
     * si viene en formato "YYYY-MM" filtra por año y mes.
     */
    public function listarMovimientos($datos = null)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($datos)) {
            $data = $this->model->getMovimientos($this->id_usuario);
        } else {
            $array = explode('-', $datos);
            $anio = isset($array[0]) ? intval($array[0]) : 0;
            $mes = isset($array[1]) ? intval($array[1]) : 0;
            if ($anio <= 0 || $mes <= 0) {
                http_response_code(400);
                echo json_encode(['msg' => 'Parámetros de fecha inválidos', 'type' => 'warning']);
                exit;
            }
            $data = $this->model->getMovimientosMes($anio, $mes, $this->id_usuario);
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function reporte($datos = null)
    {
        $data['empresa'] = $this->model->getEmpresa();

        if (empty($datos)) {
            $data['inventario'] = $this->model->getMovimientos($this->id_usuario);
        } else {
            $array = explode('-', $datos);
            $anio = isset($array[0]) ? intval($array[0]) : 0;
            $mes = isset($array[1]) ? intval($array[1]) : 0;
            if ($anio <= 0 || $mes <= 0) {
                // mostramos todos si los parámetros son inválidos
                $data['inventario'] = $this->model->getMovimientos($this->id_usuario);
            } else {
                $data['inventario'] = $this->model->getMovimientosMes($anio, $mes, $this->id_usuario);
            }
        }

        ob_start();
        $this->views->getView('inventarios', 'reporte', $data);
        $html = ob_get_clean();

        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isJavascriptEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('reporte_inventario.pdf', ['Attachment' => false]);
    }

    /**
     * Procesa un ajuste de inventario.
     * Espera JSON { idProducto, cantidad } donde cantidad puede ser positiva (entrada) o negativa (salida)
     */
    public function procesarAjuste()
    {
        header('Content-Type: application/json; charset=utf-8');

        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);

        if (empty($datos['idProducto'])) {
            http_response_code(400);
            echo json_encode(['msg' => 'EL PRODUCTO ES REQUERIDO', 'type' => 'warning']);
            exit;
        }
        if (!isset($datos['cantidad'])) {
            http_response_code(400);
            echo json_encode(['msg' => 'LA CANTIDAD ES REQUERIDO', 'type' => 'warning']);
            exit;
        }

        $idProducto = intval($datos['idProducto']);
        // cantidad puede ser decimal o entero, permitimos float negativo
        if (!is_numeric($datos['cantidad'])) {
            http_response_code(400);
            echo json_encode(['msg' => 'LA CANTIDAD DEBE SER NUMÉRICA', 'type' => 'warning']);
            exit;
        }
        $cantidad = floatval($datos['cantidad']);

        // obtener producto y verificar existencia
        $producto = $this->model->getProducto($idProducto);
        if (empty($producto)) {
            http_response_code(404);
            echo json_encode(['msg' => 'PRODUCTO NO ENCONTRADO', 'type' => 'error']);
            exit;
        }

        // calcular nueva cantidad
        $nuevaCantidad = $producto['cantidad'] + $cantidad;
        // opcional: evitar stock negativo (coméntalo si quieres permitir negativo)
        if ($nuevaCantidad < 0) {
            http_response_code(400);
            echo json_encode(['msg' => 'STOCK INSUFICIENTE (no se permite stock negativo)', 'type' => 'error']);
            exit;
        }

        $cantidadInventario = abs($cantidad);
        $accion = ($cantidad > 0) ? 'entrada' : 'salida';
        $movimiento = 'Ajuste de Inventario: ' . $accion;

        $res = $this->model->procesarAjusteConMovimiento(
            $nuevaCantidad,
            $idProducto,
            $movimiento,
            $accion,
            $cantidadInventario,
            $this->id_usuario
        );

        if ($res) {
            echo json_encode(['msg' => 'STOCK DEL PRODUCTO AJUSTADO', 'type' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['msg' => 'ERROR EN EL AJUSTE', 'type' => 'error']);
        }
        exit;

    }

    /**
     * Imprime kardex (PDF) de un producto
     */
    public function kardex($idProducto)
    {
        $idProducto = intval($idProducto);
        $data['empresa'] = $this->model->getEmpresa();
        $data['kardex'] = $this->model->getKardex($idProducto, $this->id_usuario);

        // separo entradas/salidas para la vista
        for ($i = 0; $i < count($data['kardex']); $i++) {
            $data['kardex'][$i]['entrada'] = 0;
            $data['kardex'][$i]['salida'] = 0;
            if ($data['kardex'][$i]['accion'] === 'salida') {
                $data['kardex'][$i]['salida'] = $data['kardex'][$i]['cantidad'];
            } else {
                $data['kardex'][$i]['entrada'] = $data['kardex'][$i]['cantidad'];
            }
        }

        ob_start();
        $this->views->getView('inventarios', 'kardex', $data);
        $html = ob_get_clean();

        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isJavascriptEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('kardex_' . $idProducto . '.pdf', ['Attachment' => false]);
    }
}

const tblNuevaVenta = document.querySelector("#tblNuevaVenta tbody");

const idCliente = document.querySelector("#idCliente");
const telefonoCliente = document.querySelector("#telefonoCliente");
const direccionCliente = document.querySelector("#direccionCliente");
const errorCliente = document.querySelector("#errorCliente");

const descuento = document.querySelector("#descuento");
const metodo = document.querySelector("#metodo");
const impresion_directa = document.querySelector("#impresion_directa");

const pagar_con = document.querySelector("#pagar_con");
const totalPagarHidden = document.querySelector("#totalPagarHidden");
const cambio = document.querySelector("#cambio");

const btnExcel = document.querySelector("#btnExcel");

document.addEventListener("DOMContentLoaded", function () {
  //cargar productos de localStorage
  mostrarProducto();

  //autocomplete clientes
  $("#buscarCliente").autocomplete({
    delay: 500,
    source: function (request, response) {
      $.ajax({
        url: base_url + "clientes/buscar",
        dataType: "json",
        data: { term: request.term },
        success: function (data) {
          response(data);
          errorCliente.textContent = data.length > 0 ? "" : "NO HAY CLIENTE";
        },
      });
    },
    minLength: 2,
    select: function (event, ui) {
      telefonoCliente.value = ui.item.telefono;
      direccionCliente.innerHTML = ui.item.direccion;
      idCliente.value = ui.item.id;
    },
  });

  //completar venta
  btnAccion.addEventListener("click", async function () {
    let filas = document.querySelectorAll("#tblNuevaVenta tr").length;
    if (filas < 2) {
      alertaPersonalizada("warning", "CARRITO VACIO");
      return;
    }
    if (metodo.value == "") {
      alertaPersonalizada("warning", "EL METODO ES REQUERIDO");
      return;
    }

    const datos = {
      productos: listaCarrito,
      idCliente: idCliente.value,
      metodo: metodo.value,
      descuento: descuento.value,
      pago: pagar_con.value,
      impresion: impresion_directa.checked,
    };

    try {
      const response = await fetch(base_url + "ventas/registrarVenta", {
        method: "POST",
        body: JSON.stringify(datos),
      });
      const res = await response.json();

      alertaPersonalizada(res.type, res.msg);

      if (res.type == "success") {        
        localStorage.removeItem(nombreKey);
        limpiarCamposVenta();
        limpiarTablaProductos();

        if (typeof tblHistorial !== "undefined") {
          tblHistorial.ajax.reload(null, false); // El 'false' mantiene la página actual de la tabla
        }

        verReporte(res.idVenta);

        setTimeout(() => {
          window.location.reload();
        }, 2000);
      }
    } catch (error) {
      console.error("Error:", error);
      alertaPersonalizada("error", "Error en el servidor");
    }
  });

  //cargar datos con el plugin datatables
  tblHistorial = $("#tblHistorial").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 10,
    deferRender: true, // Renderizado asíncrono de filas
    ajax: {
      url: base_url + "ventas/listarServerSide",
      type: "POST",
    },
    columns: [
      { data: "fecha" },
      { data: "hora" },
      { data: "total" },
      { data: "nombre" },
      { data: "serie" },
      { data: "metodo" },
      { data: "ganancia" },
      { data: "acciones" },
    ],
    language: { url: base_url + "assets/js/espanol.json" },
    dom,
    buttons,
    responsive: true,
    order: [[0, "desc"]], // Ordenar por fecha por defecto
  });

  //calcular cambio
  pagar_con.addEventListener("keyup", function (e) {
    if (totalPagar.value != "") {
      let totalDescuento = descuento.value != "" ? descuento.value : 0;
      let totalCambio =
        parseFloat(e.target.value) -
        (parseFloat(totalPagarHidden.value) - parseFloat(totalDescuento));
      cambio.value = totalCambio.toFixed(2);
    }
  });

  //calcular descuento
  descuento.addEventListener("keyup", function (e) {
    if (totalPagar.value != "") {
      let nuevoTotal =
        parseFloat(totalPagarHidden.value) - parseFloat(e.target.value);
      totalPagar.value = nuevoTotal.toFixed(2);
      let nuevoCambio = parseFloat(pagar_con.value) - parseFloat(nuevoTotal);
      cambio.value = nuevoCambio.toFixed(2);
    }
  });

  if (btnExcel) {
    btnExcel.addEventListener("click", () => {
      const desde = document.querySelector("#desde").value;
      const hasta = document.querySelector("#hasta").value;

      if (!desde || !hasta) {
        alertaPersonalizada("warning", "Seleccione un rango de fechas");
        return;
      }

      const url = `${base_url}ventas/exportarExcel?desde=${desde}&hasta=${hasta}`;
      window.open(url, "_blank");
    });
  }
});

//cargar productos
function mostrarProducto() {
  const contenedor = document.querySelector("#tblNuevaVenta tbody");
  if (localStorage.getItem(nombreKey) == null || listaCarrito.length == 0) {
    contenedor.innerHTML =
      '<tr><td colspan="5" class="text-center">CARRITO VACIO</td></tr>';
    return;
  }

  // Usamos Fetch para obtener los datos del servidor
  fetch(base_url + "productos/mostrarDatos", {
    method: "POST",
    body: JSON.stringify(listaCarrito),
  })
    .then((res) => res.json())
    .then((res) => {
      let html = "";
      res.productos.forEach((p) => {
        html += `<tr>
                <td>${p.nombre}</td>
                <td width="150"><input type="number" class="form-control inputPrecio" data-id="${p.id}" value="${p.precio_venta}"></td>
                <td width="100"><input type="number" class="form-control inputCantidad" data-id="${p.id}" value="${p.cantidad}"></td>
                <td>${p.subTotalVenta}</td>
                <td><button class="btn btn-sm btn-danger" onclick="eliminarProducto(${p.id})"><i class="fas fa-trash"></i></button></td>
            </tr>`;
      });
      contenedor.innerHTML = html;
      totalPagar.value = res.totalVenta;
      totalPagarHidden.value = res.totalVentaSD;

      // Reinicializar eventos de inputs
      agregarCantidad();
      agregarPrecioVenta();
    });
}

function verReporte(idVenta) {
  Swal.fire({
    title: "¿Qué acción desea realizar?",
    showDenyButton: true,
    showCancelButton: true,
    confirmButtonText: "Ticket",
    denyButtonText: "Factura",
    cancelButtonText: "Enviar correo",
    cancelButtonColor: "#3085d6",
    confirmButtonColor: "#28a745",
    denyButtonColor: "#ffc107",
    footer:
      '<button id="btnCerrar" class="swal2-cancel swal2-styled" style="background-color: #d33;">Cerrar</button>',
    didOpen: () => {
      // Evento para el botón personalizado "Cerrar"
      document.getElementById("btnCerrar").addEventListener("click", () => {
        Swal.close();
      });
    },
  }).then((result) => {
    if (result.isConfirmed) {
      // Opción 1: Ticket
      const ruta = base_url + "ventas/reporte/ticked/" + idVenta;
      fetch(ruta).then((response) => {
        if (response.ok) {
          alertaPersonalizada("success", "Orden de impresión enviada");
        } else {
          alertaPersonalizada("error", "Error al conectar con la impresora");
        }
      });
    } else if (result.isDenied) {
      // Opción 2: Factura
      const ruta = base_url + "ventas/reporte/factura/" + idVenta;
      window.open(ruta, "_blank");
    } else if (result.dismiss === Swal.DismissReason.cancel) {
      // Opción 3: Enviar correo
      Swal.fire({
        title: "Enviar ticket de venta al correo?",
        text: "Asegúrese de que existe el correo!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, enviar!",
        cancelButtonText: "Cancelar",
      }).then((resultEmail) => {
        if (resultEmail.isConfirmed) {
          const url = base_url + "ventas/enviarCorreo/" + idVenta;
          const http = new XMLHttpRequest();
          http.open("GET", url, true);
          http.send();
          http.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
              const res = JSON.parse(this.responseText);
              alertaPersonalizada(res.type, res.msg);
            }
          };
        }
      });
    }
    // Opción 4: Cerrar (se maneja automáticamente con el botón del footer)
  });
}

function anularVenta(idVenta) {
  Swal.fire({
    title: "Esta seguro de anular la venta?",
    text: "El stock de los productos cambiarán!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Si, Anular!",
  }).then((result) => {
    if (result.isConfirmed) {
      const url = base_url + "ventas/anular/" + idVenta;
      //hacer una instancia del objeto XMLHttpRequest
      const http = new XMLHttpRequest();
      //Abrir una Conexion - POST - GET
      http.open("GET", url, true);
      //Enviar Datos
      http.send();
      //verificar estados
      http.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
          const res = JSON.parse(this.responseText);
          alertaPersonalizada(res.type, res.msg);
          if (res.type == "success") {
            tblHistorial.ajax.reload();
          }
        }
      };
    }
  });
}

function limpiarCamposVenta() {
  // 1. Inputs de búsqueda (ajustados a tu HTML)
  if (document.querySelector("#buscarProductoCodigo"))
    document.querySelector("#buscarProductoCodigo").value = "";
  if (document.querySelector("#buscarProductoNombreVentas"))
    document.querySelector("#buscarProductoNombreVentas").value = "";

  // 2. Cliente
  document.querySelector("#buscarCliente").value = "";
  document.querySelector("#telefonoCliente").value = "";
  document.querySelector("#idCliente").value = "1"; // Volvemos al cliente genérico por defecto

  // CORRECCIÓN CRÍTICA: direcciónCliente es un <li>, se limpia con innerHTML
  document.querySelector("#direccionCliente").innerHTML =
    '<i class="fas fa-home"></i>';

  // 3. Pagos y totales
  document.querySelector("#descuento").value = "";
  document.querySelector("#pagar_con").value = "";
  document.querySelector("#cambio").value = "";
  document.querySelector("#totalPagar").value = "";
  document.querySelector("#totalPagarHidden").value = "";
}

/**
 * Función para vaciar la tabla de productos
 */
function limpiarTablaProductos() {
  tblNuevaVenta.innerHTML =
    '<tr><td colspan="5" class="text-center">CARRITO VACIO</td></tr>';
}

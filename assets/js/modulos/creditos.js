let tblCreditos, tblAbonos;
const idCredito = document.querySelector("#idCredito");
const cliente = document.querySelector("#buscarCliente");
const telefonoCliente = document.querySelector("#telefonoCliente");
const direccionCliente = document.querySelector("#direccionCliente");
const abonado = document.querySelector("#abonado");
const restante = document.querySelector("#restante");
const fecha = document.querySelector("#fecha");
const monto_total = document.querySelector("#monto_total");
const monto_abonar = document.querySelector("#monto_abonar");
const btnAccion = document.querySelector("#btnAccion");

const nuevoAbono = document.querySelector("#nuevoAbono");
const modalAbono = new bootstrap.Modal("#modalAbono");
const errorCliente = document.querySelector("#errorCliente");

//para filtro por rango de fechas
const desde = document.querySelector("#desde");
const hasta = document.querySelector("#hasta");

document.addEventListener("DOMContentLoaded", function () {
  //cargar datos con el plugin datatables
  tblCreditos = $("#tblCreditos").DataTable({
    ajax: {
      url: base_url + "creditos/listar",
      dataSrc: "",
    },
    columns: [
      { data: "fecha" },
      { data: "monto" },
      { data: "nombre" },
      { data: "restante" },
      { data: "abonado" },
      { data: "venta" },
      { data: "estado" },
    ],
    language: {
      url: base_url + "assets/js/espanol.json",
    },
    dom,
    buttons,
    responsive: true,
    order: [[0, "ASC"]],
    deferRender: true, // Optimización para listas largas
    processing: true, // Indica al usuario que está cargando
  });

  //autocomplete clientes
  $("#buscarCliente").autocomplete({
    delay: 400, // No satura el servidor con cada tecla
    source: function (request, response) {
      $.ajax({
        url: base_url + "creditos/buscar",
        dataType: "json",
        data: { term: request.term },
        success: function (data) {
          response(data);
          errorCliente.textContent =
            data.length > 0 ? "" : "EL CLIENTE NO TIENE CREDITOS";
        },
      });
    },
    minLength: 2,
    select: function (event, ui) {
      telefonoCliente.value = ui.item.telefono;
      direccionCliente.innerHTML = ui.item.direccion;
      idCredito.value = ui.item.id;
      abonado.value = ui.item.abonado;
      restante.value = ui.item.restante;
      monto_total.value = ui.item.monto;
      fecha.value = ui.item.fecha;
      monto_abonar.focus();
    },
  });

  //levantar modal para agregar abono
  nuevoAbono.addEventListener("click", function () {
    idCredito.value = "";
    telefonoCliente.value = "";
    cliente.value = "";
    direccionCliente.innerHTML = "";
    abonado.value = "";
    restante.value = "";
    monto_total.value = "";
    fecha.value = "";
    monto_abonar.value = "";
    modalAbono.show();
  });

  btnAccion.addEventListener("click", function () {
    if (monto_abonar.value == "") {
      alertaPersonalizada("warning", "INGRESE EL MONTO");
    } else if (
      idCredito.value == "" &&
      cliente.value == "" &&
      telefonoCliente.value == ""
    ) {
      alertaPersonalizada("warning", "BUSCA Y SELECCIONA CLIENTE");
    } else if (parseFloat(restante.value) < parseFloat(monto_abonar.value)) {
      alertaPersonalizada("warning", "INGRESE MENOR A RESTANTE");
    } else {
      //const url = base_url + 'creditos/registrarAbono';
      const url = base_url + "creditos/abonarGlobalmente";
      //hacer una instancia del objeto XMLHttpRequest
      const http = new XMLHttpRequest();
      //Abrir una Conexion - POST - GET
      http.open("POST", url, true);
      //Enviar Datos
      http.send(
        JSON.stringify({
          idCredito: idCredito.value,
          monto_abonar: monto_abonar.value,
        })
      );
      //verificar estados
      http.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
          try {
            const res = JSON.parse(this.responseText);
            alertaPersonalizada(res.type, res.msg);
            if (res.type == "success") {
              const tempIdCliente = idCredito.value;
              modalAbono.hide();
              tblCreditos.ajax.reload();
              tblAbonos.ajax.reload();
              monto_abonar.value = "";
              //
              Swal.fire({
                title: "¿Desea imprimir el comprobante de abono?",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, imprimir",
                cancelButtonText: "No, cerrar",
              }).then((result) => {
                if (result.isConfirmed) {
                  const ruta = base_url + "creditos/reporte/" + tempIdCliente;
                  window.open(ruta, "_blank");
                }
              });
            }
          } catch (e) {
            console.error(
              "Error al leer respuesta del servidor. Respuesta recibida:",
              this.responseText
            );
            alertaPersonalizada(
              "error",
              "Error en el servidor: Revisa la conexión de la impresora."
            );
          }
        } else {
          alertaPersonalizada(
            "error",
            "Error de red o servidor no disponible."
          );
        }
      };
    }
  });

  //cargar datos con el plugin datatables
  tblAbonos = $("#tblAbonos").DataTable({
    ajax: {
      url: base_url + "creditos/listarAbonos",
      dataSrc: "",
    },
    columns: [
      { data: "fecha" },
      { data: "abono" },
      { data: "credito" },
      { data: "num_identidad" },
    ],
    language: {
      url: base_url + "assets/js/espanol.json",
    },
    dom,
    buttons,
    responsive: true,
    order: [[0, "ASC"]],
  });

  //filtro rango de fechas
  desde.addEventListener("change", function () {
    tblCreditos.draw();
  });
  hasta.addEventListener("change", function () {
    tblCreditos.draw();
  });

  $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    var FilterStart = desde.value;
    var FilterEnd = hasta.value;
    var DataTableStart = data[0].trim();
    var DataTableEnd = data[0].trim();
    if (FilterStart == "" || FilterEnd == "") {
      return true;
    }
    if (DataTableStart >= FilterStart && DataTableEnd <= FilterEnd) {
      return true;
    } else {
      return false;
    }
  });
});

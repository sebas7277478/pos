-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 21-05-2025 a las 20:00:58
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.2.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abonos`
--

DROP TABLE IF EXISTS `abonos`;
CREATE TABLE IF NOT EXISTS `abonos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `abono` decimal(10,2) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `apertura` int NOT NULL DEFAULT '1',
  `id_credito` int NOT NULL,
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_credito` (`id_credito`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `abonos`
--

INSERT INTO `abonos` (`id`, `abono`, `fecha`, `apertura`, `id_credito`, `id_usuario`) VALUES
(1, 11200.00, '2025-05-20 23:16:33', 0, 1, 1),
(2, 20000.00, '2025-05-20 23:16:33', 0, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abonos_compras`
--

DROP TABLE IF EXISTS `abonos_compras`;
CREATE TABLE IF NOT EXISTS `abonos_compras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `abono` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `id_credito_compra` int NOT NULL,
  `apertura` int NOT NULL DEFAULT '1',
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `abonos_compras`
--

INSERT INTO `abonos_compras` (`id`, `abono`, `fecha`, `id_credito_compra`, `apertura`, `id_usuario`) VALUES
(1, 5000.00, '2025-05-20', 1, 0, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acceso`
--

DROP TABLE IF EXISTS `acceso`;
CREATE TABLE IF NOT EXISTS `acceso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evento` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `ip` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `detalle` text COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `acceso`
--

INSERT INTO `acceso` (`id`, `evento`, `ip`, `detalle`, `fecha`) VALUES
(1, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-02-14 00:30:07'),
(2, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-02-14 00:30:10'),
(3, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-02-14 00:32:23'),
(4, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-02-14 00:32:40'),
(5, 'Cierre de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-02-14 01:31:12'),
(6, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-02-14 01:31:33'),
(7, 'Cierre de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-02-14 22:42:47'),
(8, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-02-14 22:43:09'),
(9, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-03-13 02:22:29'),
(10, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', '2025-03-20 16:12:40'),
(11, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 19:32:35'),
(12, 'Cierre de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 19:32:52'),
(13, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 19:36:50'),
(14, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 19:48:58'),
(15, 'Cierre de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 22:18:38'),
(16, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 22:18:58'),
(17, 'Cierre de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 22:19:33'),
(18, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 22:23:23'),
(19, 'Cierre de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 23:15:52'),
(20, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 23:16:12'),
(21, 'Cierre de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 23:16:37'),
(22, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 23:16:55'),
(23, 'Cierre de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 23:22:16'),
(24, 'Inicio de Sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-20 23:49:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apartados`
--

DROP TABLE IF EXISTS `apartados`;
CREATE TABLE IF NOT EXISTS `apartados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `productos` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_create` date DEFAULT NULL,
  `fecha_apartado` datetime NOT NULL,
  `fecha_retiro` datetime NOT NULL,
  `abono` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `color` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `estado` int NOT NULL DEFAULT '1',
  `id_cliente` int NOT NULL,
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cajas`
--

DROP TABLE IF EXISTS `cajas`;
CREATE TABLE IF NOT EXISTS `cajas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `monto_inicial` decimal(10,2) NOT NULL,
  `fecha_apertura` date NOT NULL,
  `fecha_cierre` date DEFAULT NULL,
  `monto_final` decimal(10,2) DEFAULT NULL,
  `total_ventas` int DEFAULT NULL,
  `egresos` decimal(10,2) DEFAULT NULL,
  `gastos` decimal(10,2) DEFAULT NULL,
  `estado` int NOT NULL DEFAULT '1',
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cajas`
--

INSERT INTO `cajas` (`id`, `monto_inicial`, `fecha_apertura`, `fecha_cierre`, `monto_final`, `total_ventas`, `egresos`, `gastos`, `estado`, `id_usuario`) VALUES
(1, 100000.00, '2025-05-20', '2025-05-20', 151200.00, 4, 25000.00, 0.00, 0, 1),
(2, 100000.00, '2025-05-20', '2025-05-20', 7200.00, 2, 0.00, 0.00, 0, 5),
(3, 50000.00, '2025-05-20', NULL, NULL, NULL, NULL, NULL, 1, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

DROP TABLE IF EXISTS `categorias`;
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categoria` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `categoria`, `fecha`, `estado`) VALUES
(1, 'alimentos', '2025-02-14 02:04:05', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identidad` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `num_identidad` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `correo` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `identidad`, `num_identidad`, `nombre`, `telefono`, `correo`, `direccion`, `fecha`, `estado`) VALUES
(1, 'CC', '12054897', 'Jose Luis', '8954874', NULL, 'san juan', '2025-02-27 18:46:38', 1),
(2, 'CC', '4587125', 'Juan Perez', '32654879', NULL, 'santo domingo', '2025-02-27 18:44:13', 1),
(3, 'CC', '1025487985', 'JORGE', '2352221351351', 'sebastianhdg74@gmail.com', '<p>SAN PEDRO</p>', '2025-05-20 23:20:49', 1),
(4, 'CC', '58974989', 'juan', '89745521', NULL, '<p>cali</p>', '2025-02-27 20:26:08', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

DROP TABLE IF EXISTS `compras`;
CREATE TABLE IF NOT EXISTS `compras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `productos` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `metodo` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `serie` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `estado` int NOT NULL DEFAULT '1',
  `apertura` int NOT NULL DEFAULT '1',
  `id_proveedor` int NOT NULL,
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_proveedor` (`id_proveedor`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `compras`
--

INSERT INTO `compras` (`id`, `productos`, `total`, `fecha`, `hora`, `metodo`, `serie`, `estado`, `apertura`, `id_proveedor`, `id_usuario`) VALUES
(1, '[{\"id\":1,\"nombre\":\"arroz\",\"precio\":\"20000.00\",\"cantidad\":1}]', 20000.00, '2025-05-20', '16:43:34', 'CONTADO', '00124578', 1, 0, 1, 1),
(2, '[{\"id\":5,\"nombre\":\"papas\",\"precio\":\"500.00\",\"cantidad\":\"10\"}]', 5000.00, '2025-05-20', '16:44:52', 'CREDITO', '00004589', 1, 0, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE IF NOT EXISTS `configuracion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ruc` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `correo` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` text COLLATE utf8mb4_general_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_general_ci NOT NULL,
  `impuesto` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `ruc`, `nombre`, `telefono`, `correo`, `direccion`, `mensaje`, `impuesto`) VALUES
(1, '23999999999', 'EASY CONTA', '900897537', 'sebastianhdg74@gmail.com', 'COLOMBIA', '<p>GRACIAS POR SU PREFERENCIA</p>', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizaciones`
--

DROP TABLE IF EXISTS `cotizaciones`;
CREATE TABLE IF NOT EXISTS `cotizaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `productos` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `metodo` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `validez` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `descuento` decimal(10,2) NOT NULL DEFAULT '0.00',
  `id_cliente` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `creditos`
--

DROP TABLE IF EXISTS `creditos`;
CREATE TABLE IF NOT EXISTS `creditos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `monto` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` int NOT NULL DEFAULT '1',
  `id_venta` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_venta` (`id_venta`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `creditos`
--

INSERT INTO `creditos` (`id`, `monto`, `fecha`, `hora`, `estado`, `id_venta`) VALUES
(1, 31200.00, '2025-05-20', '16:57:47', 0, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `creditos_compras`
--

DROP TABLE IF EXISTS `creditos_compras`;
CREATE TABLE IF NOT EXISTS `creditos_compras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `monto` int NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado_credito` int NOT NULL DEFAULT '1',
  `idproveedor` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_compra` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `creditos_compras`
--

INSERT INTO `creditos_compras` (`id`, `monto`, `fecha`, `hora`, `estado_credito`, `idproveedor`, `id_usuario`, `id_compra`) VALUES
(1, 5000, '2025-05-20', '16:44:52', 0, 1, 1, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_apartado`
--

DROP TABLE IF EXISTS `detalle_apartado`;
CREATE TABLE IF NOT EXISTS `detalle_apartado` (
  `id` int NOT NULL AUTO_INCREMENT,
  `monto` decimal(10,2) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `apertura` int NOT NULL DEFAULT '1',
  `id_apartado` int NOT NULL,
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos`
--

DROP TABLE IF EXISTS `gastos`;
CREATE TABLE IF NOT EXISTS `gastos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `foto` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `apertura` int NOT NULL DEFAULT '1',
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

DROP TABLE IF EXISTS `inventario`;
CREATE TABLE IF NOT EXISTS `inventario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `movimiento` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `accion` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `cantidad` int NOT NULL,
  `stock_actual` int NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_producto` int NOT NULL,
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_producto` (`id_producto`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`id`, `movimiento`, `accion`, `cantidad`, `stock_actual`, `fecha`, `id_producto`, `id_usuario`) VALUES
(1, 'Venta N°: 1', 'salida', 1, 8, '2025-05-20 21:42:19', 1, 1),
(2, 'Compra N°: 1 - CONTADO', 'entrada', 1, 9, '2025-05-20 21:43:34', 1, 1),
(3, 'Compra N°: 2 - CREDITO', 'entrada', 10, 20, '2025-05-20 21:44:52', 5, 1),
(4, 'Venta N°: 2', 'salida', 1, 19, '2025-05-20 21:57:47', 5, 1),
(5, 'Venta N°: 2', 'salida', 1, 8, '2025-05-20 21:57:47', 1, 1),
(6, 'Venta N°: 3', 'salida', 2, 6, '2025-05-20 22:27:18', 1, 1),
(7, 'Venta N°: 4', 'salida', 1, 5, '2025-05-20 23:15:35', 1, 1),
(8, 'Venta N°: 5', 'salida', 3, 16, '2025-05-20 23:17:48', 5, 5),
(9, 'Venta N°: 6', 'salida', 3, 13, '2025-05-20 23:21:32', 5, 5),
(10, 'Venta N°: 7', 'salida', 1, 4, '2025-05-20 23:50:08', 1, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medidas`
--

DROP TABLE IF EXISTS `medidas`;
CREATE TABLE IF NOT EXISTS `medidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `medida` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nombre_corto` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `medidas`
--

INSERT INTO `medidas` (`id`, `medida`, `nombre_corto`, `fecha`, `estado`) VALUES
(1, 'kilogramos', 'k', '2025-02-14 02:03:55', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

DROP TABLE IF EXISTS `permisos`;
CREATE TABLE IF NOT EXISTS `permisos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id`, `nombre`) VALUES
(1, 'usuarios'),
(2, 'roles'),
(3, 'configuracion'),
(4, 'log de acceso'),
(5, 'medidas'),
(6, 'categorias'),
(7, 'productos'),
(8, 'clientes'),
(9, 'proveedores'),
(10, 'cajas'),
(11, 'compras'),
(12, 'ventas'),
(13, 'credito ventas'),
(14, 'cotizaciones'),
(15, 'apartados'),
(16, 'inventario');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

DROP TABLE IF EXISTS `productos`;
CREATE TABLE IF NOT EXISTS `productos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `vencimiento` date NOT NULL,
  `cantidad` int NOT NULL DEFAULT '0',
  `foto` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` int NOT NULL DEFAULT '1',
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ventas` int NOT NULL DEFAULT '0',
  `id_medida` int NOT NULL,
  `id_categoria` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_medida` (`id_medida`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `codigo`, `descripcion`, `precio_compra`, `precio_venta`, `vencimiento`, `cantidad`, `foto`, `estado`, `fecha`, `ventas`, `id_medida`, `id_categoria`) VALUES
(1, '5631165', 'arroz', 20000.00, 30000.00, '0000-00-00', 4, NULL, 1, '2025-05-20 23:50:08', 29, 1, 1),
(2, '5688747', 'azucar', 16000.00, 21000.00, '0000-00-00', 4, NULL, 1, '2025-02-27 23:24:30', 12, 1, 1),
(3, '4587412', 'leche', 6000.00, 11000.00, '2025-03-26', 7, NULL, 1, '2025-05-20 19:43:29', 8, 1, 1),
(4, '751273778478', 'nuevo', 5000.00, 10000.00, '0000-00-00', 5, NULL, 1, '2025-02-27 22:25:35', 0, 1, 1),
(5, '5689742', 'papas', 500.00, 1200.00, '2025-06-10', 13, NULL, 1, '2025-05-20 23:21:32', 8, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedor`
--

DROP TABLE IF EXISTS `proveedor`;
CREATE TABLE IF NOT EXISTS `proveedor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ruc` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `correo` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedor`
--

INSERT INTO `proveedor` (`id`, `ruc`, `nombre`, `telefono`, `correo`, `direccion`, `fecha`, `estado`) VALUES
(1, '5648759', 'DistriHogar', '89745621', 'distrihogar@gmail.com', '<p>pasto</p>', '2025-02-14 02:06:11', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `permisos` text COLLATE utf8mb4_general_ci,
  `estado` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `permisos`, `estado`) VALUES
(1, 'ADMINISTRADOR', '[\"usuarios\",\"roles\",\"configuracion\",\"log de acceso\",\"medidas\",\"categorias\",\"productos\",\"clientes\",\"proveedores\",\"cajas\",\"compras\",\"ventas\",\"credito ventas\",\"cotizaciones\",\"apartados\", \"inventario\"]', 1),
(2, 'SUPERVISOR', '[\"medidas\",\"categorias\",\"productos\",\"clientes\",\"proveedores\",\"cajas\",\"compras\",\"ventas\",\"credito ventas\",\"cotizaciones\",\"apartados\", \"inventario\"]', 1),
(3, 'VENDEDOR', '[\"clientes\",\"cajas\",\"ventas\",\"credito ventas\"]', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `correo` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `perfil` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clave` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado` int NOT NULL DEFAULT '1',
  `rol` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `rol` (`rol`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `correo`, `telefono`, `direccion`, `perfil`, `clave`, `token`, `fecha`, `estado`, `rol`) VALUES
(1, 'ANGEL', 'SIFUENTES', 'lovenaju2@gmail.com', '900897537', 'LIMA PERU', NULL, '$2y$10$PBVfztrP.ynI6stFCV3vtOtF9asUF4yIfUjTQ5aXhE1xZEzb0DH3a', NULL, '2022-09-20 19:46:47', 1, 1),
(2, 'SEGUNDO', 'USUARIO', 'segundousers@gmail.com', '79898987', 'PERU', NULL, '$2y$10$iEqpvM8zHShRgJPKwgkIeeO1MqG6b6Ka2Y7vhJtOXe4KShEv3pN8i', NULL, '2023-06-04 01:03:03', 0, 1),
(3, 'NUEVO', 'USUARIO', 'vendedor@gmail.com', '98699777', 'peru', NULL, '$2y$10$/k36L9B9SxvaX95g.LBf5.WEBQ4.zrZjBTyIyHBodVt9Ucl5LnmwS', NULL, '2025-02-11 02:10:34', 0, 2),
(4, 'jose', 'perez', 'jose@gmail.com', '1234566789', 'san juan', NULL, '$2y$10$EpMOwiZKkY306p4Zp1QqKerrsdQXG3sg0tTYvu1EuiifHd/p3fGnq', NULL, '2025-02-11 02:11:20', 1, 3),
(5, 'Sebastian', 'Delgado', 'sebastianhsdg@gmail.com', '3233915398', 'la laguna', NULL, '$2y$10$6R5C5058.BYVp.JUhmJceuhi4nfkWyiOaToEUuR2RyfulkTai.zaq', NULL, '2025-05-20 22:17:35', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

DROP TABLE IF EXISTS `ventas`;
CREATE TABLE IF NOT EXISTS `ventas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `productos` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `metodo` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `descuento` decimal(10,2) NOT NULL DEFAULT '0.00',
  `serie` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `pago` decimal(10,2) NOT NULL,
  `estado` int NOT NULL DEFAULT '1',
  `apertura` int NOT NULL DEFAULT '1',
  `id_cliente` int NOT NULL,
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `productos`, `total`, `fecha`, `hora`, `metodo`, `descuento`, `serie`, `pago`, `estado`, `apertura`, `id_cliente`, `id_usuario`) VALUES
(1, '[{\"id\":1,\"nombre\":\"arroz\",\"precio\":\"30000.00\",\"cantidad\":1}]', 30000.00, '2025-05-20', '16:42:19', 'CONTADO', 0.00, '00000001', 50000.00, 1, 0, 1, 1),
(2, '[{\"id\":5,\"nombre\":\"papas\",\"precio\":\"1200.00\",\"cantidad\":1},{\"id\":1,\"nombre\":\"arroz\",\"precio\":\"30000.00\",\"cantidad\":1}]', 31200.00, '2025-05-20', '16:57:47', 'CREDITO', 0.00, '00000002', 31200.00, 1, 0, 3, 1),
(3, '[{\"id\":1,\"nombre\":\"arroz\",\"precio\":\"30000.00\",\"cantidad\":\"2\"}]', 60000.00, '2025-05-20', '17:27:18', 'CONTADO', 0.00, '00000003', 70000.00, 1, 0, 3, 1),
(4, '[{\"id\":1,\"nombre\":\"arroz\",\"precio\":\"30000.00\",\"cantidad\":1}]', 30000.00, '2025-05-20', '18:15:35', 'CONTADO', 0.00, '00000004', 30000.00, 1, 0, 3, 1),
(5, '[{\"id\":5,\"nombre\":\"papas\",\"precio\":\"1200.00\",\"cantidad\":\"3\"}]', 3600.00, '2025-05-20', '18:17:48', 'CONTADO', 0.00, '00000005', 3600.00, 1, 0, 3, 5),
(6, '[{\"id\":5,\"nombre\":\"papas\",\"precio\":\"1200.00\",\"cantidad\":\"3\"}]', 3600.00, '2025-05-20', '18:21:32', 'CONTADO', 0.00, '00000006', 3600.00, 1, 0, 3, 5),
(7, '[{\"id\":1,\"nombre\":\"arroz\",\"precio\":\"30000.00\",\"cantidad\":1}]', 30000.00, '2025-05-20', '18:50:08', 'CONTADO', 0.00, '00000007', 30000.00, 1, 1, 3, 5);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `abonos`
--
ALTER TABLE `abonos`
  ADD CONSTRAINT `abonos_ibfk_1` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id`);

--
-- Filtros para la tabla `apartados`
--
ALTER TABLE `apartados`
  ADD CONSTRAINT `apartados_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`);

--
-- Filtros para la tabla `cajas`
--
ALTER TABLE `cajas`
  ADD CONSTRAINT `cajas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id`),
  ADD CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD CONSTRAINT `cotizaciones_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`);

--
-- Filtros para la tabla `creditos`
--
ALTER TABLE `creditos`
  ADD CONSTRAINT `creditos_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`);

--
-- Filtros para la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD CONSTRAINT `gastos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`),
  ADD CONSTRAINT `inventario_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id`),
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`id_medida`) REFERENCES `medidas` (`id`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

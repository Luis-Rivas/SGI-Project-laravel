<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Venta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class InformeVentasController extends Controller
{
    //
    /*
        SELECT X1.id_venta as idVenta,SUM(X2.subtotal_detalle_venta) as total_venta,
        X1.fecha_venta AS fecha_venta
        FROM venta AS X1
        INNER JOIN detalleventa AS X2 ON X1.id_venta = X2.id_venta
        WHERE Month(X1.fecha_venta) = 1
        GROUP BY(X1.id_venta);

        ===============================
        SELECT X1.id_venta as idVenta,SUM(X2.subtotal_detalle_venta) as total_venta,
        X1.fecha_venta AS fecha_venta
        FROM venta AS X1
        INNER JOIN detalleventa AS X2 ON X1.id_venta = X2.id_venta
        WHERE Month(X1.fecha_venta)IN (1,2,3,4,5) AND YEAR(X1.fecha_venta) = 2021
        GROUP BY(X1.id_venta);
        ===================================
        SELECT SUM(X2.subtotal_detalle_venta) as total_venta,
        Month(X1.fecha_venta) AS mes_venta
        FROM venta AS X1
        INNER JOIN detalleventa AS X2 ON X1.id_venta = X2.id_venta
        WHERE Month(X1.fecha_venta)IN (1,2,3,4,5) AND YEAR(X1.fecha_venta) = 2021
        GROUP BY(Month(X1.fecha_venta));
    */
    protected $nombre_meses = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"];
    public function obtenerVentasTotalesPorFecha(Request $request)
    {
        /* $validator = Validator::make($request->all(),[
             "filtro_meses"=>["array"],
             "anio_filtro"=>["string"],
         ]);
         if($validator->fails()){
             response()->json([
                 "status"=>false,
                 "errores"=>$validator->errors()->all(),
             ],500);
         }*/
        //$datos_filtro = $validator->validated();
        $filtro_meses = $request->input("filtro_meses");//$datos_filtro["filtro_meses"];//("filtro_meses");//$parametros["filtro_meses"]; 
        $anio_filtro = $request->input("anio_filtro");//$datos_filtro["anio_filtro"];//$parametros["anio_filtro"];
        $ventas = [];
        try {
            if (isset($filtro_meses) && sizeof($filtro_meses) > 0) {
                $ventas = Venta::selectRaw('SUM(venta.total_venta) as total_venta, MONTH(venta.fecha_venta) AS mes_venta, COUNT(venta.id_venta) as num_ventas')
                    ->whereIn(DB::raw('MONTH(venta.fecha_venta)'), $filtro_meses)
                    ->whereYear('venta.fecha_venta', $anio_filtro)
                    ->groupBy(DB::raw('MONTH(venta.fecha_venta)'))
                    ->get();
                $control = "1";

                $filtro_meses_str = '(' . implode(',', $filtro_meses) . ')';
                $query_n_ventas = DB::select('SELECT COUNT(id_venta) as cantidad_ventas, sum(total_venta) AS total_ventas FROM venta WHERE YEAR(fecha_venta) =' . $anio_filtro . ' AND MONTH(fecha_venta) in' . $filtro_meses_str . ';');

                //Consulta de los productos
                $filtro_meses = $request->input("filtro_meses") ?? '(1,2,3,4,5,6,7,8,9,10,11,12)';//$datos_filtro["filtro_meses"];//("filtro_meses");//$parametros["filtro_meses"]; 
                $anio_filtro = $request->input("anio_filtro") ?? date('Y');
                //formatear filtro_meses
                $filtro_meses = '(' . implode(',', $filtro_meses) . ')';
                $query_productos = DB::select(
                    'SELECT 
                        producto.nombre_producto as nombre,
                        SUM(COALESCE(detalleventa.cantidad_producto, 0) + COALESCE(detallecredito.cantidad_producto_credito, 0)) AS cantidad_producto,
                        SUM(COALESCE(detalleventa.subtotal_detalle_venta, 0) + COALESCE(detallecredito.subtotal_detalle_credito, 0)) AS ingresos_producto
                    FROM producto
                    LEFT JOIN detalleventa ON producto.codigo_barra_producto = detalleventa.codigo_barra_producto
                    LEFT JOIN detallecredito ON producto.codigo_barra_producto = detallecredito.codigo_barra_producto
                    LEFT JOIN venta ON detalleventa.id_venta = venta.id_venta
                    where YEAR(venta.fecha_venta) = ' . $anio_filtro . '
                        and month(venta.fecha_venta) in ' . $filtro_meses . '
                    GROUP BY producto.codigo_barra_producto
                    ORDER BY cantidad_producto DESC
                    LIMIT 10;'
                );
            } else {

                $filtro_meses_str = '(' . implode(',', $filtro_meses) . ')';
                $query_n_ventas = DB::select('SELECT COUNT(id_venta) as cantidad_ventas, sum(total_venta) AS total_ventas, COUNT(venta.id_venta) as num_ventas FROM venta WHERE YEAR(fecha_venta) =' . $anio_filtro . ' AND MONTH(fecha_venta) in' . $filtro_meses_str . ';');

                //Consulta de los productos
                $filtro_meses = $request->input("filtro_meses") ?? '(1,2,3,4,5,6,7,8,9,10,11,12)';//$datos_filtro["filtro_meses"];//("filtro_meses");//$parametros["filtro_meses"]; 
                $anio_filtro = $request->input("anio_filtro") ?? date('Y');
                //formatear filtro_meses
                $filtro_meses = '(' . implode(',', $filtro_meses) . ')';
                $query_productos = DB::select(
                    'SELECT 
                        producto.nombre_producto as nombre,
                        SUM(COALESCE(detalleventa.cantidad_producto, 0) + COALESCE(detallecredito.cantidad_producto_credito, 0)) AS cantidad_producto,
                        SUM(COALESCE(detalleventa.subtotal_detalle_venta, 0) + COALESCE(detallecredito.subtotal_detalle_credito, 0)) AS ingresos_producto
                    FROM producto
                    LEFT JOIN detalleventa ON producto.codigo_barra_producto = detalleventa.codigo_barra_producto
                    LEFT JOIN detallecredito ON producto.codigo_barra_producto = detallecredito.codigo_barra_producto
                    LEFT JOIN venta ON detalleventa.id_venta = venta.id_venta
                    where YEAR(venta.fecha_venta) = ' . $anio_filtro . '
                        and month(venta.fecha_venta) in ' . $filtro_meses . '
                    GROUP BY producto.codigo_barra_producto
                    ORDER BY cantidad_producto DESC
                    LIMIT 10;'
                );


                $ventas = Venta::selectRaw('SUM(venta.total_venta) as total_venta, MONTH(venta.fecha_venta) AS mes_venta')
                    ->whereYear('venta.fecha_venta', $anio_filtro)
                    ->groupBy(DB::raw('MONTH(venta.fecha_venta)'))
                    ->get();

                $query_n_ventas = DB::select('SELECT COUNT(id_venta) as cantidad_ventas, sum(total_venta) AS total_ventas FROM venta WHERE YEAR(fecha_venta) = ' . $anio_filtro . ';');
            }

            foreach ($ventas as $venta) {
                for ($i = 1; $i <= 12; $i++) {
                    if ($venta["mes_venta"] == $i) {
                        $venta["nombre_mes"] = $this->nombre_meses[$i - 1];
                    }
                }
            }

            $numero_ventas = $query_n_ventas;
            $datos_productos = $query_productos;

            return response()->json([
                "status" => true,
                "mensaje" => "Exito al realizar la consulta",
                "datos_filtrados" => $ventas,
                "numero_ventas" => $numero_ventas,
                "datos_productos" => $datos_productos
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "status" => false,
                "mensaje" => $e->getMessage(),
            ], 500);
        } catch (QueryException $e) {
            return response()->json([
                "status" => false,
                "mensaje" => $e->getMessage()
            ], 500);
        }

    }


    public function informePedidosDomicilio(Request $request)
    {

        $filtro_meses = $request->input("filtro_meses") ?? '(1,2,3,4,5,6,7,8,9,10,11,12)';//$datos_filtro["filtro_meses"];//("filtro_meses");//$parametros["filtro_meses"]; 
        $anio_filtro = $request->input("anio_filtro") ?? date('Y');
        //formatear filtro_meses
        $filtro_meses = '(' . implode(',', $filtro_meses) . ')';

        $datos_pedidos = DB::select('SELECT 
        MONTH(venta.fecha_venta) AS mes, 
        COUNT(ventadomicilio.id_vd) AS total_ventas_domicilio,
        COUNT(DISTINCT hojaderuta.id_hr) AS total_hojas_ruta,
        SUM(DISTINCT hojaderuta.total) as ingresos
    FROM ventadomicilio
    LEFT JOIN venta ON ventadomicilio.id_venta = venta.id_venta
    LEFT JOIN hojaderuta ON ventadomicilio.id_hr = hojaderuta.id_hr
    where YEAR(venta.fecha_venta) = ' . $anio_filtro . '
    and month(venta.fecha_venta) in ' . $filtro_meses . '
    GROUP BY MONTH(venta.fecha_venta);');

        $total_ventas_domicilio = 0;
        $total_hojas_ruta = 0;
        $total_ingresos = 0;
        foreach ($datos_pedidos as $pedido) {
            // Sumar el valor de la columna total_ventas_domicilio a la variable total_ventas_domicilio
            $total_ventas_domicilio += $pedido->total_ventas_domicilio;
            $total_hojas_ruta += $pedido->total_hojas_ruta;
            $total_ingresos += $pedido->ingresos;
        }

        $prom_pedidos_mensuales = $total_ventas_domicilio / count($datos_pedidos);
        $prom_ingresos_mensuales = $total_ingresos / count($datos_pedidos);
        $prom_hojas_ruta = $total_hojas_ruta / count($datos_pedidos);

        return response()->json([
            'status' => 'ok',
            'message' => 'Todo bien',
            'datos_pedidos' => $datos_pedidos,
            'prom_pedidos_mensuales' => $prom_pedidos_mensuales ?? 0,
            'prom_ingresos_mensuales' => $prom_ingresos_mensuales ?? 0,
            'prom_hojas_ruta' => $prom_hojas_ruta ?? 0
        ]);
    }
}

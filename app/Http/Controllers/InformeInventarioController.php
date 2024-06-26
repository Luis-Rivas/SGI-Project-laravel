<?php

namespace App\Http\Controllers;

use App\ClasesPersonalizadas\FiltroHistorialVentasProducto as ClasesPersonalizadasFiltroHistorialVentasProducto;
use Illuminate\Http\Request;
use App\Http\Resources\InformeInventarioResource;
use App\Models\Producto;
use App\Filtros\FiltroHistorialVentasProducto;
use Illuminate\Support\Facades\DB;
use App\Filtros\FiltroProductosMasVendidos;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

use Illuminate\Database\Eloquent\Casts\Json;

class InformeInventarioController extends Controller
{
    public function index(){
        return InformeInventarioResource::collection(Producto::paginate(5));
    }
    
    public function obtenerDatosFiltradosProductoPorPrecios(Request $request,int $valorMinimo = 0,int $valorMaximo = 650){
        //$valorMinimo = $request->query("valorMinimo",0);
        //$valorMaximo = $request->query("valorMaximo",650);

        $resultados = Producto::whereRaw('cantidad_producto_disponible * precio_unitario > ?',[$valorMinimo])
        ->whereRaw('cantidad_producto_disponible * precio_unitario < ?',[$valorMaximo])
        ->paginate(5);

        return InformeInventarioResource::collection($resultados);
        
    }

    public function obtenerDatosGraficoInventarioValorado(Request $request){

        //ordenamiento y limit
        $order_by = $request->query('order_column')?? 'existencias';
        $order_type = $request->query('order_type')?? 'desc';
        $limit_rows = $request->query('cant_rows')?? 10;;

        $data = array();
        $categories = array();
        $existencias = array();
        $productos = Producto::selectRaw('nombre_producto, (cantidad_producto_disponible * precio_unitario) as valor_monetario, cantidad_producto_disponible as existencias')
        ->orderByDesc('valor_monetario')
        ->limit(10)
        ->get();
        $productos2 = DB::select(
            'SELECT 
    producto.codigo_barra_producto as nombre_producto, 
    producto.nombre_producto, 
    producto.cantidad_producto_disponible AS existencias, 
    (producto.cantidad_producto_disponible * producto.precio_unitario) AS valor_monetario,
    (SELECT COUNT(lotes.id_lote) 
     FROM lotes 
     WHERE lotes.codigo_barra_producto = producto.codigo_barra_producto 
       AND lotes.cantidad > 0 
       AND lotes.fecha_vencimiento > CURRENT_DATE) AS lotes_disponibles
FROM 
    producto 
    ORDER BY '.$order_by.' '.$order_type.'
LIMIT '.$limit_rows.';'
        );


        foreach($productos2 as $producto){
             $data[] = $producto->valor_monetario;
             $categories[] = $producto->nombre_producto;
             $existencias[] = $producto->existencias;
        }
        
        return response()->json([
            "data"=>$data,
            "categories"=>$categories,
            "existencias" => $existencias,
            "datos_inventario" => $productos2
        ]);
    }
    

    public function existenMasParametrosDeConsulta($parametrosFiltro){
        if((isset($parametrosFiltro["minTotal"])) ||(isset($parametrosFiltro["maxTotal"])) || (isset($parametrosFiltro["minTotalProducto"])) || (isset($parametrosFiltro["maxTotalProducto"]))){
            return true;
        }
        return false;
    }

    public function obtenerVentasPorProductos(Request $request)
    {
        $parametrosFiltro = $request->all();
        $managerFiltros = new FiltroHistorialVentasProducto(5);
       if(isset($parametrosFiltro["fechaInicioVenta"]) && isset($parametrosFiltro["fechaFinVenta"])){
            //$parametrosFiltro["fechaInicioVenta"] = date("Y-m-d",strtotime($parametrosFiltro["fechaInicioVenta"]));
            return $managerFiltros->filtrarPorFechas($parametrosFiltro["fechaInicioVenta"],$parametrosFiltro["fechaFinVenta"]);
        }
        else if(isset($parametrosFiltro["fechaInicioVenta"])){
            //$parametrosFiltro["fechaFinVenta"] = date("Y-m-d",strtotime($parametrosFiltro["fechaFinVenta"]));
            return $managerFiltros->filtrarPorFechaInicio(
                $parametrosFiltro["fechaInicioVenta"]
            );
        }
        else if(isset($parametrosFiltro["fechaFinVenta"])){
            return $managerFiltros->filtrarPorFechaFin(
                $parametrosFiltro["fechaFinVenta"]
            );
        }
        else if((!isset($parametrosFiltro["fechaInicioVenta"]) && !isset($parametrosFiltro["fechaFinVenta"])) && $this->existenMasParametrosDeConsulta($parametrosFiltro) ){
            return $managerFiltros->filtrarPorValorVentasCantidades(
                isset($parametrosFiltro["minTotal"]) ? $parametrosFiltro["minTotal"] : null,
                isset($parametrosFiltro["maxTotal"]) ? $parametrosFiltro["maxTotal"] : null,
                isset($parametrosFiltro["minTotalProducto"]) ? $parametrosFiltro["minTotalProducto"] : null,
                isset($parametrosFiltro["maxTotalProducto"]) ? $parametrosFiltro["maxTotalProducto"] : null
            );
        }
        else if(isset($parametrosFiltro["fechaInicioVenta"]) && isset($parametrosFiltro["fechaFinVenta"])){
            $resultado = $managerFiltros->filtroFechasValorVentasCantidades(
                $parametrosFiltro["fechaInicioVenta"],
                $parametrosFiltro["fechaFinVenta"],
                isset($parametrosFiltro["minTotal"]) ? $parametrosFiltro["minTotal"] : null,
                isset($parametrosFiltro["maxTotal"]) ? $parametrosFiltro["maxTotal"] : null,
                isset($parametrosFiltro["minTotalProducto"]) ? $parametrosFiltro["minTotalProducto"] : null,
                isset($parametrosFiltro["maxTotalProducto"]) ? $parametrosFiltro["maxTotalProducto"] : null
            );
            return $resultado;
        }
        else if(isset($parametrosFiltro["fechaInicioVenta"])){
           $resultado = $managerFiltros->filtroFechaIncioValorVentasCantidades(
                $parametrosFiltro["fechaInicioVenta"],
                isset($parametrosFiltro["minTotal"]) ? $parametrosFiltro["minTotal"] : null,
                isset($parametrosFiltro["maxTotal"]) ? $parametrosFiltro["maxTotal"] : null,
                isset($parametrosFiltro["minTotalProducto"]) ? $parametrosFiltro["minTotalProducto"] : null,
                isset($parametrosFiltro["maxTotalProducto"]) ? $parametrosFiltro["maxTotalProducto"] : null
            );
            return $resultado;
        }
        else if(isset($parametrosFiltro["fechaFinVenta"])){
            $resultado = $managerFiltros->filtroFechaFinValorVentasCantidades(
                $parametrosFiltro["fechaFinVenta"],
                isset($parametrosFiltro["minTotal"]) ? $parametrosFiltro["minTotal"] : null,
                isset($parametrosFiltro["maxTotal"]) ? $parametrosFiltro["maxTotal"] : null,
                isset($parametrosFiltro["minTotalProducto"]) ? $parametrosFiltro["minTotalProducto"] : null,
                isset($parametrosFiltro["maxTotalProducto"]) ? $parametrosFiltro["maxTotalProducto"] : null
            );
            return $resultado;
        }

        return $managerFiltros->obtenerTodos();
        
    }

    public function destroy(){
        return "";
    }   
    public function store(){
        return "";
    }
    public function update(){
        return "";
    }

    public function obtenerProductosMasVendidosConIngresos(Request $request)
    {
        $parametros = request()->all();
        
        $managerFiltros = new FiltroProductosMasVendidos(5);
        
        try {

            if (isset($parametros['fechaInicio']) && isset($parametros['fechaFin'])){
                return $managerFiltros->filtrarPorFechaInicioYFechaFin($parametros['fechaInicio'], $parametros['fechaFin'], $parametros['tipoOrden'], $parametros['cantidadAMostrar']);
            }
            else if (isset($parametros['fechaInicio']))
            {
                return $managerFiltros->filtrarPorFechaInicio($parametros['fechaInicio'], $parametros['tipoOrden'], $parametros['cantidadAMostrar']);
            }
            else if (isset($parametros['fechaFin']))
            {
                return $managerFiltros->filtrarPorFechaFin($parametros['fechaFin'], $parametros['tipoOrden'], $parametros['cantidadAMostrar']);
            }
            else
            {
                return $managerFiltros->obtenerProductosPorOrden($parametros['tipoOrden'], $parametros['cantidadAMostrar']);
            }
            
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

    public function construirCondiciones(){

    }
}

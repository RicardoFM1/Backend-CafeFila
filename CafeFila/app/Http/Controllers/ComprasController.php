<?php

namespace App\Http\Controllers;

use App\Models\Compras;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ComprasController extends Controller
{
    public function listar(Request $request)
    {
        try {
            $query = Compras::with(['usuario', 'alteradoPor'])
                             ->select('compras.*'); 

            if ($request->filled('usuario_id')) {
                $query->where('usuario_id', $request->usuario_id);
            }

            if ($request->filled('item')) {
                $query->where('item', 'like', '%' . $request->item . '%');
            }

            if ($request->filled('tipo') && in_array($request->tipo, ['cafe', 'filtro'])) {
                $query->where('item', $request->tipo);
            }

            if ($request->filled('data_inicio') && $request->filled('data_fim')) {
                $query->whereBetween('data_compra', [
                    $request->data_inicio . ' 00:00:00',
                    $request->data_fim . ' 23:59:59'
                ]);
            }

            $compras = $query->orderBy('data_compra', 'desc')->get();

            $comprasAgrupadas = $compras->groupBy(function($item) {
                return $item->usuario_id . '-' . substr($item->data_compra, 0, 16); 
            });

            $resultadoFinal = [];
            foreach ($comprasAgrupadas as $grupo) {
                $totalCafe = $grupo->where('item', 'cafe')->sum('quantidade');
                $totalFiltro = $grupo->where('item', 'filtro')->sum('quantidade');

                $descricaoParts = [];
                if ($totalCafe > 0) $descricaoParts[] = "Café x$totalCafe";
                if ($totalFiltro > 0) $descricaoParts[] = "Filtro x$totalFiltro";
                
                $primeiroItem = $grupo->first();
                
                $resultadoFinal[] = [
                    'id' => $primeiroItem->id, 
                    'usuario_id' => $primeiroItem->usuario_id,
                    'usuario' => $primeiroItem->usuario,
                    'data' => $primeiroItem->data_compra,
                    'descricao' => implode(' | ', $descricaoParts),
                    'total' => $totalCafe + $totalFiltro,
                ];
            }

            return response()->json($resultadoFinal, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar as compras.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function comprar(Request $request)
    {
        try {
          
            $validacao = $request->validate([
                'usuario_id' => 'required|integer|exists:usuarios,id',
                'item'       => 'required|string|in:cafe,filtro',
                'quantidade' => 'required|integer|min:1'
            ]);

         
            $compra = Compras::create([
                'usuario_id'   => $validacao["usuario_id"],
                'data_compra'  => now('America/Sao_Paulo'),
                'item'         => $validacao["item"],
                'quantidade'   => $validacao["quantidade"],
            ]);

            return response()->json([
                "message" => "Compra efetuada com sucesso!",
                "compra"  => $compra
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                "message" => "Erro ao efetuar compra.",
                "error"   => $e->getMessage()
            ], 500);
        }
    }

    public function atualizar(Request $request, $id)
    {
        try {
            $compra = Compras::findOrFail($id);

            $usuarioLogado = JWTAuth::parseToken()->authenticate();

          
            if (!$usuarioLogado || !$usuarioLogado->admin) {
                return response()->json([
                    'message' => 'Acesso negado. Apenas administradores podem editar compras.'
                ], 403);
            }

          
            $compra->item = $request->item ?? $compra->item;
            $compra->quantidade = $request->quantidade ?? $compra->quantidade;

           
            $compra->ultima_alteracao_por = $usuarioLogado->id;
            $compra->ultima_alteracao_em = now('America/Sao_Paulo');

            $compra->save();

            return response()->json([
                'message' => 'Compra atualizada com sucesso.',
                'data'    => $compra
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar compra.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}

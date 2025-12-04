<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilaRequest;
use App\Models\Compras;
use App\Models\Fila;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class FilaController extends Controller
{
    public function listar()
    {
        try {
            $fila = Fila::with('usuario')
                ->orderBy('posicao', 'asc')
                ->get();

            return response()->json($fila, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar a fila.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function adicionarPedido(Request $request, $item_type)
    {
        DB::beginTransaction();
        try {
            $usuarioLogado = JWTAuth::parseToken()->authenticate();
            
            if (!in_array($item_type, ['cafe', 'filtro'])) {
                return response()->json([
                    'message' => 'Tipo de item inválido. Use "cafe" ou "filtro".'
                ], 400);
            }

            $fila = Fila::where('usuario_id', $usuarioLogado->id)->first();
            $message = '';
            $status = 200;

            if ($fila) {
                $fila->{$item_type} = $fila->{$item_type} + 1;
                $fila->save();
                $message = 'Item "' . $item_type . '" adicionado ao pedido com sucesso!';

            } else {
                $ultimaPosicao = Fila::max('posicao') ?? 0;
                $novaPosicao = $ultimaPosicao + 1;

                $fila = Fila::create([
                    'usuario_id' => $usuarioLogado->id,
                    'posicao' => $novaPosicao,
                    'cafe' => $item_type === 'cafe' ? 1 : 0,
                    'filtro' => $item_type === 'filtro' ? 1 : 0,
                ]);
                
                $message = 'Usuário adicionado à fila e item "' . $item_type . '" adicionado ao pedido com sucesso!';
                $status = 201;
            }
            
            DB::commit();

            return response()->json([
                'message' => $message,
                'dados' => $fila,
            ], $status);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao adicionar item ao pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function moverParaProximo(Request $request, $usuario_id)
    {
        DB::beginTransaction();
        try {
            $usuarioLogado = JWTAuth::parseToken()->authenticate();

            if (!$usuarioLogado || !$usuarioLogado->admin) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Acesso negado. Apenas administradores podem mover usuários na fila.'
                ], 403);
            }

            $filaMover = Fila::where('usuario_id', $usuario_id)->first();

            if (!$filaMover) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Usuário não encontrado na fila.'
                ], 404);
            }

            $posicaoAtual = $filaMover->posicao;
            
            if ($posicaoAtual <= 2) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Usuário já está na primeira ou segunda posição da fila. Nenhuma ação necessária.'
                ], 200);
            }
            
            Fila::whereBetween('posicao', [2, $posicaoAtual - 1])
                ->increment('posicao');
            
            $filaMover->posicao = 2;
            $filaMover->save();

            DB::commit();

            return response()->json([
                'message' => 'Usuário movido para a segunda posição da fila com sucesso!',
                'usuario_id' => $usuario_id,
                'nova_posicao' => 2
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao mover usuário para a segunda posição.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

public function concluirEVoltarParaFinal(Request $request, $usuario_id)
{
    DB::beginTransaction();

    try {

        $fila = Fila::where('usuario_id', $usuario_id)->first();

        if (!$fila) {
            DB::rollBack();
            return response()->json([
                'message' => 'Usuário não encontrado na fila.'
            ], 404);
        }

        // Verificar se é o primeiro da fila
        $primeiro = Fila::orderBy('posicao')->first();

        if ($fila->id !== $primeiro->id) {
            DB::rollBack();
            return response()->json([
                'message' => 'Apenas o primeiro da fila pode concluir a compra.'
            ], 403);
        }

        
        if ($fila->cafe > 0) {
            Compras::create([
                'usuario_id'   => $usuario_id,
                'item'         => 'cafe',
                'quantidade'   => $fila->cafe,
                'data_compra'  => now('America/Sao_Paulo')
            ]);
        }

        // Filtro
        if ($fila->filtro > 0) {
            Compras::create([
                'usuario_id'   => $usuario_id,
                'item'         => 'filtro',
                'quantidade'   => $fila->filtro,
                'data_compra'  => now('America/Sao_Paulo')
            ]);
        }

        //-------------------------------
        // PROCESSAR FILA
        //-------------------------------

        $posicaoRemovida = $fila->posicao;

        $fila->delete();

        // Reordenar posições
        Fila::where('posicao', '>', $posicaoRemovida)
            ->decrement('posicao');

        // Nova posição no final
        $novaPosicao = (Fila::max('posicao') ?? 0) + 1;

        // Criar nova entrada "zerada"
        $novo = Fila::create([
            'usuario_id' => $usuario_id,
            'posicao' => $novaPosicao,
            'cafe' => 0,
            'filtro' => 0,
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Compra concluída e usuário movido para o final da fila!',
            'nova_posicao' => $novaPosicao,
            'dados' => $novo
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Erro ao concluir compra.',
            'error' => $e->getMessage()
        ], 500);
    }
}


   public function atualizarQuantidade(Request $request)
{
    $usuario = JWTAuth::parseToken()->authenticate();

    $request->validate([
        'tipo' => 'required|in:cafe,filtro',
        'quantidade' => 'required|integer|min:0',
    ]);

    $tipo = $request->tipo;
    $novaQuantidade = $request->quantidade;

    
    $itemFila = Fila::firstOrNew(['usuario_id' => $usuario->id]);

    
    if (!$itemFila->exists) {
        $ultimaPosicao = Fila::max('posicao') ?? 0;
        $itemFila->posicao = $ultimaPosicao + 1;
        $itemFila->cafe = 0;
        $itemFila->filtro = 0;
    }


    if ($tipo === 'filtro' && $novaQuantidade > 0 && $itemFila->cafe <= 0) {
        return response()->json([
            'message' => 'Adicione Café antes de adicionar Filtro.',
        ], 400);
    }

   
    $itemFila->{$tipo} = $novaQuantidade;

 
    if (!$itemFila->exists) {
        $itemFila->created_at = now();
    }

    $itemFila->save();

    return response()->json([
        'message' => "Quantidade de {$tipo} atualizada para {$novaQuantidade} com sucesso!",
        'item' => $itemFila
    ], 200);
}


    public function sairDaFila($usuario_id)
    {
        DB::beginTransaction();

        try {
            
            $fila = Fila::where('usuario_id', $usuario_id)->first();

            if (!$fila) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Usuário não encontrado na fila.'
                ], 404);
            }

            $posicaoRemovida = $fila->posicao;
            $fila->delete();
            
            Fila::where('posicao', '>', $posicaoRemovida)
                ->decrement('posicao');

            DB::commit();

            return response()->json([
                'message' => 'Usuário removido da fila com sucesso!',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erro ao remover usuário da fila.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
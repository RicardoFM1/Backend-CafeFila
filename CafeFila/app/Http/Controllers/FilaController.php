<?php

namespace App\Http\Controllers;

use App\Models\Fila;
use Illuminate\Http\Request;

class FilaController extends Controller
{
    public function listar()
    {
        try {
            $fila = Fila::with('usuario')->get(); 

            return response()->json($fila, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar a fila.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   
    public function buscarPorPosicao(string $pos)
    {
        try {
            $fila = Fila::where('posicao', $pos)
                ->with('usuario')
                ->first();

            if (!$fila) {
                return response()->json([
                    'message' => 'Nenhum registro encontrado para a posição informada.'
                ], 404);
            }

            return response()->json($fila, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar a posição na fila.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function listar()
    {
        $consulta = Usuario::query();

        $usuarios = $consulta->get();

        return [$usuarios->toArray()];
    }
    public function buscarPorId(string $id)
    {
        $consulta = Usuario::query();

        $consulta->where("id", $id);
        $usuario = $consulta->get()->first();
        return [$usuario];
    }
    public function buscarPorEmail(Request $request)
    {
        $email = $request->query('email'); // pega ?email=...

        if (!$email) {
            return response()->json([
                'message' => 'Parâmetro "email" é obrigatório na query string.'
            ], 400);
        }

        $usuario = Usuario::where('email', $email)->first();

        if (!$usuario) {
            return response()->json([
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        return response()->json($usuario);
    }

    public function criar(Request $request)
    {
        $dados = $request->only(['email', 'senha', 'admin', 'status']);


        $existe = Usuario::where('email', $dados['email'])->exists();

        if ($existe) {
            return response()->json([
                "message" => "Email já cadastrado!"
            ], 400);
        }

        try {

            $usuario = new Usuario();
            $usuario->email = $dados["email"];
            $usuario->senha = $dados["senha"];
            $usuario->admin = $dados["admin"] ?? false;
            $usuario->status = $dados["status"] ?? "ativo";
            $usuario->save();

            return response()->json([
                "message" => "Usuário criado com sucesso!",
                "usuario" => $usuario
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                "message" => "Erro ao criar usuário.",
                "error" => $e->getMessage()
            ], 500);
        }
    }
    public function atualizar(string $id, Request $request)
{
    try {
       
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                "message" => "Usuário não encontrado!"
            ], 404);
        }

        $dados = $request->only(['email', 'senha', 'admin', 'status']);

        
        $emailExistente = Usuario::where('email', $dados['email'])
            ->where('id', '!=', $id) 
            ->exists();

        if ($emailExistente) {
            return response()->json([
                "message" => "Email já está sendo usado por outro usuário!"
            ], 400);
        }

     
        $usuario->email = $dados["email"];
        $usuario->senha = $dados["senha"];
        $usuario->admin = $dados["admin"] ?? $usuario->admin;
        $usuario->status = $dados["status"] ?? $usuario->status;
        $usuario->save();

        return response()->json([
            "message" => "Usuário atualizado com sucesso!",
            "usuario" => $usuario
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "message" => "Erro ao atualizar o usuário.",
            "error" => $e->getMessage()
        ], 500);
    }
}

}

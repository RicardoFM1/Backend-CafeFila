<?php

use App\Http\Controllers\UsuarioController;
use App\Models\Usuario;
use Illuminate\Support\Facades\Route;

Route::prefix('/usuarios')->group(function () {
    Route::get("", [UsuarioController::class, "listar"]);
    Route::get("/filtro", [UsuarioController::class, "buscarPorEmail"]);
    Route::get("/{id}", [UsuarioController::class, "buscarPorId"]);
    Route::post("", [UsuarioController::class, "criar"]);
    Route::patch("/{id}", [UsuarioController::class, "atualizar"]);
});
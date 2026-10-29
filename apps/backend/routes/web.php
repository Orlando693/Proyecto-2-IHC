<?php

use App\Http\Controllers\CuentaController;
use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

// apps/web esta un nivel arriba: paginas, css y js
function archivoWeb(string $nombre)
{
    $partes = str_replace('/', DIRECTORY_SEPARATOR, $nombre);
    $ruta = dirname(base_path()).DIRECTORY_SEPARATOR.'web'.DIRECTORY_SEPARATOR.$partes;
    abort_unless(is_file($ruta), 404);

    $tipos = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
    ];
    $ext = pathinfo($ruta, PATHINFO_EXTENSION);

    return response()->file($ruta, [
        'Content-Type' => $tipos[$ext] ?? 'text/plain; charset=utf-8',
    ]);
}

Route::get('/', fn () => archivoWeb('paginas/index.html'));
Route::get('/login', fn () => archivoWeb('paginas/login.html'));
Route::get('/register', fn () => archivoWeb('paginas/registro.html'));
Route::get('/recover', fn () => archivoWeb('paginas/recuperar.html'));
Route::get('/mis-planes', fn () => archivoWeb('paginas/planes.html'));
Route::get('/css/estilos.css', fn () => archivoWeb('css/estilos.css'));
Route::get('/js/comun.js', fn () => archivoWeb('js/comun.js'));

Route::post('/api/auth/register', [CuentaController::class, 'registrar']);
Route::post('/api/auth/login', [CuentaController::class, 'entrar']);
Route::post('/api/auth/logout', [CuentaController::class, 'salir']);
Route::get('/api/auth/me', [CuentaController::class, 'yo']);
Route::post('/api/auth/recover', [CuentaController::class, 'recuperar']);
Route::post('/api/auth/recover/confirm', [CuentaController::class, 'confirmar']);

Route::get('/api/planes', [PlanController::class, 'lista']);
Route::post('/api/planes', [PlanController::class, 'crear']);
Route::post('/api/planes/actualizar', [PlanController::class, 'actualizar']);
Route::post('/api/planes/confirmar', [PlanController::class, 'confirmar']);
Route::post('/api/planes/cancelar', [PlanController::class, 'cancelar']);
Route::post('/api/planes/participantes', [PlanController::class, 'agregarParticipante']);
Route::post('/api/planes/propuestas', [PlanController::class, 'crearPropuesta']);
Route::post('/api/planes/votos', [PlanController::class, 'votar']);
Route::get('/api/cambios', [PlanController::class, 'cambios']);

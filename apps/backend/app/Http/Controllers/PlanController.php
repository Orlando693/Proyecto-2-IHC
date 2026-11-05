<?php

namespace App\Http\Controllers;

use App\Models\Cambio;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\User;
use App\Models\Vote;
use App\ReglasPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlanController extends Controller
{
    public function lista()
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $planes = Plan::with([
            'participantes:id,name,email',
            'propuestas' => function ($query) use ($user) {
                $query->withCount('votes')
                    ->with(['user:id,name', 'votes' => fn ($votes) => $votes->where('user_id', $user->id)]);
            },
        ])->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('participantes', fn ($participantes) => $participantes->where('users.id', $user->id));
        })->orderBy('id')->get(['id', 'user_id', 'nombre', 'fecha_limite', 'estado', 'votacion_cerrada', 'propuesta_ganadora_id', 'motivo_decision']);

        return response()->json(['planes' => $planes->map(function ($plan) {
            return [
                'id' => $plan->id,
                'user_id' => $plan->user_id,
                'nombre' => $plan->nombre,
                'fecha_limite' => $plan->fecha_limite,
                'estado' => $plan->estado,
                'votacion_cerrada' => $plan->votacion_cerrada,
                'propuesta_ganadora_id' => $plan->propuesta_ganadora_id,
                'motivo_decision' => $plan->motivo_decision,
                'participantes' => $plan->participantes->map(fn ($participante) => [
                    'id' => $participante->id,
                    'nombre' => $participante->name,
                    'correo' => $participante->email,
                ]),
                'propuestas' => $plan->propuestas->map(fn ($propuesta) => [
                    'id' => $propuesta->id,
                    'tipo' => $propuesta->tipo,
                    'contenido' => $propuesta->contenido,
                    'autor' => $propuesta->user->name,
                    'votos' => $propuesta->votes_count,
                    'mi_voto' => $propuesta->votes->isNotEmpty(),
                ]),
            ];
        })]);
    }

    public function cambios()
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $cambios = Cambio::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->get(['id', 'plan_id', 'nombre', 'accion', 'resultado', 'created_at']);

        return response()->json(['cambios' => $cambios]);
    }

    public function crear(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $nombre = trim($request->input('nombre', ''));
        $fecha = trim($request->input('fecha', ''));
        if ($nombre === '' || $fecha === '') {
            return response()->json(['message' => 'Falta el nombre o la fecha.'], 400);
        }

        $plan = Plan::create([
            'user_id' => $user->id,
            'nombre' => $nombre,
            'fecha_limite' => $fecha,
            'estado' => 'borrador',
        ]);

        Cambio::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'nombre' => $plan->nombre,
            'accion' => 'creado',
            'resultado' => 'borrador',
        ]);

        return response()->json(['id' => $plan->id], 201);
    }

    public function actualizar(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = Plan::where('id', $request->input('id'))
            ->where('user_id', $user->id)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'No encontré ese plan. Recarga la página.'], 404);
        }
        if (! ReglasPlan::puedeEditar($plan->user_id, $plan->estado, $user->id)) {
            return response()->json(['message' => 'Ese plan ya no se puede editar. Deja de editar y mira la lista.'], 400);
        }

        $nombre = trim($request->input('nombre', ''));
        $fecha = trim($request->input('fecha', ''));
        if ($nombre === '' || $fecha === '') {
            return response()->json(['message' => 'Falta el nombre o la fecha.'], 400);
        }

        $plan->nombre = $nombre;
        $plan->fecha_limite = $fecha;
        $plan->save();

        Cambio::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'nombre' => $plan->nombre,
            'accion' => 'editado',
            'resultado' => $nombre.' · '.$fecha,
        ]);

        return response()->json(['ok' => true]);
    }

    public function confirmar(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = Plan::where('id', $request->input('id'))
            ->where('user_id', $user->id)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'No encontré ese plan. Recarga la página.'], 404);
        }
        if (! ReglasPlan::puedeConfirmar($plan->user_id, $plan->estado, $user->id)) {
            if ($plan->estado === 'cancelado') {
                return response()->json(['message' => 'Un plan cancelado no se puede confirmar. Recarga la página.'], 400);
            }

            return response()->json(['message' => 'Ese plan ya no se puede confirmar. Recarga la página.'], 400);
        }

        $plan->estado = 'confirmado';
        $plan->save();

        Cambio::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'nombre' => $plan->nombre,
            'accion' => 'confirmado',
            'resultado' => 'confirmado',
        ]);

        return response()->json(['ok' => true]);
    }

    public function cancelar(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = Plan::where('id', $request->input('id'))->first();

        if (! $plan) {
            return response()->json(['message' => 'No encontré ese plan. Recarga la página.'], 404);
        }

        if ((int) $plan->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Solo quien organiza el plan puede cancelarlo.'], 400);
        }

        if (! ReglasPlan::puedeCancelar($plan->user_id, $plan->estado, $user->id)) {
            if ($plan->estado === 'cancelado') {
                return response()->json(['message' => 'Ese plan ya está cancelado. Recarga la página para verlo.'], 400);
            }

            return response()->json(['message' => 'No puedes cancelar ese plan. Revisa el estado y vuelve a intentarlo.'], 400);
        }

        $plan->estado = 'cancelado';
        $plan->save();

        Cambio::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'nombre' => $plan->nombre,
            'accion' => 'cancelado',
            'resultado' => 'cancelado',
        ]);

        return response()->json(['ok' => true]);
    }

    public function agregarParticipante(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = Plan::where('id', $request->input('id'))->where('user_id', $user->id)->first();
        if (! $plan) {
            return response()->json(['message' => 'Solo quien organiza puede agregar participantes.'], 403);
        }

        $correo = strtolower(trim($request->input('correo', '')));
        $participante = User::where('email', $correo)->first();
        if (! $participante) {
            return response()->json(['message' => 'No existe un usuario con ese correo.'], 404);
        }
        if ((int) $participante->id === (int) $user->id) {
            return response()->json(['message' => 'El organizador ya puede ver este plan.'], 400);
        }
        if ($plan->participantes()->where('users.id', $participante->id)->exists()) {
            return response()->json(['message' => 'Ese usuario ya participa en el plan.'], 400);
        }

        $plan->participantes()->attach($participante->id);

        return response()->json(['ok' => true]);
    }

    public function crearPropuesta(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = $this->planComoParticipante($request->input('id'), $user->id);
        if (! $plan) {
            return response()->json(['message' => 'Solo quien organiza o participa puede proponer opciones.'], 403);
        }
        if ($plan->estado === 'cancelado' || $plan->votacion_cerrada) {
            return response()->json(['message' => 'No se pueden proponer opciones cuando la votación está cerrada.'], 400);
        }

        $tipo = $request->input('tipo');
        $contenido = trim($request->input('contenido', ''));
        if (! in_array($tipo, ['fecha', 'lugar'], true) || $contenido === '') {
            return response()->json(['message' => 'Escribe una fecha o un lugar para la propuesta.'], 400);
        }

        Proposal::create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'tipo' => $tipo,
            'contenido' => $contenido,
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function votar(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = $this->planComoParticipante($request->input('id'), $user->id);
        if (! $plan) {
            return response()->json(['message' => 'Solo quien organiza o participa puede votar.'], 403);
        }
        if ($plan->estado === 'cancelado') {
            return response()->json(['message' => 'No se puede votar en un plan cancelado.'], 400);
        }
        if ($plan->votacion_cerrada) {
            return response()->json(['message' => 'La votación está cerrada.'], 400);
        }

        $propuesta = Proposal::where('id', $request->input('propuesta_id'))
            ->where('plan_id', $plan->id)
            ->first();
        if (! $propuesta) {
            return response()->json(['message' => 'No encontré esa propuesta.'], 404);
        }

        Vote::updateOrCreate(
            ['plan_id' => $plan->id, 'proposal_id' => $propuesta->id, 'user_id' => $user->id],
            ['tipo' => $propuesta->tipo]
        );

        return response()->json(['ok' => true]);
    }

    public function quitarVoto(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = $this->planComoParticipante($request->input('id'), $user->id);
        if (! $plan) {
            return response()->json(['message' => 'Solo quien organiza o participa puede votar.'], 403);
        }
        if ($plan->estado === 'cancelado' || $plan->votacion_cerrada) {
            return response()->json(['message' => 'La votación no se puede modificar.'], 400);
        }

        Vote::where('plan_id', $plan->id)
            ->where('proposal_id', $request->input('propuesta_id'))
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function cerrarVotacion(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = Plan::where('id', $request->input('id'))->where('user_id', $user->id)->first();
        if (! $plan) {
            return response()->json(['message' => 'Solo quien organiza puede cerrar la votación.'], 403);
        }
        if ($plan->estado === 'cancelado' || $plan->votacion_cerrada) {
            return response()->json(['message' => 'La votación ya está cerrada.'], 400);
        }

        $plan->votacion_cerrada = true;
        $plan->save();

        return response()->json(['ok' => true]);
    }

    public function confirmarResultado(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'No has iniciado sesión'], 401);
        }

        $plan = Plan::where('id', $request->input('id'))->where('user_id', $user->id)->first();
        if (! $plan) {
            return response()->json(['message' => 'Solo quien organiza puede confirmar el resultado.'], 403);
        }
        if (! $plan->votacion_cerrada || $plan->estado === 'cancelado') {
            return response()->json(['message' => 'Primero debes cerrar una votación activa.'], 400);
        }
        if ($plan->propuesta_ganadora_id) {
            return response()->json(['message' => 'El resultado ya fue confirmado.'], 400);
        }

        $propuestas = Proposal::where('plan_id', $plan->id)->withCount('votes')->get();
        if ($propuestas->isEmpty()) {
            return response()->json(['message' => 'No hay opciones para confirmar.'], 400);
        }

        $maximo = $propuestas->max('votes_count');
        $empatadas = $propuestas->where('votes_count', $maximo);
        $ganadora = $empatadas->first();
        $motivo = trim($request->input('motivo', ''));

        if ($empatadas->count() > 1) {
            $ganadora = $empatadas->firstWhere('id', (int) $request->input('propuesta_id'));
            if (! $ganadora || $motivo === '') {
                return response()->json(['message' => 'Elige una opción empatada y escribe el motivo.'], 400);
            }
        }

        $plan->propuesta_ganadora_id = $ganadora->id;
        $plan->motivo_decision = $empatadas->count() > 1 ? $motivo : null;
        $plan->estado = 'confirmado';
        $plan->save();

        return response()->json(['ok' => true]);
    }

    private function planComoParticipante($planId, $userId): ?Plan
    {
        return Plan::where('id', $planId)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereHas('participantes', fn ($participantes) => $participantes->where('users.id', $userId));
            })
            ->first();
    }
}

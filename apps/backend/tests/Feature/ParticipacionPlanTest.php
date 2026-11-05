<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Proposal;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipacionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_participante_puede_votar_varias_propuestas_y_el_organizador_cierra_la_votacion(): void
    {
        $organizador = User::create([
            'name' => 'Organizador',
            'email' => 'organizador@planazo.com',
            'password' => 'Planazo123!',
        ]);
        $participante = User::create([
            'name' => 'Participante',
            'email' => 'participante@planazo.com',
            'password' => 'Planazo123!',
        ]);

        $this->actingAs($organizador)
            ->postJson('/api/planes', ['nombre' => 'Cena', 'fecha' => 'viernes'])
            ->assertCreated();
        $plan = Plan::firstOrFail();

        $this->postJson('/api/planes/participantes', [
            'id' => $plan->id,
            'correo' => $participante->email,
        ])->assertOk();
        $this->assertDatabaseHas('plan_participants', [
            'plan_id' => $plan->id,
            'user_id' => $participante->id,
        ]);

        $this->actingAs($participante)
            ->getJson('/api/planes')
            ->assertOk()
            ->assertJsonPath('planes.0.id', $plan->id);

        $this->postJson('/api/planes/propuestas', [
            'id' => $plan->id,
            'tipo' => 'fecha',
            'contenido' => 'Sábado',
        ])->assertCreated();
        $primera = Proposal::firstOrFail();
        $segunda = Proposal::create([
            'plan_id' => $plan->id,
            'user_id' => $participante->id,
            'tipo' => 'fecha',
            'contenido' => 'Domingo',
        ]);

        $this->postJson('/api/planes/votos', [
            'id' => $plan->id,
            'propuesta_id' => $primera->id,
        ])->assertOk();
        $this->postJson('/api/planes/votos', [
            'id' => $plan->id,
            'propuesta_id' => $segunda->id,
        ])->assertOk();

        $this->assertDatabaseCount('votes', 2);
        $this->assertDatabaseHas('votes', [
            'plan_id' => $plan->id,
            'proposal_id' => $primera->id,
            'user_id' => $participante->id,
            'tipo' => 'fecha',
        ]);
        $this->assertDatabaseHas('votes', [
            'plan_id' => $plan->id,
            'proposal_id' => $segunda->id,
            'user_id' => $participante->id,
            'tipo' => 'fecha',
        ]);

        $respuesta = $this->getJson('/api/planes')->assertOk();
        $propuestas = $respuesta->json('planes.0.propuestas');
        $this->assertSame(1, $propuestas[0]['votos']);
        $this->assertSame(1, $propuestas[1]['votos']);
        $this->assertTrue($propuestas[0]['mi_voto']);
        $this->assertTrue($propuestas[1]['mi_voto']);

        $this->postJson('/api/planes/votos/quitar', [
            'id' => $plan->id,
            'propuesta_id' => $primera->id,
        ])->assertOk();
        $this->assertDatabaseCount('votes', 1);

        $this->actingAs($organizador)
            ->postJson('/api/planes/cerrar-votacion', ['id' => $plan->id])
            ->assertOk();

        $this->actingAs($participante)
            ->postJson('/api/planes/votos', [
                'id' => $plan->id,
                'propuesta_id' => $primera->id,
            ])->assertStatus(400);

        $this->assertSame(1, Vote::count());
    }

    public function test_el_organizador_puede_agregar_y_votar_opciones(): void
    {
        $organizador = User::create([
            'name' => 'Organizador',
            'email' => 'organizador@planazo.com',
            'password' => 'Planazo123!',
        ]);
        $plan = Plan::create([
            'user_id' => $organizador->id,
            'nombre' => 'Salida',
            'fecha_limite' => 'viernes',
            'estado' => 'borrador',
        ]);

        $this->actingAs($organizador)
            ->postJson('/api/planes/propuestas', [
                'id' => $plan->id,
                'tipo' => 'fecha',
                'contenido' => 'Sábado',
            ])->assertCreated();
        $this->postJson('/api/planes/propuestas', [
            'id' => $plan->id,
            'tipo' => 'lugar',
            'contenido' => 'Parque',
        ])->assertCreated();

        $propuestas = Proposal::where('plan_id', $plan->id)->get();
        $this->assertCount(2, $propuestas);

        foreach ($propuestas as $propuesta) {
            $this->postJson('/api/planes/votos', [
                'id' => $plan->id,
                'propuesta_id' => $propuesta->id,
            ])->assertOk();
        }

        $respuesta = $this->getJson('/api/planes')->assertOk();
        $this->assertCount(2, $respuesta->json('planes.0.propuestas'));
        $this->assertSame(2, Vote::count());
    }

    public function test_el_organizador_confirma_la_opcion_con_mas_votos(): void
    {
        $organizador = User::create([
            'name' => 'Organizador',
            'email' => 'organizador@planazo.com',
            'password' => 'Planazo123!',
        ]);
        $participante = User::create([
            'name' => 'Participante',
            'email' => 'participante@planazo.com',
            'password' => 'Planazo123!',
        ]);
        $plan = Plan::create([
            'user_id' => $organizador->id,
            'nombre' => 'Cena',
            'fecha_limite' => 'viernes',
            'estado' => 'borrador',
        ]);
        $plan->participantes()->attach($participante->id);
        $sabado = Proposal::create(['plan_id' => $plan->id, 'user_id' => $organizador->id, 'tipo' => 'fecha', 'contenido' => 'Sábado']);
        $domingo = Proposal::create(['plan_id' => $plan->id, 'user_id' => $organizador->id, 'tipo' => 'fecha', 'contenido' => 'Domingo']);
        Vote::create(['plan_id' => $plan->id, 'proposal_id' => $sabado->id, 'user_id' => $organizador->id, 'tipo' => 'fecha']);
        Vote::create(['plan_id' => $plan->id, 'proposal_id' => $sabado->id, 'user_id' => $participante->id, 'tipo' => 'fecha']);
        Vote::create(['plan_id' => $plan->id, 'proposal_id' => $domingo->id, 'user_id' => $participante->id, 'tipo' => 'fecha']);

        $this->actingAs($organizador)
            ->postJson('/api/planes/cerrar-votacion', ['id' => $plan->id])
            ->assertOk();
        $this->postJson('/api/planes/confirmar-resultado', ['id' => $plan->id])
            ->assertOk();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'propuesta_ganadora_id' => $sabado->id,
            'estado' => 'confirmado',
            'votacion_cerrada' => true,
        ]);

        $this->actingAs($participante)
            ->getJson('/api/planes')
            ->assertOk()
            ->assertJsonPath('planes.0.propuesta_ganadora_id', $sabado->id);
        $this->postJson('/api/planes/votos', ['id' => $plan->id, 'propuesta_id' => $domingo->id])
            ->assertStatus(400);
        $this->postJson('/api/planes/propuestas', [
            'id' => $plan->id,
            'tipo' => 'fecha',
            'contenido' => 'Viernes',
        ])->assertStatus(400);
        $this->postJson('/api/planes/votos/quitar', ['id' => $plan->id, 'propuesta_id' => $domingo->id])
            ->assertStatus(400);
    }

    public function test_el_organizador_resuelve_un_empate_con_motivo(): void
    {
        $organizador = User::create([
            'name' => 'Organizador',
            'email' => 'organizador@planazo.com',
            'password' => 'Planazo123!',
        ]);
        $participante = User::create([
            'name' => 'Participante',
            'email' => 'participante@planazo.com',
            'password' => 'Planazo123!',
        ]);
        $plan = Plan::create([
            'user_id' => $organizador->id,
            'nombre' => 'Salida',
            'fecha_limite' => 'viernes',
            'estado' => 'borrador',
        ]);
        $plan->participantes()->attach($participante->id);
        $parque = Proposal::create(['plan_id' => $plan->id, 'user_id' => $organizador->id, 'tipo' => 'lugar', 'contenido' => 'Parque']);
        $cine = Proposal::create(['plan_id' => $plan->id, 'user_id' => $organizador->id, 'tipo' => 'lugar', 'contenido' => 'Cine']);
        Vote::create(['plan_id' => $plan->id, 'proposal_id' => $parque->id, 'user_id' => $organizador->id, 'tipo' => 'lugar']);
        Vote::create(['plan_id' => $plan->id, 'proposal_id' => $cine->id, 'user_id' => $organizador->id, 'tipo' => 'lugar']);

        $this->actingAs($participante)
            ->postJson('/api/planes/cerrar-votacion', ['id' => $plan->id])
            ->assertStatus(403);
        $this->postJson('/api/planes/confirmar-resultado', ['id' => $plan->id])
            ->assertStatus(403);

        $this->actingAs($organizador)
            ->postJson('/api/planes/cerrar-votacion', ['id' => $plan->id])
            ->assertOk();
        $this->postJson('/api/planes/confirmar-resultado', [
            'id' => $plan->id,
            'propuesta_id' => $parque->id,
        ])->assertStatus(400);
        $this->postJson('/api/planes/confirmar-resultado', [
            'id' => $plan->id,
            'propuesta_id' => $cine->id,
            'motivo' => 'Es más fácil llegar en transporte público.',
        ])->assertOk();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'propuesta_ganadora_id' => $cine->id,
            'motivo_decision' => 'Es más fácil llegar en transporte público.',
        ]);

        $this->actingAs($participante)
            ->getJson('/api/planes')
            ->assertOk()
            ->assertJsonPath('planes.0.propuesta_ganadora_id', $cine->id)
            ->assertJsonPath('planes.0.motivo_decision', 'Es más fácil llegar en transporte público.');
    }
}

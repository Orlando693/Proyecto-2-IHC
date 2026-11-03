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
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Throwable;

/**
 * Tests pour l'API de Battleship.
 *
 * @author Gabriel T. St-Hilaire
 */
class BattleshipTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /** @var User $user1 Usager principal. */
    private User $user1;

    /** @var User $user2 Usager pour tester la protection des données de l'usager $user1. */
    private User $user2;

    /**
     * On recréé nos usagers avant chaque test.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
    }

    /**
     * On supprime nos usagers (et les données en cascade) après chaque test.
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->user1->forceDelete();
        $this->user2->forceDelete();
        parent::tearDown();
    }

    /**
     * On teste notre séquence complète.
     *
     * @return void
     * @throws Throwable
     */
    public function test_partie(): void
    {
        // #### /battleship-ia/parties (POST) ####
        $response = $this->actingAs($this->user1)->postJson('/battleship-ia/parties', ['adversaire' => 'SuperAdversaire']);
        $response
            ->assertStatus(201)
            ->assertJsonPath('data.adversaire', 'SuperAdversaire');
        $this->validerJSONPartie($response);

        $idPartie = $response->decodeResponseJson()['data']['id'];

        // #### /battleship-ia/parties/[id]/missiles (POST) ####
        $response = $this->actingAs($this->user1)->postJson("/battleship-ia/parties/$idPartie/missiles");
        $response
            ->assertStatus(201)
            ->assertJson(fn(AssertableJson $json) => $json->whereType('data.resultat' , 'null'));
        $this->validerJSONMissile($response);

        $missile = $response->decodeResponseJson()['data']['coordonnee'];

        // #### /battleship-ia/parties/[id]/missiles/[coordonnées] (PUT) ####
        $response = $this->actingAs($this->user1)->putJson("/battleship-ia/parties/$idPartie/missiles/$missile", ['resultat' => 3]);
        $response
            ->assertStatus(200)
            ->assertJsonPath('data.resultat', fn ($resultat) => $resultat === 3);
        $this->validerJSONMissile($response);

        // ####  /battleship-ia/parties/[id] (DELETE) ####
        $response = $this->actingAs($this->user1)->deleteJson("/battleship-ia/parties/$idPartie");
        $response->assertStatus(200);
        $this->validerJSONPartie($response);
    }

    /**
     * On teste les erreurs d'authentifcation 401.
     *
     * @return void
     */
    public function test_erreur_401(): void
    {
        $message = 'Non authentifié.';
        $code = 401;

        $response = $this->postJson('/battleship-ia/parties', ['adversaire' => 'SuperAdversaire']);
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);

        $response = $this->postJson('/battleship-ia/parties/1/missiles');
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);

        $response = $this->putJson("/battleship-ia/parties/1/missiles/A-1", ['resultat' => 3]);
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);

        $response = $this->deleteJson("/battleship-ia/parties/1");
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);
    }

    /**
     * On teste les erreurs d'autorisation 403.
     *
     * @return void
     * @throws Throwable
     */
    public function test_erreur_403()
    {
        $message = 'Cette action n’est pas autorisée.';
        $code = 403;

        $response = $this->actingAs($this->user1)->postJson('/battleship-ia/parties', ['adversaire' => 'SuperAdversaire']);
        $idPartie = $response->decodeResponseJson()['data']['id'];

        $response = $this->actingAs($this->user1)->postJson("/battleship-ia/parties/$idPartie/missiles");
        $missile = $response->decodeResponseJson()['data']['coordonnee'];

        $response = $this->actingAs($this->user2)->postJson("/battleship-ia/parties/$idPartie/missiles");
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);

        $response = $this->actingAs($this->user2)->putJson("/battleship-ia/parties/$idPartie/missiles/$missile", ['resultat' => 3]);
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);

        $response = $this->actingAs($this->user2)->deleteJson("/battleship-ia/parties/$idPartie");
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);
    }

    /**
     * On teste les erreurs de ressources inexistantes 404.
     *
     * @return void
     * @throws Throwable
     */
    public function test_erreur_404()
    {
        $message = 'La ressource n’existe pas.';
        $code = 404;

        $response = $this->actingAs($this->user1)->postJson('/battleship-ia/parties/-1/missiles');
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);

        $response = $this->actingAs($this->user1)->putJson("/battleship-ia/parties/-1/missiles/A-1", ['resultat' => 3]);
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);

        $response = $this->actingAs($this->user1)->deleteJson("/battleship-ia/parties/-1");
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);

        $response = $this->actingAs($this->user1)->postJson('/battleship-ia/parties', ['adversaire' => 'SuperAdversaire']);
        $idPartie = $response->decodeResponseJson()['data']['id'];
        $response = $this->actingAs($this->user1)->putJson("/battleship-ia/parties/$idPartie/missiles/-1", ['resultat' => 3]);
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', $message);
    }

    /**
     * On teste les erreurs de données invalides 422.
     *
     * @return void
     * @throws Throwable
     */
    public function test_erreur_422()
    {
        $code = 422;

        $response = $this->actingAs($this->user1)->postJson('/battleship-ia/parties', ['adversaire' => '']);
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', 'Le champ adversaire est obligatoire.');

        $response = $this->actingAs($this->user1)->postJson('/battleship-ia/parties', ['adversaire' => 'SuperAdversaire']);
        $idPartie = $response->decodeResponseJson()['data']['id'];

        $response = $this->actingAs($this->user1)->postJson("/battleship-ia/parties/$idPartie/missiles");
        $missile = $response->decodeResponseJson()['data']['coordonnee'];

        $response = $this->actingAs($this->user1)->putJson("/battleship-ia/parties/$idPartie/missiles/$missile", ['resultat' => '']);
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', 'Le champ resultat est obligatoire.');

        $response = $this->actingAs($this->user1)->putJson("/battleship-ia/parties/$idPartie/missiles/$missile", ['resultat' => '-1']);
        $response
            ->assertStatus($code)
            ->assertJsonPath('message', 'Le champ resultat est invalide.');
    }

    /**
     * Validation d'une réponse JSON pour une ressource Partie.
     *
     * @param $response TestResponse réponse de la requête à valider.
     * @return void
     */
    private function validerJSONPartie(TestResponse $response): void
    {
        $response
            ->assertJson(fn(AssertableJson $json) =>
                $json->whereAllType([
                        'data.id' => 'integer',
                        'data.adversaire' => 'string',
                        'data.bateaux' => 'array',
                        'data.bateaux.porte-avions' => 'array',
                        'data.bateaux.cuirasse' => 'array',
                        'data.bateaux.destroyer' => 'array',
                        'data.bateaux.sous-marin' => 'array',
                        'data.bateaux.patrouilleur' => 'array',
                        'data.created_at' => 'string',
                    ])->missing('message')
                )
            ->assertJsonPath('data.bateaux.porte-avions', fn ($coordonnees) => count($coordonnees) == 5)
            ->assertJsonPath('data.bateaux.cuirasse', fn ($coordonnees) => count($coordonnees) == 4)
            ->assertJsonPath('data.bateaux.destroyer', fn ($coordonnees) => count($coordonnees) == 3)
            ->assertJsonPath('data.bateaux.sous-marin', fn ($coordonnees) => count($coordonnees) == 3)
            ->assertJsonPath('data.bateaux.patrouilleur', fn ($coordonnees) => count($coordonnees) == 2)
            ->assertJsonPath('data.bateaux.porte-avions.0', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.porte-avions.1', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.porte-avions.2', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.porte-avions.3', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.porte-avions.4', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.cuirasse.0', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.cuirasse.1', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.cuirasse.2', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.cuirasse.3', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.destroyer.0', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.destroyer.1', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.destroyer.2', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.sous-marin.0', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.sous-marin.1', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.sous-marin.2', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.patrouilleur.0', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee))
            ->assertJsonPath('data.bateaux.patrouilleur.1', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee));
    }

    /**
     * Validation d'une réponse JSON pour une ressource Missile.
     *
     * @param $response TestResponse réponse de la requête à valider.
     * @return void
     */
    private function validerJSONMissile(TestResponse $response)
    {
        $response
            ->assertJson(fn(AssertableJson $json) =>
                $json->whereAllType([
                        'data.coordonnee' => 'string',
                        'data.resultat' => 'integer|null',
                        'data.created_at' => 'string',
                    ])->missing('message')
                )
            ->assertJsonPath('data.coordonnee', fn ($coordonnee) => $this->validerFormatCoordonnee($coordonnee));
    }

    /**
     * Valide le format d'une coordonnée Battleship. Ex: A-1.
     *
     * @param $coordonnee string La coordonnée à valider.
     * @return bool Si la coordonnée est valide.
     */
    private function validerFormatCoordonnee(string $coordonnee): bool
    {
        return preg_match('/^[A-J]-([1-9]|10)$/', $coordonnee) === 1;
    }
}

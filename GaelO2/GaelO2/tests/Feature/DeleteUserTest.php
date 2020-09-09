<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;

class DeleteUser extends TestCase
{

    use DatabaseMigrations {
        runDatabaseMigrations as baseRunDatabaseMigrations;
    }

    public function runDatabaseMigrations()
    {
        $this->baseRunDatabaseMigrations();
        $this->artisan('db:seed');

    }

    protected function setUp() : void{
        parent::setUp();
        Artisan::call('passport:install');
        Passport::actingAs(
            User::where('id',1)->first()
        );
    }

    public function testDeleteUser() {

        //Fill user table
        factory(User::class, 5)->create();
        //Remove number 3
        $response = $this->json('DELETE', '/api/users/3');
        //Test delete non-existing user should be refused
        $this->json('DELETE', '/api/users/-1') -> assertStatus(500);
        //Check that the user 3 has been remove
        $queryUser = User::where('id', 3)->first();
        $this->assertEmpty($queryUser);
    }
}
<?php

namespace Tests\Feature;

use App\Documentation;
use App\GaelO\Constants\Constants;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\User;
use App\Study;
use Tests\AuthorizationTools;

class DocumentationTest extends TestCase
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
        $this->study = factory(Study::class, 1)->create(['name'=> 'test', 'patient_code_prefix' => 1234])->first();

        $this->validPayload = [
            'name'=>'documentationTest',
            'version'=>'1.1.0',
            'investigator'=>true,
            'monitor'=>true,
            'controller'=>false,
            'reviewer'=>false
        ];

        Artisan::call('passport:install');
        Passport::actingAs(
            User::where('id',1)->first()
        );
    }

    public function testForbiddenWhenNotSupervisor(){
        $response = $this->post('api/studies/'.$this->study->name.'/documentations', $this->validPayload);
        $response->assertStatus(403);
    }

    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testCreateDocumentation()
    {
        AuthorizationTools::addRoleToUser(1, Constants::ROLE_SUPERVISOR, $this->study->name);
        $response = $this->post('api/studies/'.$this->study->name.'/documentations', $this->validPayload);
        $response->assertStatus(201);
        $response->assertJsonStructure(['id']);
    }

    public function testUploadDocumentation(){
        AuthorizationTools::addRoleToUser(1, Constants::ROLE_SUPERVISOR, $this->study->name);
        $documentation = factory(Documentation::class, 1)->create(['study_name'=>$this->study->name])->first();
        $response = $this->post('api/documentations/'.$documentation['id'].'/file', ["binaryData"=>base64_encode ("testFileContent"  ) ], ['CONTENT_TYPE'=>'application/pdf']);
        $response->assertStatus(201);

    }

    public function testUploadDocumentationShouldFailBecauseWrongMime(){
        AuthorizationTools::addRoleToUser(1, Constants::ROLE_SUPERVISOR, $this->study->name);
        $documentation = factory(Documentation::class, 1)->create(['study_name'=>$this->study->name])->first();
        $response = $this->post('api/documentations/'.$documentation['id'].'/file', ["binaryData"=>base64_encode ("testFileContent"  ) ]);
        $response->assertStatus(400);

    }

    public function testUploadDocumentationShouldFailBecauseNotBase64Encoded(){
        AuthorizationTools::addRoleToUser(1, Constants::ROLE_SUPERVISOR, $this->study->name);
        $documentation = factory(Documentation::class, 1)->create(['study_name'=>$this->study->name])->first();
        $response = $this->post('api/documentations/'.$documentation['id'].'/file', ["binaryData"=>"testFileContent"]);
        $response->assertStatus(400);
    }

    public function testDeleteDocumenationShouldFailBecauseNotSupervisor(){
        $documentation = factory(Documentation::class, 1)->create(['study_name'=>$this->study->name])->first();
        $response = $this->delete('api/documentations/'.$documentation['id']);
        $response->assertStatus(403);

    }

    public function testDeleteDocumenation(){
        AuthorizationTools::addRoleToUser(1, Constants::ROLE_SUPERVISOR, $this->study->name);
        $documentation = factory(Documentation::class, 1)->create(['study_name'=>$this->study->name])->first();
        $response = $this->delete('api/documentations/'.$documentation['id']);
        $response->assertStatus(200);

    }
}

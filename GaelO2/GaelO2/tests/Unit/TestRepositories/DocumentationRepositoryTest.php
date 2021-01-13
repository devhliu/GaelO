<?php

namespace Tests\Unit\TestRepositories;

use App\GaelO\Constants\Constants;
use App\GaelO\Repositories\DocumentationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

use App\Models\Documentation;
use App\Models\Study;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DocumentationRepositoryTest extends TestCase
{
    private DocumentationRepository $documentationRepository;

    use DatabaseMigrations {
        runDatabaseMigrations as baseRunDatabaseMigrations;
    }

    use RefreshDatabase;

    public function runDatabaseMigrations()
    {
        $this->baseRunDatabaseMigrations();
        $this->artisan('db:seed');
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->documentationRepository = new DocumentationRepository(new Documentation());
        $this->study = Study::factory()->create();
        $this->study2 = Study::factory()->create();
    }

    public function testCreateDocumentation(){

        $documenationEntity = $this->documentationRepository->createDocumentation('documentation', '2020-01-01', $this->study->name , '1.0', true,
                true, true, true);

        $documentation = Documentation::find($documenationEntity['id'])->toArray();

        $this->assertEquals($documenationEntity['name'], $documentation['name']);
        $this->assertEquals($documenationEntity['version'], $documentation['version']);
    }

    public function testGetDocumentation(){
        $createdDocumentation = Documentation::factory()->studyName($this->study->name)->create();
        $documentation = $this->documentationRepository->find($createdDocumentation->id);

        $this->assertEquals($createdDocumentation->name, $documentation['name']);
        $this->assertEquals($createdDocumentation->version, $documentation['version']);
    }

    public function testDeleteDocumentation(){
        $createdDocumentation = Documentation::factory()->studyName($this->study->name)->create();
        $this->documentationRepository->delete($createdDocumentation['id']);
        $this->expectException(ModelNotFoundException::class);
        Documentation::findOrFail($createdDocumentation['id']);

    }

    public function testDocumentationOfStudy(){

        Documentation::factory()->studyName($this->study->name)->count(5)->create();
        Documentation::factory()->studyName($this->study2->name)->count(10)->create();

        $documenationStudy1 = $this->documentationRepository->getDocumentationsOfStudy($this->study->name);
        $this->assertEquals(5, sizeof($documenationStudy1));
        $documenationStudy1 = $this->documentationRepository->getDocumentationsOfStudy($this->study2->name);
        $this->assertEquals(10, sizeof($documenationStudy1));
    }

    public function testDocumentationOfStudyWithRole(){

        Documentation::factory()->studyName($this->study->name)->investigator()->count(5)->create();
        Documentation::factory()->studyName($this->study->name)->reviewer()->count(15)->create();
        Documentation::factory()->studyName($this->study2->name)->investigator()->count(10)->create();

        $documenationStudy1Investigator = $this->documentationRepository->getDocumentationOfStudyWithRole($this->study->name, Constants::ROLE_INVESTIGATOR);
        $this->assertEquals(5, sizeof($documenationStudy1Investigator));
        $documenationStudy1Supervisor = $this->documentationRepository->getDocumentationOfStudyWithRole($this->study->name, Constants::ROLE_REVIEWER);
        $this->assertEquals(15, sizeof($documenationStudy1Supervisor));
        $documenationStudy2Investigator = $this->documentationRepository->getDocumentationOfStudyWithRole($this->study2->name, Constants::ROLE_INVESTIGATOR);
        $this->assertEquals(10, sizeof($documenationStudy2Investigator));
    }

    //TODO : Update Documentation


}
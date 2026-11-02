<?php

namespace Tests\Unit;

use App\Models\ServicePortal\EmeetingRequestDetail;
use App\Models\ServicePortal\CcIted02RequestDetail;
use App\Models\ServicePortal\CcIted04RequestDetail;
use App\Models\ServicePortal\MoodleCourseRequestDetail;
use App\Models\ServicePortal\ServiceRequest;
use App\Models\ServicePortal\WebsiteRequestDetail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServicePortalModelsTest extends TestCase
{
    #[DataProvider('modelTables')]
    public function test_models_use_the_course133_connection_and_service_portal_tables(string $modelClass, string $table): void
    {
        $model = new $modelClass;

        $this->assertSame('course133', $model->getConnectionName());
        $this->assertSame($table, $model->getTable());
    }

    public function test_service_request_exposes_form_specific_details_and_audit_relations(): void
    {
        $request = new ServiceRequest;

        $this->assertInstanceOf(HasOne::class, $request->ccIted01Detail());
        $this->assertInstanceOf(HasOne::class, $request->ccIted02Detail());
        $this->assertInstanceOf(HasOne::class, $request->ccIted04Detail());
        $this->assertInstanceOf(HasOne::class, $request->ccIted05Detail());
        $this->assertInstanceOf(HasOne::class, $request->ccIted06Detail());
        $this->assertInstanceOf(HasMany::class, $request->deanReviews());
        $this->assertInstanceOf(HasMany::class, $request->officerReviews());
        $this->assertInstanceOf(HasMany::class, $request->histories());
        $this->assertInstanceOf(HasMany::class, $request->notifications());
    }

    /** @return array<string, array{class-string, string}> */
    public static function modelTables(): array
    {
        return [
            'service request' => [ServiceRequest::class, 'service_portal.service_request'],
            'CC-ITED-01 detail' => [WebsiteRequestDetail::class, 'service_portal.cc_ited_01_request_detail'],
            'CC-ITED-02 detail' => [CcIted02RequestDetail::class, 'service_portal.cc_ited_02_request_detail'],
            'CC-ITED-04 detail' => [CcIted04RequestDetail::class, 'service_portal.cc_ited_04_request_detail'],
            'CC-ITED-05 detail' => [EmeetingRequestDetail::class, 'service_portal.cc_ited_05_request_detail'],
            'CC-ITED-06 detail' => [MoodleCourseRequestDetail::class, 'service_portal.cc_ited_06_request_detail'],
        ];
    }
}

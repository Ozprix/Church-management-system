<?php

declare(strict_types=1);

namespace Tests\Feature\CustomFields;

use App\Models\Member;
use App\Models\MemberCustomField;
use App\Models\MemberCustomValue;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberCustomFieldUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uploads_file_and_returns_metadata(): void
    {
        Storage::fake('custom_fields');

        $tenant = Tenant::factory()->create();
        $field = MemberCustomField::factory()
            ->file([
                'allowed_extensions' => ['pdf'],
                'allowed_mimetypes' => ['application/pdf'],
                'max_size' => 2048,
            ])
            ->create([
                'tenant_id' => $tenant->id,
            ]);

        $this->actingAsTenantAdmin($tenant);

        $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->post('/api/v1/member-custom-fields/' . $field->id . '/uploads', [
                'file' => $file,
            ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['data' => ['file' => ['disk', 'path', 'name', 'mime', 'size']]]);

        $metadata = $response->json('data.file');
        Storage::disk('custom_fields')->assertExists($metadata['path']);
    }

    public function test_member_custom_value_persists_file_metadata(): void
    {
        Storage::fake('custom_fields');

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $field = MemberCustomField::factory()->file()->create([
            'tenant_id' => $tenant->id,
        ]);
        $metadata = [
            'disk' => 'custom_fields',
            'path' => 'custom-fields/' . $tenant->id . '/' . $field->id . '/placeholder.pdf',
            'name' => 'placeholder.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ];

        MemberCustomValue::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'field_id' => $field->id,
            'value_json' => $metadata,
            'value_file_disk' => $metadata['disk'],
            'value_file_path' => $metadata['path'],
            'value_file_name' => $metadata['name'],
            'value_file_mime' => $metadata['mime'],
            'value_file_size' => $metadata['size'],
        ]);

        $this->assertCount(1, $member->customValues);
        $value = $member->customValues()->where('field_id', $field->id)->first();
        $this->assertNotNull($value);
        $this->assertEquals($metadata['disk'], $value->value_file_disk);
        $this->assertEquals($metadata['path'], $value->value_file_path);
    }
}

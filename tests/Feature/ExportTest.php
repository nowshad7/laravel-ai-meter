<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\TestCase;

class ExportTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function seedCalls(): void
    {
        AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => 1.25,
            'prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150,
            'scope_type' => 'user', 'scope_id' => '1', 'status' => 'success',
            'input' => 'hello', 'output' => 'world', 'properties' => ['k' => 'v'],
            'created_at' => Carbon::parse('2024-06-15 09:00:00'),
        ]);
        AiMeterCall::create([
            'provider' => 'anthropic', 'model' => 'claude-3-5-haiku', 'cost_usd' => 0.1,
            'status' => 'error', 'created_at' => Carbon::parse('2024-06-10 09:00:00'),
        ]);
    }

    public function test_guests_cannot_export()
    {
        $this->get(route('ai-meter.calls.export'))->assertRedirect('/login');
    }

    public function test_csv_export_has_a_header_and_rows_and_a_download_name()
    {
        $this->seedCalls();

        $response = $this->actingAs($this->makeUser())
            ->get(route('ai-meter.calls.export'))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertStringContainsString('id,created_at,source,provider,model', $lines[0]);
        $this->assertCount(3, $lines); // header + 2 rows
        $this->assertStringContainsString('gpt-4o', $csv);
        $this->assertStringContainsString('claude-3-5-haiku', $csv);
    }

    public function test_csv_export_respects_filters()
    {
        $this->seedCalls();

        $csv = $this->actingAs($this->makeUser())
            ->get(route('ai-meter.calls.export', ['provider' => 'openai']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('gpt-4o', $csv);
        $this->assertStringNotContainsString('claude-3-5-haiku', $csv);
    }

    public function test_csv_omits_io_columns_by_default_and_includes_them_with_io()
    {
        $this->seedCalls();
        $user = $this->makeUser();

        $without = $this->actingAs($user)->get(route('ai-meter.calls.export'))->streamedContent();
        $this->assertStringNotContainsString('hello', $without);
        $this->assertStringNotContainsString(',input,output,properties', $without);

        $with = $this->actingAs($user)->get(route('ai-meter.calls.export', ['io' => 1]))->streamedContent();
        $this->assertStringContainsString('input,output,properties', $with);
        $this->assertStringContainsString('hello', $with);
        $this->assertStringContainsString('world', $with);
    }

    public function test_json_export_is_a_valid_array()
    {
        $this->seedCalls();

        $response = $this->actingAs($this->makeUser())
            ->get(route('ai-meter.calls.export', ['format' => 'json']))
            ->assertOk();

        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));

        $data = json_decode($response->streamedContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertSame('claude-3-5-haiku', $data[0]['model']); // ordered by id desc
        $this->assertSame('gpt-4o', $data[1]['model']);
        $this->assertArrayNotHasKey('input', $data[0]);
    }

    public function test_json_export_with_io_keeps_properties_as_an_object()
    {
        $this->seedCalls();

        $data = json_decode(
            $this->actingAs($this->makeUser())
                ->get(route('ai-meter.calls.export', ['format' => 'json', 'io' => 1]))
                ->streamedContent(),
            true,
        );

        $this->assertSame(['k' => 'v'], $data[1]['properties']); // gpt-4o row (id desc puts it second)
        $this->assertSame('hello', $data[1]['input']);
    }

    public function test_command_writes_csv_to_stdout()
    {
        $this->seedCalls();

        $this->artisan('ai-meter:export')
            ->expectsOutputToContain('gpt-4o')
            ->assertSuccessful();
    }

    public function test_command_writes_json_to_a_file_with_filters()
    {
        $this->seedCalls();
        $path = storage_path('app/ai-meter/export-test.json');
        @unlink($path);

        $this->artisan('ai-meter:export', ['--format' => 'json', '--output' => $path, '--provider' => 'openai'])
            ->expectsOutputToContain('Exported 1 call(s)')
            ->assertSuccessful();

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertCount(1, $data);
        $this->assertSame('gpt-4o', $data[0]['model']);

        @unlink($path);
    }

    public function test_command_filters_by_date_window()
    {
        $this->seedCalls();
        $path = storage_path('app/ai-meter/export-dates.csv');
        @unlink($path);

        $this->artisan('ai-meter:export', ['--output' => $path, '--since' => '2024-06-12', '--until' => '2024-06-20'])
            ->assertSuccessful();

        $csv = (string) file_get_contents($path);
        $this->assertStringContainsString('gpt-4o', $csv);        // 06-15, in window
        $this->assertStringNotContainsString('claude-3-5-haiku', $csv); // 06-10, out
        @unlink($path);
    }

    public function test_command_with_io_includes_prompt_and_response()
    {
        $this->seedCalls();
        $path = storage_path('app/ai-meter/export-io.csv');
        @unlink($path);

        $this->artisan('ai-meter:export', ['--output' => $path, '--with-io' => true])
            ->assertSuccessful();

        $this->assertStringContainsString('hello', (string) file_get_contents($path));
        @unlink($path);
    }
}

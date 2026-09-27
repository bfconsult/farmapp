<?php

use App\Models\Expense;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

function tinyPng(): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function tinyPdf(): string
{
    $pdf = new \FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica');
    $pdf->Cell(40, 10, 'Test invoice');

    return $pdf->Output('S');
}

function setUpExportScenario(): array
{
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fencing', 'property_id' => $property->id, 'user_id' => $user->id]);

    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'started_at' => '2026-06-15 22:00:00',
        'ended_at' => '2026-06-16 00:00:00',
        'status' => WorkSession::FINALISED,
    ]);

    return [$user, $property, $job];
}

test('a reimbursable expense in range appears in the Excel export with the right total', function () {
    [$user, $property, $job] = setUpExportScenario();

    Expense::create([
        'farm_job_id' => $job->id, 'created_by' => $user->id, 'reimburse_to_user_id' => $user->id, 'name' => 'Fuel',
        'description' => 'Diesel for the mower', 'date' => '2026-06-10', 'amount' => 45.50,
        'reimburse' => true, 'status' => Expense::COMPLETE,
    ]);
    // Excluded: not reimbursable.
    Expense::create([
        'farm_job_id' => $job->id, 'created_by' => $user->id, 'reimburse_to_user_id' => $user->id, 'name' => 'Parts',
        'date' => '2026-06-11', 'amount' => 10, 'reimburse' => false, 'status' => Expense::COMPLETE,
    ]);
    // Excluded: reimbursable to someone else - even though created_by is
    // this exporting user (an admin entering it on someone else's behalf),
    // reimburse_to_user_id is what actually decides whose export it's in.
    $otherUser = User::factory()->create();
    Expense::create([
        'farm_job_id' => $job->id, 'created_by' => $user->id, 'reimburse_to_user_id' => $otherUser->id, 'name' => 'Other worker\'s fuel',
        'date' => '2026-06-11', 'amount' => 20, 'reimburse' => true, 'status' => Expense::COMPLETE,
    ]);
    // Excluded: outside the date range.
    Expense::create([
        'farm_job_id' => $job->id, 'created_by' => $user->id, 'reimburse_to_user_id' => $user->id, 'name' => 'Late fuel',
        'date' => '2026-07-05', 'amount' => 30, 'reimburse' => true, 'status' => Expense::COMPLETE,
    ]);
    // Excluded: needs_review, no amount yet.
    Expense::create([
        'farm_job_id' => $job->id, 'created_by' => $user->id, 'reimburse_to_user_id' => $user->id, 'name' => 'Pending review',
        'date' => '2026-06-12', 'amount' => null, 'reimburse' => true, 'status' => Expense::NEEDS_REVIEW,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', [
            'format' => 'excel',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertOk();

    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->getContent());
    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    // Row 1: header, 2: 15/06 session row, 3: Total, 4: blank, 5: "Reimbursable Expenses",
    // 6: column headers, 7: the Fuel expense, 8: Total.
    expect($sheet->getCell('A5')->getValue())->toBe('Reimbursable Expenses');
    expect($sheet->getCell('A6')->getValue())->toBe('Date');
    expect($sheet->getCell('A7')->getValue())->toBe('10/06/2026');
    expect($sheet->getCell('B7')->getValue())->toBe('Fencing');
    expect($sheet->getCell('C7')->getValue())->toBe('Diesel for the mower');
    expect((float) $sheet->getCell('D7')->getValue())->toBe(45.5);
    expect($sheet->getCell('C8')->getValue())->toBe('Total');
    expect((float) $sheet->getCell('D8')->getValue())->toBe(45.5);
});

test('no reimbursable expenses means no extra section in the Excel export', function () {
    [$user, $property, $job] = setUpExportScenario();

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', [
            'format' => 'excel',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertOk();

    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->getContent());
    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    expect($sheet->getCell('A4')->getValue())->toBeNull();
});

test('the PDF export gains a page per invoice attachment - one for an image, real pages for a PDF', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);

    [$user, $property, $job] = setUpExportScenario();

    Storage::disk('public')->put('invoices/receipt.png', tinyPng());
    Storage::disk('public')->put('invoices/supplier-invoice.pdf', tinyPdf());

    Expense::create([
        'farm_job_id' => $job->id, 'created_by' => $user->id, 'reimburse_to_user_id' => $user->id, 'name' => 'Fuel',
        'date' => '2026-06-10', 'amount' => 45.50, 'reimburse' => true, 'status' => Expense::COMPLETE,
        'invoice_file' => 'invoices/receipt.png',
    ]);
    Expense::create([
        'farm_job_id' => $job->id, 'created_by' => $user->id, 'reimburse_to_user_id' => $user->id, 'name' => 'Parts',
        'date' => '2026-06-11', 'amount' => 12, 'reimburse' => true, 'status' => Expense::COMPLETE,
        'invoice_file' => 'invoices/supplier-invoice.pdf',
    ]);

    $baseline = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', [
            'format' => 'pdf', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31',
        ]));
    $baseline->assertOk();
    $basePages = (new Fpdi())->setSourceFile(StreamReader::createByString($baseline->getContent()));

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', [
            'format' => 'pdf', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ]));
    $response->assertOk();
    $response->assertHeader('X-Vapor-Base64-Encode', 'True');

    $pagesWithInvoices = (new Fpdi())->setSourceFile(StreamReader::createByString($response->getContent()));

    // +1 page for the image invoice, +1 for the (single-page) real PDF invoice.
    expect($pagesWithInvoices)->toBe($basePages + 2);
});

test('a missing invoice file is skipped rather than failing the export', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);

    [$user, $property, $job] = setUpExportScenario();

    Expense::create([
        'farm_job_id' => $job->id, 'created_by' => $user->id, 'reimburse_to_user_id' => $user->id, 'name' => 'Fuel',
        'date' => '2026-06-10', 'amount' => 45.50, 'reimburse' => true, 'status' => Expense::COMPLETE,
        'invoice_file' => 'invoices/does-not-exist.png',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', [
            'format' => 'pdf', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ]));

    $response->assertOk();
});

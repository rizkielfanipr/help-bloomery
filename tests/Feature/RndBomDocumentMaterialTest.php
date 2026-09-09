<?php

use App\Models\RndBomDocumentMaterial;
use App\Models\RndProject;
use App\Models\User;

it('deletes document-only materials with their BOM', function () {
    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Document Material Project',
        'start_date' => today(),
        'end_date' => today()->addMonth(),
        'created_by' => $user->id,
    ]);
    $bom = $project->boms()->create([
        'esb_bom_id' => 999,
        'bom_code' => 'BOM-DOC',
        'bom_name' => 'Document BOM',
        'created_by' => $user->id,
    ]);
    $material = RndBomDocumentMaterial::factory()->create([
        'rnd_project_bom_id' => $bom->id,
        'name' => 'Air',
    ]);

    $bom->delete();

    expect($material->fresh())->toBeNull();
});

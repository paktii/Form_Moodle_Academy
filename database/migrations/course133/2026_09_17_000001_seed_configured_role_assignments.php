<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        // Role assignments must be created explicitly for real Server 199 personnel.
    }

    public function down(): void
    {
    }
};

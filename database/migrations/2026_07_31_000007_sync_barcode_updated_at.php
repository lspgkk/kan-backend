<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sync all barcodes.updated_at to match created_at.
     */
    public function up(): void
    {
        $updated = DB::statement("
            UPDATE barcodes
            SET updated_at = created_at
            WHERE updated_at != created_at
               OR (created_at IS NOT NULL AND updated_at IS NULL)
        ");
        echo "  Barcodes updated_at synced to created_at.\n";
    }

    public function down(): void
    {
        // No-op
    }
};

<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "Verifying timezone columns in tables:\n";

// Check projects table
if (Schema::hasColumn('projects', 'timezone')) {
    echo "✅ projects table has timezone column\n";
} else {
    echo "❌ projects table does not have timezone column\n";
}

// Check clients table
if (Schema::hasColumn('clients', 'timezone')) {
    echo "✅ clients table has timezone column\n";
} else {
    echo "❌ clients table does not have timezone column\n";
}

// Check users table
if (Schema::hasColumn('users', 'timezone')) {
    echo "✅ users table has timezone column\n";
} else {
    echo "❌ users table does not have timezone column\n";
}

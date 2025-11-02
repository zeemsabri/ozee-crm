<?php

// Path to the ProjectController file
$filePath = __DIR__.'/app/Http/Controllers/Api/ProjectController.php';

// Read the file content
$content = file_get_contents($filePath);

// Replace all instances of withPivot('role') with withPivot('role_id')
$updatedContent = str_replace("withPivot('role')", "withPivot('role_id')", $content);

// Replace all instances of 'role' => in pivot relationships with 'role_id' =>
$updatedContent = str_replace("'role' => \$user['role']", "'role_id' => \$user['role_id']", $updatedContent);
$updatedContent = str_replace("'role' => \$client['role']", "'role_id' => \$client['role_id']", $updatedContent);

// Write the updated content back to the file
file_put_contents($filePath, $updatedContent);

echo "Updated all instances of role references to role_id in ProjectController.php\n";

<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::first();
$user->password = bcrypt('admin123');
$user->save();

echo "Password updated successfully for: " . $user->email . PHP_EOL;
echo "New password: admin123" . PHP_EOL;

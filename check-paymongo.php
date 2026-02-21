<?php
echo "<!DOCTYPE html>
<html>
<head>
    <title>PayMongo Diagnostic Tool</title>
    <style>
        body { font-family: Arial; background: #1a1a2e; color: white; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #16213e; padding: 30px; border-radius: 10px; }
        h1 { color: #e94560; }
        .success { color: #00b894; }
        .error { color: #d63031; }
        .warning { color: #fdcb6e; }
        pre { background: #0f3460; padding: 15px; border-radius: 5px; overflow: auto; }
        .section { margin: 20px 0; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 5px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; margin-left: 10px; }
        .badge-success { background: #00b894; color: white; }
        .badge-error { background: #d63031; color: white; }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔍 PayMongo Diagnostic Tool</h1>";

// ============================================
// SECTION 1: Check PHP Information
// ============================================
echo "<div class='section'>";
echo "<h2>📋 PHP Information</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// Check cURL
if (extension_loaded('curl')) {
    echo "<p class='success'>✓ cURL extension is loaded</p>";
    $curl_version = curl_version();
    echo "<p>cURL Version: " . $curl_version['version'] . "</p>";
} else {
    echo "<p class='error'>✗ cURL extension is NOT loaded</p>";
}

// Check JSON
if (extension_loaded('json')) {
    echo "<p class='success'>✓ JSON extension is loaded</p>";
} else {
    echo "<p class='error'>✗ JSON extension is NOT loaded</p>";
}

// Check OpenSSL
if (extension_loaded('openssl')) {
    echo "<p class='success'>✓ OpenSSL extension is loaded</p>";
} else {
    echo "<p class='error'>✗ OpenSSL extension is NOT loaded</p>";
}
echo "</div>";

// ============================================
// SECTION 2: Check Composer Installation
// ============================================
echo "<div class='section'>";
echo "<h2>📦 Composer Installation</h2>";

// Check if vendor folder exists
if (file_exists(__DIR__ . '/vendor')) {
    echo "<p class='success'>✓ vendor folder exists</p>";
    
    // Check if autoload.php exists
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "<p class='success'>✓ vendor/autoload.php exists</p>";
        require __DIR__ . '/vendor/autoload.php';
    } else {
        echo "<p class='error'>✗ vendor/autoload.php NOT found!</p>";
    }
} else {
    echo "<p class='error'>✗ vendor folder NOT found! Run 'composer install' first.</p>";
}
echo "</div>";

// ============================================
// SECTION 3: Check PayMongo Installation
// ============================================
echo "<div class='section'>";
echo "<h2>💳 PayMongo Package Check</h2>";

// Check if paymongo folder exists in vendor
if (file_exists(__DIR__ . '/vendor/paymongo')) {
    echo "<p class='success'>✓ PayMongo folder exists in vendor</p>";
    
    // List all PayMongo files
    $paymongo_files = glob(__DIR__ . '/vendor/paymongo/*/*.php');
    if (!empty($paymongo_files)) {
        echo "<p class='success'>✓ Found " . count($paymongo_files) . " PayMongo files</p>";
        echo "<details>";
        echo "<summary>Click to see PayMongo files</summary>";
        echo "<pre>";
        foreach ($paymongo_files as $file) {
            echo str_replace(__DIR__, '', $file) . "\n";
        }
        echo "</pre>";
        echo "</details>";
    } else {
        echo "<p class='error'>✗ No PayMongo PHP files found!</p>";
    }
} else {
    echo "<p class='error'>✗ PayMongo folder NOT found in vendor!</p>";
    echo "<p>Try running: composer require paymongo/paymongo-php</p>";
}
echo "</div>";

// ============================================
// SECTION 4: Check PayMongo Classes
// ============================================
echo "<div class='section'>";
echo "<h2>🔧 PayMongo Class Check</h2>";

$classes_to_check = [
    'Paymongo\\PaymongoClient',
    'Paymongo\\Paymongo',
    'Paymongo\\Api\\PaymentIntent',
    'Paymongo\\Api\\PaymentMethod',
    'Paymongo\\Api\\Source',
    'Paymongo\\Api\\Webhook'
];

foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "<p class='success'>✓ Class found: $class</p>";
    } else {
        echo "<p class='warning'>? Class not found: $class</p>";
    }
}
echo "</div>";

// ============================================
// SECTION 5: Test API Connection
// ============================================
echo "<div class='section'>";
echo "<h2>🌐 PayMongo API Connection Test</h2>";

$secret_key = 'sk_test_CuXgiJJHcBEX24FTGBE6KxPd';
$public_key = 'pk_test_PQdr5QWdEXHTJLcs6RXyYjM7';

echo "<p><strong>Secret Key:</strong> " . substr($secret_key, 0, 10) . "..." . substr($secret_key, -5) . "</p>";
echo "<p><strong>Public Key:</strong> " . substr($public_key, 0, 10) . "..." . substr($public_key, -5) . "</p>";

// Test 1: Basic cURL connection
echo "<h3>Test 1: Basic API Connection</h3>";

$ch = curl_init('https://api.paymongo.com/v1/payment_intents');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode($secret_key . ':')
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_info = curl_getinfo($ch);
curl_close($ch);

echo "<p><strong>HTTP Status Code:</strong> " . $http_code . "</p>";

if ($curl_error) {
    echo "<p class='error'>❌ cURL Error: $curl_error</p>";
} else {
    if ($http_code == 200) {
        echo "<p class='success'>✅ Connection successful! (HTTP 200)</p>";
    } elseif ($http_code == 401) {
        echo "<p class='error'>❌ Authentication failed (HTTP 401) - Check your API keys</p>";
    } elseif ($http_code == 403) {
        echo "<p class='error'>❌ Forbidden (HTTP 403)</p>";
    } elseif ($http_code == 404) {
        echo "<p class='error'>❌ API endpoint not found (HTTP 404)</p>";
    } else {
        echo "<p class='warning'>⚠️ Unexpected HTTP code: $http_code</p>";
    }
    
    echo "<details>";
    echo "<summary>Click to see API response</summary>";
    echo "<pre>";
    print_r(json_decode($response, true));
    echo "</pre>";
    echo "</details>";
}

// Test 2: Create a small payment intent
echo "<h3>Test 2: Create Test Payment Intent</h3>";

if (!class_exists('Paymongo\\PaymongoClient')) {
    echo "<p class='warning'>⚠️ PayMongoClient class not found, skipping test</p>";
} else {
    try {
        $client = new Paymongo\PaymongoClient($secret_key);
        echo "<p class='success'>✓ PayMongoClient initialized</p>";
        
        $test_payment = [
            'amount' => 10000, // ₱100.00
            'currency' => 'PHP',
            'payment_method_allowed' => ['gcash'],
            'description' => 'Diagnostic Test',
            'statement_descriptor' => 'DIAGNOSTIC',
            'metadata' => [
                'test' => 'true',
                'source' => 'diagnostic'
            ]
        ];
        
        $paymentIntent = $client->paymentIntents->create($test_payment);
        echo "<p class='success'>✅ Payment intent created successfully!</p>";
        
        echo "<details>";
        echo "<summary>Click to see payment intent details</summary>";
        echo "<pre>";
        print_r($paymentIntent);
        echo "</pre>";
        echo "</details>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error creating payment intent: " . $e->getMessage() . "</p>";
        
        if (method_exists($e, 'getResponse')) {
            echo "<details>";
            echo "<summary>Click to see error details</summary>";
            echo "<pre>";
            print_r($e->getResponse());
            echo "</pre>";
            echo "</details>";
        }
    }
}
echo "</div>";

// ============================================
// SECTION 6: Troubleshooting Recommendations
// ============================================
echo "<div class='section'>";
echo "<h2>🔧 Troubleshooting Recommendations</h2>";

echo "<ul>";
if (!extension_loaded('curl')) {
    echo "<li class='error'>❌ Enable cURL in php.ini: extension=curl</li>";
}
if (!file_exists(__DIR__ . '/vendor')) {
    echo "<li class='error'>❌ Run: composer install</li>";
}
if (!file_exists(__DIR__ . '/vendor/paymongo')) {
    echo "<li class='error'>❌ Run: composer require paymongo/paymongo-php</li>";
}
if ($curl_error) {
    echo "<li class='warning'>⚠️ SSL/cURL error detected. Try disabling Avast temporarily</li>";
    echo "<li class='warning'>⚠️ Or add to php.ini: curl.cainfo = \"C:/xampp/php/extras/ssl/cacert.pem\"</li>";
}
if (isset($http_code) && $http_code == 401) {
    echo "<li class='error'>❌ Invalid API keys. Get new test keys from PayMongo dashboard</li>";
}
echo "<li>📝 Check XAMPP error logs: C:\\xampp\\php\\logs\\php_error_log</li>";
echo "<li>📝 Check Apache logs: C:\\xampp\\apache\\logs\\error.log</li>";
echo "</ul>";
echo "</div>";

// ============================================
// SECTION 7: System Information
// ============================================
echo "<div class='section'>";
echo "<h2>🖥️ System Information</h2>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Path:</strong> " . __FILE__ . "</p>";
echo "<p><strong>Current Directory:</strong> " . __DIR__ . "</p>";
echo "<p><strong>PHP.ini path:</strong> " . php_ini_loaded_file() . "</p>";

// Check if we can write to logs
$log_path = 'C:\\xampp\\php\\logs\\php_error_log';
if (is_writable(dirname($log_path))) {
    echo "<p class='success'>✓ Log directory is writable</p>";
} else {
    echo "<p class='warning'>⚠️ Log directory may not be writable</p>";
}
echo "</div>";

echo "</div></body></html>";
?>
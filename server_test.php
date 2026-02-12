<?php
// ==========================================
// CONFIGURATION
// ==========================================
date_default_timezone_set('Asia/Manila');
// Production/Standard Credentials
define('MERCHANT_ID', 'TEST');
define('PASSWORD',    'ZnVxU5V31A3itdK'); 

// Test Environment Credentials (az-sg1)
define('TEST_MERCHANT_ID', 'TESTARVIN');
define('TEST_PASSWORD',    '1AqlJyPf49MtnaUbOAiN4T');


// Master List of Servers
$serverList = [
    'az-sg11',
    'az-sg10',
    'az-sg7',
    'az-sg8',
    'az-sg9',
    'az-sg12',
    'live',
    'secure',
    'gw',
    'az-sg1' // (test)
];

// User Details for Testing
$testData = [
    'Amount'      => 1.00,
    'Currency'    => 'PHP',
    'Description' => 'ServerTesting',
    'Email'       => 'arvin.retuya@dragonpay.ph',
    'MobileNo'    => '09454309871'
];

// ==========================================
// LOGIC: HANDLE SELECTION
// ==========================================

// Get the selected server from the URL/Form, default to 'All'
$selectedOption = isset($_GET['server']) ? $_GET['server'] : 'All';

// Filter the list of servers to test based on selection
$serversToTest = [];

if ($selectedOption === 'All') {
    $serversToTest = $serverList;
} else {
    // If a specific server is selected, check if it's valid
    if (in_array($selectedOption, $serverList)) {
        $serversToTest[] = $selectedOption;
    } else {
        // Fallback to all if invalid selection
        $serversToTest = $serverList; 
    }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function testRestApi($server, $creds, $data) {
    $txnId = 'REST-' . time() . '-' . mt_rand(1000, 9999);
    $url = "https://{$server}.dragonpay.ph/api/collect/v1/{$txnId}/post";
    
    $payload = $data;
    $payload['ProcId'] = "";
    
    $jsonData = json_encode($payload);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    
    // Auth header if required by specific endpoint configuration
    curl_setopt($ch, CURLOPT_USERPWD, $creds['id'] . ":" . $creds['key']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 

    $start = microtime(true);
    $response = curl_exec($ch);
    $end = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $duration = round(($end - $start) * 1000, 0) . 'ms';
    $error = curl_error($ch);
    
    curl_close($ch);

    return [
        'type' => 'REST',
        'code' => $httpCode,
        'time' => $duration,
        'response' => $httpCode == 200 ? "Success" : ($error ?: substr(strip_tags($response), 0, 50) . "...")
    ];
}

function testSoapApi($server, $creds, $data) {
    $txnId = 'SOAP-' . time() . '-' . mt_rand(1000, 9999);
    $url = "https://{$server}.dragonpay.ph/dragonpaywebservice/MerchantService.asmx";
    
    $xml = '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <GetTxnToken xmlns="http://api.dragonpay.ph/">
                  <merchantId>' . $creds['id'] . '</merchantId>
                  <password>' . $creds['key'] . '</password>
                  <merchantTxnId>' . $txnId . '</merchantTxnId>
                  <amount>' . $data['Amount'] . '</amount>
                  <currency>' . $data['Currency'] . '</currency>
                  <description>' . $data['Description'] . '</description>
                  <email>' . $data['Email'] . '</email>
                </GetTxnToken>
              </soap:Body>
            </soap:Envelope>';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/xml; charset=utf-8',
        'Content-Length: ' . strlen($xml),
        'SOAPAction: "http://api.dragonpay.ph/GetTxnToken"'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $start = microtime(true);
    $response = curl_exec($ch);
    $end = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $duration = round(($end - $start) * 1000, 0) . 'ms';
    $error = curl_error($ch);
    
    curl_close($ch);

    $statusMsg = ($httpCode == 200) ? "OK (XML Received)" : ($error ?: "HTTP Error");

    return [
        'type' => 'SOAP',
        'code' => $httpCode,
        'time' => $duration,
        'response' => $statusMsg
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dragonpay Server Tester</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f9f9f9; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        
        /* Controls */
        .controls { margin-bottom: 20px; padding: 15px; background: #eee; border-radius: 5px; display: flex; align-items: center; gap: 10px; justify-content: space-between; }
        .form-group { display: flex; align-items: center; gap: 10px; }
        select, button { padding: 8px 12px; font-size: 14px; }
        button { background-color: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        button:hover { background-color: #0056b3; }

        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        
        /* Status Colors */
        .code-200 { background-color: #d4edda; color: #155724; } /* Green */
        .code-error { background-color: #f8d7da; color: #721c24; } /* Red */
        .code-warn { background-color: #fff3cd; color: #856404; } /* Yellow/Orange */
    </style>
</head>
<body>

<div class="container">
    <h2>Dragonpay Server Connectivity Test</h2>
    
    <div class="controls">
        <form method="GET" action="" class="form-group">
            <label for="server"><strong>Select Target:</strong></label>
            <select name="server" id="server">
                <option value="All" <?php echo ($selectedOption == 'All') ? 'selected' : ''; ?>>All Servers</option>
                <?php foreach ($serverList as $srv): ?>
                    <option value="<?php echo $srv; ?>" <?php echo ($selectedOption == $srv) ? 'selected' : ''; ?>>
                        <?php echo $srv; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Run Test</button>
        </form>
        <div>
           <strong>Status:</strong> Ready
        </div>
    </div>

    <p>Targeting: <strong><?php echo htmlspecialchars($selectedOption); ?></strong> | Time: <?php echo date('H:i:s'); ?></p>

    <table>
        <thead>
            <tr>
                <th>Server</th>
                <th>API Type</th>
                <th>HTTP Code</th>
                <th>Latency</th>
                <th>Result / Error</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($serversToTest)): ?>
                <tr><td colspan="5" style="text-align:center;">No valid servers selected.</td></tr>
            <?php else: ?>
                <?php foreach ($serversToTest as $server): ?>
                    <?php 
                        // Determine Credentials
                        if ($server === 'az-sg1') {
                            $creds = ['id' => TEST_MERCHANT_ID, 'key' => TEST_PASSWORD];
                        } else {
                            $creds = ['id' => MERCHANT_ID, 'key' => PASSWORD];
                        }

                        // Run Tests
                        $restResult = testRestApi($server, $creds, $testData);
                        $soapResult = testSoapApi($server, $creds, $testData);
                        
                        $results = [$restResult, $soapResult];
                    ?>

                    <?php foreach ($results as $res): ?>
                        <tr class="<?php echo ($res['code'] == 200) ? 'code-200' : 'code-error'; ?>">
                            <td><strong><?php echo $server; ?></strong></td>
                            <td><?php echo $res['type']; ?></td>
                            <td><?php echo $res['code']; ?></td>
                            <td><?php echo $res['time']; ?></td>
                            <td><?php echo htmlspecialchars($res['response']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
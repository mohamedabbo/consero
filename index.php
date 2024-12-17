<?php

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // Check for errors in the uploaded file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Error uploading file: " . $file['error']);
    }

    // Azure Storage Account Configuration
    $accountName = "conseroserendipity";
    $accountKey = "+Me8wc3q5y5GMU8xIYK7DOn4nXkTK8zZLAozNdJePqXZLEvsKg8J6EwHO96qPBucUBtoNTEtw+w5+ASteHhzvw==";
    $containerName = "cheques";
    $blobName = $file['name']; // Use the uploaded file's name as the blob name

    // Generate SAS Token
    $sasToken = generateSasToken($accountName, $accountKey, $containerName, $blobName);
    $sasToken = "sv=2022-11-02&ss=bfqt&srt=sco&sp=rwdlacupiytfx&se=2026-12-31T23:46:41Z&st=2024-12-13T15:46:41Z&spr=https,http&sig=zyfuYATNhCWbUDnzmBgQfL5keJdWPsSC2z1ZFeZb9TE%3D";
    // Construct Blob URL with SAS Token
    $blobUrl = "https://$accountName.blob.core.windows.net/$containerName/$blobName?$sasToken";
    //$blobUrl = "https://conseroserendipity.blob.core.windows.net/?sv=2022-11-02&ss=bfqt&srt=sco&sp=rwdlacupiytfx&se=2026-12-31T23:46:41Z&st=2024-12-13T15:46:41Z&spr=https,http&//sig=zyfuYATNhCWbUDnzmBgQfL5keJdWPsSC2z1ZFeZb9TE%3D";

    // Path to the uploaded file on the server
    $filePath = $file['tmp_name'];
    $originalFileName = $file['name'];

    // Upload file to Azure Blob Storage
    uploadToAzureBlob($blobUrl, $filePath, $originalFileName);
}

// Function to generate SAS Token
function generateSasToken($accountName, $accountKey, $containerName, $blobName) {
    $version = "2020-08-04"; // Azure Storage API version
    $resource = "b"; // b = Blob, c = Container
    $permissions = "rw"; // Permissions: Read & Write
    $startTime = gmdate("Y-m-d\TH:i:s\Z", time()); // Start time in UTC
    $expiryTime = gmdate("Y-m-d\TH:i:s\Z", strtotime("+1 hour")); // Expiry time in UTC

    // Canonicalized resource format: /{accountName}/{containerName}/{blobName}
    $canonicalizedResource = sprintf("/%s/%s/%s", $accountName, $containerName, $blobName);

    // String to sign
    $stringToSign = sprintf(
        "%s\n%s\n%s\n%s\n%s\n%s\n%s\n%s\n%s\n%s\n",
        $permissions,
        $startTime,
        $expiryTime,
        $canonicalizedResource,
        "", // Identifier
        "", // IP
        "https", // Protocol
        $version,
        "", // Cache-control
        ""  // Content-disposition
    );

    // Decode the account key
    $decodedKey = base64_decode($accountKey);

    // Compute the HMAC-SHA256 hash
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

    // Construct the SAS token
    return http_build_query([
        'sv' => $version,
        'sr' => $resource,
        'sp' => $permissions,
        'st' => $startTime,
        'se' => $expiryTime,
        'spr' => 'https',
        'sig' => $signature,
    ]);
}

// Function to upload file to Azure Blob Storage
function uploadToAzureBlob($sasUrl, $filePath, $originalFileName) {
    // Open the file to upload
    $file = fopen($filePath, 'r');
    if (!$file) {
        die("Failed to open file: $filePath");
    }

    // Initialize cURL
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $sasUrl);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $file); // File to upload
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filePath)); // File size
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-ms-blob-type: BlockBlob', // Required header for Azure Blob Storage
    ]);

    // Execute the request
    $response = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // Close cURL and the file
    curl_close($ch);
    fclose($file);
    
    // Check response
    if ($httpStatus === 201) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="results.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Write the header row
        fputcsv($output, [
            'Date', 'Invoice No', 'Customer Name', 'Sequence', 'Amount', 'Cheque No'
        ]);

        for ($i = 1; $i < 14; $i++) {
            try {
                    // Azure API URL and Subscription Key
                    $endpoint = "https://serendipitydocintelligencepaid.cognitiveservices.azure.com";
                    $apiKey = "6IgoH6zTGHGPzJvtkbcV2zYjjo0FTxRXVhAuilUVzqDzXpfPmfGHJQQJ99ALACrJL3JXJ3w3AAALACOGHOu1";
                    $modelId = "Serendipity20241212"; // Replace with the correct model ID
                    $apiVersion = "2024-11-30";

                    // Document URL
                    $documentUrl = "https://conseroserendipity.blob.core.windows.net/cheques/".$originalFileName;

                    // Prepare the data for the POST request
                    $data = json_encode([
                        "urlSource" => $documentUrl
                    ]);

                    // Set up the headers
                    $headers = [
                        "Content-Type: application/json",
                        "Ocp-Apim-Subscription-Key: $apiKey"
                    ];

                    // Initialize cURL session
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "{$endpoint}/documentintelligence/documentModels/{$modelId}:analyze?api-version={$apiVersion}&pages=".$i);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    // Enable verbose output for debugging
                    curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['urlSource' => 'https://conseroserendipity.blob.core.windows.net/cheques/'.$originalFileName]));

                // Capture verbose output
                $verbose = fopen('php://temp', 'w+');
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_STDERR, $verbose);  // Redirect verbose output to a temporary stream

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                // Close cURL session
                curl_close($ch);

                // Get the verbose output from the temporary stream
                rewind($verbose);
                $verboseOutput = stream_get_contents($verbose);

                // Output the verbose response for debugging
                //echo "Verbose Output:\n" . $verboseOutput;
                //exit();
                // Now, extract the apim-request-id from the verbose output using a regex
                preg_match('/apim-request-id:\s([a-f0-9\-]{36})/', $verboseOutput, $matches);

                // Check if we found the apim-request-id
                if (isset($matches[1])) {
                    $apimRequestId = $matches[1];
                    //echo "apim-request-id: " . $apimRequestId . "\n";  // Example: 5b080100-5d6b-46ed-9b01-42bb425ae20f

                    $analyzing = true;
                    $counter = 0;
                    while($analyzing){
                    // Now, perform the GET request with the apim-request-id
                    $endpoint = "https://serendipitydocintelligencepaid.cognitiveservices.azure.com";  // Replace with your endpoint
                    $modelId = "Serendipity20241212";  // Replace with your model ID
                    $getUrl = "{$endpoint}/documentintelligence/documentModels/{$modelId}/analyzeResults/{$apimRequestId}?api-version=2024-11-30";

                    // Initialize cURL for the GET request
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, $getUrl);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                        "Ocp-Apim-Subscription-Key: 6IgoH6zTGHGPzJvtkbcV2zYjjo0FTxRXVhAuilUVzqDzXpfPmfGHJQQJ99ALACrJL3JXJ3w3AAALACOGHOu1"  // Same subscription key as before
                    ]);

                    $result = curl_exec($ch2);
                    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    $data = json_decode($result, true);
                    //echo $result;
                    $status = isset($data['status']) ? $data['status'] : null;
                    if($status == "succeeded"){
                        $analyzing = false;
                        $json_response = $result;
                    }
                    else{
                        //echo "sleeping";
                        sleep(5);
                        $counter++;
                    }

                    if($counter == 30){
                        $analyzing = false;
                        break;
                    }
                    // Check if the request was successful
                    //if ($httpCode2 == 200) {
                        //echo "Analysis Result:\n" . $result;  // Output the analysis result
                    //} else {
                        //echo "Error: Failed to get analysis results. HTTP Code: $httpCode2\n";
                    //}

                    // Close the GET request session
                    curl_close($ch2);
                    }
                } else {
                    echo "apim-request-id not found.";
                }

                if(!empty($json_response)){
                    //echo $json_response;
                    $data = json_decode($json_response, true);
                    $analyze_fields = $data['analyzeResult']['documents'][0]['fields'];
                    //print_r($analyze_fields);

                    //if (isset($analyze_fields['consero_date_1']['valueString'])) {
                        $consero_date_1 = "'".$analyze_fields['consero_date_1']['valueString']."'";
                        $consero_invoice_no_1 = $analyze_fields['consero_invoice_no_1']['valueString'];
                        $consero_invoice_no_1 = preg_replace('/\D/', '', $consero_invoice_no_1);
                        $consero_customer_name_1 = $analyze_fields['consero_customer_name_1']['valueString'];
                        $consero_sequence_1 = $analyze_fields['consero_sequence_1']['valueString'];
                        $consero_amount_1 = $analyze_fields['consero_amount_1']['valueString'];
                        $consero_cheque_no_1 = $analyze_fields['consero_cheque_no_1']['valueString'];

                        $consero_date_2 = "'".$analyze_fields['consero_date_2']['valueString']."'";
                        $consero_invoice_no_2 = $analyze_fields['consero_invoice_no_2']['valueString'];
                        $consero_invoice_no_2 = preg_replace('/\D/', '', $consero_invoice_no_2);
                        $consero_customer_name_2 = $analyze_fields['consero_customer_name_2']['valueString'];
                        $consero_sequence_2 = $analyze_fields['consero_sequence_2']['valueString'];
                        $consero_amount_2 = $analyze_fields['consero_amount_2']['valueString'];
                        $consero_cheque_no_2 = $analyze_fields['consero_cheque_no_2']['valueString'];

                        $consero_date_3 = "'".$analyze_fields['consero_date_3']['valueString']."'";
                        $consero_invoice_no_3 = $analyze_fields['consero_invoice_no_3']['valueString'];
                        $consero_invoice_no_3 = preg_replace('/\D/', '', $consero_invoice_no_3);
                        $consero_customer_name_3 = $analyze_fields['consero_customer_name_3']['valueString'];
                        $consero_sequence_3 = $analyze_fields['consero_sequence_3']['valueString'];
                        $consero_amount_3 = $analyze_fields['consero_amount_3']['valueString'];
                        $consero_cheque_no_3 = $analyze_fields['consero_cheque_no_3']['valueString'];

                        //echo $consero_date_1.",".$consero_invoice_no_1.",".$consero_customer_name_1.",".$consero_sequence_1.",".$consero_amount_1.",".$consero_cheque_no_1;
                        //echo $consero_date_2.",".$consero_invoice_no_2.",".$consero_customer_name_2.",".$consero_sequence_2.",".$consero_amount_2.",".$consero_cheque_no_2;
                        //echo $consero_date_3.",".$consero_invoice_no_3.",".$consero_customer_name_3.",".$consero_sequence_3.",".$consero_amount_3.",".$consero_cheque_no_3;

                        // Write each group of data to the CSV
                        if(!empty($consero_customer_name_1)){
                            fputcsv($output, [
                                $consero_date_1,
                                $consero_invoice_no_1,
                                $consero_customer_name_1,
                                $consero_sequence_1,
                                $consero_amount_1,
                                $consero_cheque_no_1
                            ]);
                        }

                        if(!empty($consero_customer_name_2)){
                            fputcsv($output, [
                                $consero_date_2,
                                $consero_invoice_no_2,
                                $consero_customer_name_2,
                                $consero_sequence_2,
                                $consero_amount_2,
                                $consero_cheque_no_2
                            ]);
                        }

                        if(!empty($consero_customer_name_3)){
                            fputcsv($output, [
                                $consero_date_3,
                                $consero_invoice_no_3,
                                $consero_customer_name_3,
                                $consero_sequence_3,
                                $consero_amount_3,
                                $consero_cheque_no_3
                            ]);
                        }
                }
                // Check the response
                if ($httpCode == 202) {
                    // If successful, parse and display the results
                    //echo "<p>Request successful! You can now poll the operation status.</p>";
                    //$responseData = json_decode($response, true);
                    //echo "<pre>" . print_r($responseData, true) . "</pre>";

                    // Extract the operation location from the response headers
                    //$operationLocation = $responseData['Operation-Location'] ?? 'No operation location provided';
                    //echo "<p>Operation location: $operationLocation</p>";
                } else {
                    // Handle the error response
                    //echo "<p>Error occurred: HTTP Code $httpCode</p>";
                    //echo "<pre>" . htmlspecialchars($response) . "</pre>";
                }
            } 
            catch (Exception $e) {
                exit();
            }
        }
        // Close the file handle
        fclose($output);
        exit();
    } else {
        echo "Failed to upload file. HTTP Status: $httpStatus<br>";
        echo "Response: $response";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Consero Cheque Scanner</title>
</head>
<body>
    <h1>Consero Cheque Scanner</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="file">Select Cheque File</label>
        <input type="file" name="file" id="file" required>
        <button type="submit">Create CSV</button>
    </form>
</body>
</html>

<?php
// Save form submission to protected file

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize and validate inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $interests = isset($_POST['interests']) ? $_POST['interests'] : array();
    
    // Validate required fields
    if (!$name || !$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and email are required']);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }
    
    // Sanitize interests
    $valid_interests = ['grand_opening', 'daily_specials', 'catering'];
    $filtered_interests = array_intersect($interests, $valid_interests);
    
    // Create submission data
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $submission_data = [
        'timestamp' => $timestamp,
        'name' => $name,
        'email' => $email,
        'interests' => $filtered_interests,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent
    ];
    
    // Convert to JSON
    $json_data = json_encode($submission_data, JSON_PRETTY_PRINT) . "\n";
    
    // Create submissions directory if it doesn't exist
    $submissions_dir = __DIR__ . '/submissions';
    if (!file_exists($submissions_dir)) {
        mkdir($submissions_dir, 0755, true);
    }
    
    // Save to file
    $filename = $submissions_dir . '/submissions.json';
    $result = file_put_contents($filename, $json_data, FILE_APPEND | LOCK_EX);
    
    if ($result !== false) {
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for subscribing! We\'ll keep you updated on our grand opening.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save submission']);
    }
    
} else {
    // Not a POST request
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

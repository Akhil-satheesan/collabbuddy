<?php
// FILE: verify_github.php - GitHub API-യിൽ നിന്ന് ഡാറ്റ എടുക്കുന്നു.

header('Content-Type: application/json');

$username = $_GET['username'] ?? '';


if (empty($username)) {
    http_response_code(400); // Bad Request
    echo json_encode(['valid' => false, 'message' => 'Username cannot be empty.']);
    exit;
}


$url = "https://api.github.com/users/" . urlencode($username);


$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// GitHub API-ക്ക് ഒരു User-Agent നിർബന്ധമാണ്.
curl_setopt($ch, CURLOPT_USERAGENT, 'CollabBuddyGitHubVerifier'); 

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    // 200 OK: 
    echo json_encode(['valid' => true, 'message' => '✅ GitHub profile is valid!']);
} elseif ($http_code === 404) {
    
    echo json_encode(['valid' => false, 'message' => '🚫 GitHub user not found.']);
} else {
    // മറ്റ് എററുകൾ (ഉദാഹരണത്തിന്, Rate Limiting)
    http_response_code(500); // Internal Server Error
    echo json_encode(['valid' => false, 'message' => '⚠️ Verification error. Try again.']);
}
?>
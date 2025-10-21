<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require 'include/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'host') {
    header("Location: signup.php");
    exit;
}

if (isset($_SESSION['host_profile_completed']) && $_SESSION['host_profile_completed'] === true) {
    header("Location: host_dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error   = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_host'])) {
    $host_type      = trim($_POST['host_type']);
    $about_host     = trim($_POST['about_host']);
    $company_name = trim($_POST['company_name']) ?? null;
    $product_name = trim($_POST['product_name']) ?? null;
    $website_url  = trim($_POST['website_url']) ?? null;
    
    if (!empty($website_url) && !filter_var($website_url, FILTER_VALIDATE_URL)) {
        $error = "❌ Invalid website URL format.";
    }

    if (!$error && $host_type === 'Business' && (empty($company_name) || empty($product_name))) {
        $error = "❌ Company and Product name required for Business hosts.";
    } elseif(!$error) {
        $stmt = $conn->prepare("INSERT INTO hosts 
             (host_id, host_type, about_host, company_name, product_name, website_url) 
             VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $host_type, $about_host, $company_name, $product_name, $website_url);

        if ($stmt->execute()) {
            $_SESSION['host_profile_completed'] = true; 
            echo "<script>alert('✅ Host profile completed! Redirecting to Dashboard.'); window.location.href='host_dashboard.php?role=host';</script>";
            exit;
        } else {
            $error = "❌ Error saving host details: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CollabBuddy - Host Signup</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; }
    .signup-container { max-width: 450px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #374151; }
    input[type="text"], input[type="url"], textarea, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; }
    textarea { resize: vertical; min-height: 100px; }
    .btn-primary { background: #4f46e5; color: white; padding: 12px; border: none; border-radius: 8px; width: 100%; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s; }
    .btn-primary:hover { background: #4338ca; }
    .hidden { display: none; }
    .error { color: #dc2626; text-align:center; margin-bottom: 15px; font-weight: 500; padding: 10px; border: 1px solid #fee2e2; background: #fef2f2; border-radius: 6px; }
    .subtitle { text-align: center; color: #6b7280; margin-bottom: 25px; }
  </style>
</head>
<body>
<?php
require 'include/navbar.php'; 
?>

<div class="signup-container">
  <h2>Complete Your Host Profile</h2>
  <p class="subtitle">Fill in the extra details as a <b>host</b></p>

  <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

  <form method="POST" class="signup-form" onsubmit="return validateForm()">
    <div class="form-group">
      <label>Host Type</label>
      <select name="host_type" id="hostType" class="styled-select" required>
        <option value="">-- Select Type --</option>
        <option value="Student" <?php if(isset($host_type) && $host_type == 'Student') echo 'selected'; ?>>🎓 Student</option>
        <option value="Freelancer" <?php if(isset($host_type) && $host_type == 'Freelancer') echo 'selected'; ?>>💻 Freelancer</option>
        <option value="Business" <?php if(isset($host_type) && $host_type == 'Business') echo 'selected'; ?>>🏢 Business</option>
      </select>
    </div>

    <div class="form-group">
      <label>About You</label>
      <textarea name="about_host" id="aboutHost" placeholder="Brief description about yourself" required><?php echo htmlspecialchars($_POST['about_host'] ?? ''); ?></textarea>
    </div>

        <div id="businessFields" class="hidden">
      <div class="form-group">
        <label>Company Name <span class="required-star">*</span></label>
        <input type="text" name="company_name" id="companyName" placeholder="Enter company name" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label>Product Name <span class="required-star">*</span></label>
        <input type="text" name="product_name" id="productName" placeholder="Enter product name" value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>">
      </div>
    </div>

    <div class="form-group">
      <label>Website (optional)</label>
      <input type="url" name="website_url" id="websiteUrl" placeholder="https://example.com" value="<?php echo htmlspecialchars($_POST['website_url'] ?? ''); ?>">
    </div>

    <button type="submit" name="save_host" class="btn-primary">Save & Continue</button>
  </form>

  <p class="redirect">Already have an account? <a href="login.php">Login here</a></p>
</div>

<script>
const hostType = document.getElementById('hostType');
const businessFields = document.getElementById('businessFields');
const companyNameInput = document.getElementById('companyName');
const productNameInput = document.getElementById('productName');

function toggleBusinessFields() {
  if (hostType.value === 'Business') {
    businessFields.classList.remove('hidden');
  } else {
    businessFields.classList.add('hidden');
  }
}

function validateForm() {
    if (hostType.value === 'Business') {
        if (companyNameInput.value.trim() === '') {
            alert('Please enter the Company Name.');
            companyNameInput.focus();
            return false;
        }
        if (productNameInput.value.trim() === '') {
            alert('Please enter the Product Name.');
            productNameInput.focus();
            return false;
        }
    }
    return true;
}

hostType.addEventListener('change', toggleBusinessFields);

toggleBusinessFields(); 
</script>
<?php
require 'include/footer.php';
?>
</body>
</html>
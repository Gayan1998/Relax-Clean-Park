<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "You don't have permission to access this page";
    header("Location: dashboard.php");
    exit();
}

include '../includes/db_connection.php';

$success_message = "";
$error_message = "";

// Fetch current business settings
$stmt = $pdo->query("SELECT * FROM business_settings WHERE id = 1");
$business = $stmt->fetch(PDO::FETCH_ASSOC);

// If no settings exist yet, create default ones
if (!$business) {
    $stmt = $pdo->prepare("INSERT INTO business_settings 
                        (business_name, tagline, address, phone, email, registration_number, additional_info, logo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'KAYEL AUTO PARTS',
        'Dealer of All Japan, Indian & China Vehicle Parts',
        'Kurunegala Road, Vithikuliya, Nikaweratiya',
        '077-9632277',
        '',
        '',
        '',
        'logo.png'
    ]);
    
    $stmt = $pdo->query("SELECT * FROM business_settings WHERE id = 1");
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process logo upload if a new one is provided
    $logo = $business['logo']; // Default to current logo
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['logo']['type'], $allowed_types)) {
            $error_message = "Invalid file type. Only JPG, PNG and GIF are allowed.";
        } elseif ($_FILES['logo']['size'] > $max_size) {
            $error_message = "File is too large. Maximum size is 2MB.";
        } else {
            // Generate a unique filename
            $file_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'logo_' . time() . '.' . $file_ext;
            $upload_path = '../assets/images/' . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $logo = $new_filename;
                
                // Delete old logo if it's not the default
                if ($business['logo'] != 'logo.png' && file_exists('../assets/images/' . $business['logo'])) {
                    unlink('../assets/images/' . $business['logo']);
                }
            } else {
                $error_message = "Failed to upload the logo. Please try again.";
            }
        }
    }
    
    if (empty($error_message)) {
        try {
            $stmt = $pdo->prepare("UPDATE business_settings SET 
                                business_name = ?, 
                                tagline = ?, 
                                address = ?, 
                                phone = ?, 
                                email = ?, 
                                registration_number = ?, 
                                additional_info = ?, 
                                logo = ? 
                                WHERE id = 1");
            
            $result = $stmt->execute([
                $_POST['business_name'],
                $_POST['tagline'],
                $_POST['address'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['registration_number'],
                $_POST['additional_info'],
                $logo
            ]);
            
            if ($result) {
                $success_message = "Business settings updated successfully!";
                
                // Refresh the business data
                $stmt = $pdo->query("SELECT * FROM business_settings WHERE id = 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error_message = "Failed to update business settings!";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Settings</title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">    <style>
        :root {
            --dark-bg: #1a1a1a;
            --darker-bg: #141414;
            --card-bg: #242424;
            --border-color: #333;
            --text-primary: #fff;
            --text-secondary: #a0a0a0;
            --accent-green: #4ade80;
            --accent-blue: #60a5fa;
            --accent-red: #f87171;
            --accent-yellow: #b6e134;
            --accent-purple: #a78bfa;
        }
        
        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 900px;
        }
        
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }
        
        .card-header {
            border-bottom: 1px solid var(--border-color);
            background-color: var(--darker-bg);
            color: var(--text-primary);
            padding: 15px;
        }
        
        .logo-preview {
            max-width: 150px;
            max-height: 150px;
            margin-bottom: 10px;
            background-color: white;
            padding: 5px;
            border-radius: 5px;
        }
        
        .form-label {
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .form-control, .form-select {
            background-color: var(--darker-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--darker-bg);
            border-color: var(--accent-blue);
            color: var(--text-primary);
            box-shadow: none;
        }
        
        .btn-primary {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
            color: black;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            color: var(--accent-blue);
            text-decoration: none;
        }
        
        .back-link:hover {
            color: var(--accent-blue);
            opacity: 0.8;
            text-decoration: underline;
        }
        
        .alert-success {
            background-color: var(--accent-green);
            border-color: var(--accent-green);
            color: black;
        }
        
        .alert-danger {
            background-color: var(--accent-red);
            border-color: var(--accent-red);
            color: black;
        }
    </style>
</head>
<body>    <div class="container py-4 py-md-5">
        <a href="dashboard.php" class="back-link mb-3 d-inline-block">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
        
        <div class="card">
            <div class="card-header">
                <h3 class="m-0"><i class="fas fa-store me-2"></i>Business Settings</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="business_name" class="form-label">Business Name</label>
                            <input type="text" class="form-control" id="business_name" name="business_name" 
                                   value="<?php echo htmlspecialchars($business['business_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tagline" class="form-label">Tagline/Slogan</label>
                            <input type="text" class="form-control" id="tagline" name="tagline" 
                                   value="<?php echo htmlspecialchars($business['tagline']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($business['address']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number(s)</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($business['phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($business['email']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="registration_number" class="form-label">Registration Number</label>
                        <input type="text" class="form-control" id="registration_number" name="registration_number" 
                               value="<?php echo htmlspecialchars($business['registration_number']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="additional_info" class="form-label">Additional Information</label>
                        <textarea class="form-control" id="additional_info" name="additional_info" rows="3"><?php echo htmlspecialchars($business['additional_info']); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Business Logo</label>
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <img src="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" alt="Business Logo" class="logo-preview img-thumbnail">
                            </div>
                            <div>
                                <input type="file" class="form-control" id="logo" name="logo">
                                <small class="form-text text-secondary">Recommended size: 200x200px. Max size: 2MB</small>
                            </div>
                        </div>
                    </div>
                      <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show preview of selected logo
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.querySelector('.logo-preview').setAttribute('src', event.target.result);
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>

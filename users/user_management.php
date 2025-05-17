<?php
session_start();
// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin/login.php');
    exit();
}

include '../includes/db_connection.php';
include '../includes/functions.php';

$message = '';
$error = '';

// Handle user operations (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new user
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        
        // Basic validation
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error = "All fields are required";
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Username already exists";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password, email, role, created_at, updated_at)
                        VALUES (:username, :password, :email, :role, NOW(), NOW())
                    ");
                    
                    $stmt->execute([
                        'username' => $username,
                        'password' => $hashed_password,
                        'email' => $email,
                        'role' => $role
                    ]);
                    
                    $message = "User created successfully";
                } catch (PDOException $e) {
                    $error = "Error creating user: " . $e->getMessage();
                }
            }
        }
    }
    
    // Update user
    else if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = trim($_POST['password']);
        
        // Basic validation
        if (empty($username) || empty($email) || empty($role)) {
            $error = "Username, email and role are required";
        } else {
            // Check if username already exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
            $stmt->execute(['username' => $username, 'user_id' => $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Username already exists for another user";
            } else {
                try {
                    // If password is provided, update it as well
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET username = :username, password = :password, email = :email, 
                                role = :role, updated_at = NOW() 
                            WHERE id = :user_id
                        ");
                        $stmt->execute([
                            'username' => $username,
                            'password' => $hashed_password,
                            'email' => $email,
                            'role' => $role,
                            'user_id' => $user_id
                        ]);
                    } else {
                        // Update without changing the password
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET username = :username, email = :email, role = :role, 
                                updated_at = NOW() 
                            WHERE id = :user_id
                        ");
                        $stmt->execute([
                            'username' => $username,
                            'email' => $email,
                            'role' => $role,
                            'user_id' => $user_id
                        ]);
                    }
                    
                    $message = "User updated successfully";
                } catch (PDOException $e) {
                    $error = "Error updating user: " . $e->getMessage();
                }
            }
        }
    }
    
    // Delete user
    else if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $user_id = $_POST['user_id'];
        
        // Don't allow users to delete their own account
        if ($user_id == $_SESSION['user_id']) {
            $error = "You cannot delete your own account";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
                
                $message = "User deleted successfully";
            } catch (PDOException $e) {
                $error = "Error deleting user: " . $e->getMessage();
            }
        }
    }
}

// Fetch all users
try {
    $stmt = $pdo->query("SELECT id, username, email, role, created_at, updated_at FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
    $users = [];
}

// Page title
$page_title = "User Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <?php include '../includes/admin_header.php'; ?>
    <style>
        .user-role-admin {
            background-color: #e2f0d9;
        }
        .user-role-staff {
            background-color: #fff2cc;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2>User Management</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus"></i> Add New User
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="<?php echo 'user-role-' . strtolower($user['role']); ?>">
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                            <td><?php echo htmlspecialchars($user['updated_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-user" 
                                                        data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                                        data-id="<?php echo $user['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-user" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteUserModal" 
                                                        data-id="<?php echo $user['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="edit_password" name="password" 
                                   placeholder="Leave blank to keep current password">
                            <small class="form-text text-muted">Leave blank to keep current password</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete user <strong id="delete_username"></strong>?</p>
                        <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Edit user modal data
            document.querySelectorAll('.edit-user').forEach(function(button) {
                button.addEventListener('click', function() {
                    var id = this.getAttribute('data-id');
                    var username = this.getAttribute('data-username');
                    var email = this.getAttribute('data-email');
                    var role = this.getAttribute('data-role');
                    
                    document.getElementById('edit_user_id').value = id;
                    document.getElementById('edit_username').value = username;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_role').value = role;
                    
                    // Clear password field
                    document.getElementById('edit_password').value = '';
                });
            });
            
            // Delete user modal data
            document.querySelectorAll('.delete-user').forEach(function(button) {
                button.addEventListener('click', function() {
                    var id = this.getAttribute('data-id');
                    var username = this.getAttribute('data-username');
                    
                    document.getElementById('delete_user_id').value = id;
                    document.getElementById('delete_username').textContent = username;
                });
            });
        });
    </script>
</body>
</html>

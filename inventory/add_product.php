<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
include '../includes/db_connection.php'; // Make sure to create this file with your database connection
include_once '../includes/functions.php';

$business = get_business_settings();
// Check if we're adding a service by default
$isAddingService = isset($_GET['type']) && $_GET['type'] === 'service';

// Function to convert number to character
function convertNumberToCharacter($number) {
    try {
        // Character mapping - same as C# function
        $charMapping = ['Z', 'Y', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        
        // Convert the number to string
        $numberStr = strval($number);
        
        // Initialize result string
        $result = "";
        
        // Iterate through each digit and get the corresponding character
        for ($i = 0; $i < strlen($numberStr); $i++) {
            $digitChar = $numberStr[$i];
            if (is_numeric($digitChar)) {
                $digit = intval($digitChar);
                if ($digit >= 0 && $digit < count($charMapping)) {
                    $result .= $charMapping[$digit];
                }
            }
        }
        
        return $result;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return "";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {        if ($_POST['action'] === 'add') {
            // Generate Cost_Code from purchase price if not provided
            if (empty($_POST['cost_code']) && !empty($_POST['purchase_price'])) {
                $_POST['cost_code'] = convertNumberToCharacter(intval($_POST['purchase_price']));
            }
            
            // Add new product
            $stmt = $pdo->prepare("INSERT INTO products (name, item_type, description, purchase_price, selling_price, quantity, category, Cost_Code) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            // If it's a service, set quantity to null or 0 as services don't have inventory
            $quantity = ($_POST['item_type'] === 'service') ? 0 : $_POST['quantity'];
            
            $stmt->execute([
                $_POST['name'],
                $_POST['item_type'],
                $_POST['description'],
                $_POST['purchase_price'],
                $_POST['selling_price'],
                $quantity,
                $_POST['category'],
                $_POST['cost_code']
            ]);
            $_SESSION['message'] = ($_POST['item_type'] === 'service' ? 'Service' : 'Product') . ' added successfully!';
            
        } elseif ($_POST['action'] === 'delete') {
            // Delete product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $_SESSION['message'] = 'Product deleted successfully!';        } elseif ($_POST['action'] === 'update') {
            // Generate Cost_Code from purchase price if not provided
            if (empty($_POST['cost_code']) && !empty($_POST['purchase_price'])) {
                $_POST['cost_code'] = convertNumberToCharacter(intval($_POST['purchase_price']));
            }
            
            // Update existing product
            $stmt = $pdo->prepare("UPDATE products 
                                 SET name = ?, item_type = ?, description = ?, purchase_price = ?, 
                                     selling_price = ?, category = ?, Cost_Code = ?
                                 WHERE id = ?");
                                 
            $stmt->execute([
                $_POST['name'],
                $_POST['item_type'],
                $_POST['description'],
                $_POST['purchase_price'],
                $_POST['selling_price'],
                $_POST['category'],
                $_POST['cost_code'],
                $_POST['id']
            ]);
            $_SESSION['message'] = ($_POST['item_type'] === 'service' ? 'Service' : 'Product') . ' updated successfully!';
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch existing products
$stmt = $pdo->query("SELECT * FROM products ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add <?php echo $isAddingService ? 'Service' : 'Product'; ?> - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <style>
        /* Additional styles specific to product management */

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
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

        .table {
            color: var(--text-primary);
        }

        .table thead th {
            background-color: var(--darker-bg);
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        .table td {
            border-color: var(--border-color);
        }

        .btn-edit {
            color: var(--accent-blue);
            background: transparent;
            border: 1px solid var(--accent-blue);
        }

        .btn-edit:hover {
            background: var(--accent-blue);
            color: var(--darker-bg);
        }

        .message {
            background-color: var(--accent-green);
            color: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .product-table-container {
            max-height: 600px;
            overflow-y: auto;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--darker-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #444;
        }

        .search-container {
            margin-bottom: 1rem;
        }
        
        .search-input {
            background-color: var(--darker-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            width: 100%;
        }
        
        .search-input:focus {
            border-color: var(--accent-blue);
            outline: none;
        }
        
        .no-results {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .form-label{
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        $page_title = $isAddingService ? 'Add New Service' : 'Add New Product';
        include '../includes/page_header.php';
        ?>
        
        <div class="mb-4 d-flex justify-content-between">
            <a href="../inventory/manage_inventory.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Add Product Form -->
        <div class="card mb-4">            <div class="card-header">
                <h4 class="mb-0 text-white">Add New Product or Service</h4>
            </div>
            <div class="card-body">
                <form method="POST" id="addProductForm">
                    <input type="hidden" name="action" value="add">                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="name" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>                        <div class="col-md-4 mb-3">
                            <label for="item_type" class="form-label">Item Type</label>
                            <select class="form-select" id="item_type" name="item_type">
                                <option value="product" <?php echo !$isAddingService ? 'selected' : ''; ?>>Product</option>
                                <option value="service" <?php echo $isAddingService ? 'selected' : ''; ?>>Service</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" name="category">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">                        <div class="col-md-4 mb-3">
                            <label for="purchase_price" class="form-label">Purchase Price</label>
                            <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="selling_price" class="form-label">Selling Price</label>
                            <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cost_code" class="form-label">Cost Code</label>
                            <input type="text" class="form-control" id="cost_code" name="cost_code" readonly>
                            <small class="text-muted">Auto-generated from purchase price</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label" id="quantityLabel">
                                <?php echo $isAddingService ? 'Quantity (N/A for services)' : 'Initial Quantity'; ?>
                            </label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   value="<?php echo $isAddingService ? '0' : ''; ?>" 
                                   <?php echo $isAddingService ? 'disabled' : ''; ?> 
                                   required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </form>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0 text-white">Product List</h4>
                <div class="search-container mt-3">
                    <input type="text" 
                           id="searchInput" 
                           class="search-input" 
                           placeholder="Search products by name, category, or description..."
                           autocomplete="off">
                </div>
            </div>
            <div class="card-body">
                <div class="product-table-container">                    <table class="table">
                        <thead>                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Purchase Price</th>
                                <th>Selling Price</th>
                                <th>Cost Code</th>
                                <th>Quantity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $product['item_type'] === 'service' ? 'info' : 'secondary' ?>">
                                        <?= ucfirst(htmlspecialchars($product['item_type'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($product['category']) ?></td>                                <td><?= htmlspecialchars($product['description']) ?></td>
                                <td>$<?= number_format($product['purchase_price'], 2) ?></td>
                                <td>$<?= number_format($product['selling_price'], 2) ?></td>
                                <td><?= htmlspecialchars($product['Cost_Code']) ?></td>
                                <td><?= $product['item_type'] === 'service' ? 'N/A' : $product['quantity'] ?></td>
                                <td>
                                    <button class="btn btn-edit btn-sm" 
                                            onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="noResults" class="no-results" style="display: none;">
                        No products found matching your search.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>                <div class="modal-body">
                    <form method="POST" id="editProductForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_item_type" class="form-label">Item Type</label>
                            <select class="form-select" id="edit_item_type" name="item_type">
                                <option value="product">Product</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="edit_category" name="category">
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>                        <div class="mb-3">
                            <label for="edit_purchase_price" class="form-label">Purchase Price</label>
                            <input type="number" step="0.01" class="form-control" id="edit_purchase_price" name="purchase_price" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_selling_price" class="form-label">Selling Price</label>
                            <input type="number" step="0.01" class="form-control" id="edit_selling_price" name="selling_price" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_cost_code" class="form-label">Cost Code</label>
                            <input type="text" class="form-control" id="edit_cost_code" name="cost_code" readonly>
                            <small class="text-muted">Auto-generated from purchase price</small>
                        </div>
                        <div class="mb-3" id="edit_quantity_container">
                            <label for="edit_quantity" class="form-label">Current Quantity</label>
                            <input type="number" class="form-control" id="edit_quantity" name="quantity" readonly>
                            <small class="text-muted">Note: To update quantity, use Stock Management</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="submit" class="btn btn-primary">Update Item</button>
                            <button type="button" class="btn btn-danger" onclick="deleteProduct()">Delete Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        let editModal;
        
        // Function to convert number to character (matches the PHP function)
        function convertNumberToCharacter(number) {
            try {
                // Character mapping
                const charMapping = ['Z', 'Y', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
                
                // Convert the number to string
                const numberStr = number.toString();
                
                // Initialize result string
                let result = "";
                
                // Iterate through each digit and get the corresponding character
                for (let i = 0; i < numberStr.length; i++) {
                    const digitChar = numberStr[i];
                    if (!isNaN(parseInt(digitChar))) {
                        const digit = parseInt(digitChar);
                        if (digit >= 0 && digit < charMapping.length) {
                            result += charMapping[digit];
                        }
                    }
                }
                
                return result;
            } catch (error) {
                console.error(error.message);
                return "";
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            editModal = new bootstrap.Modal(document.getElementById('editModal'));
            
            // Auto-generate Cost Code when purchase price changes in the Add form
            const purchasePriceInput = document.getElementById('purchase_price');
            const costCodeInput = document.getElementById('cost_code');
            
            purchasePriceInput.addEventListener('input', function() {
                const price = parseInt(this.value);
                if (!isNaN(price)) {
                    costCodeInput.value = convertNumberToCharacter(price);
                } else {
                    costCodeInput.value = '';
                }
            });
            
            // Auto-generate Cost Code when purchase price changes in the Edit form
            const editPurchasePriceInput = document.getElementById('edit_purchase_price');
            const editCostCodeInput = document.getElementById('edit_cost_code');
            
            editPurchasePriceInput.addEventListener('input', function() {
                const price = parseInt(this.value);
                if (!isNaN(price)) {
                    editCostCodeInput.value = convertNumberToCharacter(price);
                } else {
                    editCostCodeInput.value = '';
                }
            });
        });function editProduct(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            
            // Set the item type in the dropdown
            const itemTypeDropdown = document.getElementById('edit_item_type');
            for(let i = 0; i < itemTypeDropdown.options.length; i++) {
                if(itemTypeDropdown.options[i].value === product.item_type) {
                    itemTypeDropdown.selectedIndex = i;
                    break;
                }
            }
            
            // Toggle quantity visibility based on item type
            const quantityContainer = document.getElementById('edit_quantity_container');
            quantityContainer.style.display = product.item_type === 'service' ? 'none' : 'block';
            
            document.getElementById('edit_category').value = product.category;            document.getElementById('edit_description').value = product.description;
            document.getElementById('edit_purchase_price').value = product.purchase_price;
            document.getElementById('edit_selling_price').value = product.selling_price;
            document.getElementById('edit_quantity').value = product.quantity;
            document.getElementById('edit_cost_code').value = product.Cost_Code || convertNumberToCharacter(parseInt(product.purchase_price));
            
            editModal.show();
        }
        
        // Add event listener to item type dropdown to toggle quantity field
        document.addEventListener('DOMContentLoaded', function() {
            const itemTypeSelect = document.getElementById('item_type');
            const quantityInput = document.getElementById('quantity');
            const quantityLabel = document.querySelector('label[for="quantity"]');
            
            itemTypeSelect.addEventListener('change', function() {
                if(this.value === 'service') {
                    quantityInput.value = 0;
                    quantityInput.disabled = true;
                    quantityLabel.textContent = 'Quantity (N/A for services)';
                } else {
                    quantityInput.disabled = false;
                    quantityLabel.textContent = 'Initial Quantity';
                }
            });
            
            // Do the same for the edit form
            const editItemTypeSelect = document.getElementById('edit_item_type');
            const editQuantityContainer = document.getElementById('edit_quantity_container');
            
            editItemTypeSelect.addEventListener('change', function() {
                editQuantityContainer.style.display = this.value === 'service' ? 'none' : 'block';
            });
        });

        document.getElementById('searchInput').addEventListener('input', function() {
            initializeSearch();
            searchProducts(this.value);
        });
         function initializeSearch() {
            const searchInput = document.getElementById('searchInput');
            const tableBody = document.getElementById('productTableBody');
            const noResults = document.getElementById('noResults');
            const rows = tableBody.getElementsByTagName('tr');

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                let hasVisibleRows = false;

                for (const row of rows) {
                    const name = row.cells[0].textContent.toLowerCase();
                    const category = row.cells[1].textContent.toLowerCase();
                    const description = row.cells[2].textContent.toLowerCase();

                    const matches = name.includes(searchTerm) || 
                                  category.includes(searchTerm) || 
                                  description.includes(searchTerm);

                    row.style.display = matches ? '' : 'none';
                    if (matches) hasVisibleRows = true;
                }

                noResults.style.display = hasVisibleRows ? 'none' : 'block';
            });
        }

        function deleteProduct() {
            if (confirm('Are you sure you want to delete this product?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = document.getElementById('edit_id').value;

                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
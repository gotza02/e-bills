<?php
$page_title = "แก้ไขข้อมูลบิล";
require_once 'header.php'; // Handles session, auth, CSRF, db_connect

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

if (!isset($_GET['bill_id']) || !is_numeric($_GET['bill_id'])) {
    $_SESSION['message'] = "ไม่พบ ID ของบิลที่ต้องการแก้ไข หรือ ID ไม่ถูกต้อง";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$bill_id_to_edit = (int)$_GET['bill_id'];

$bill_data = null;
$items_in_bill_from_db = []; // Stores items with product_id, quantity, price_per_unit

$vendors_for_dropdown_edit = [];
$products_for_dropdown_edit = []; // Will store {id, name, default_price}

if ($conn) {
    // Fetch Bill Details, ensuring it belongs to the current user
    $sql_bill = "SELECT id, bill_date, vendor_id, notes, is_paid
                 FROM bills
                 WHERE id = ? AND user_id = ?";
    $stmt_bill = $conn->prepare($sql_bill);
    if($stmt_bill){
        $stmt_bill->bind_param("ii", $bill_id_to_edit, $current_user_id);
        if($stmt_bill->execute()){
            $result_bill = $stmt_bill->get_result();
            if ($result_bill->num_rows === 1) {
                $bill_data = $result_bill->fetch_assoc();
                // $page_title is already set, but you could update it here if needed
                // $page_title = "แก้ไขบิล #" . htmlspecialchars($bill_data['id']);
                $result_bill->free();

                // Fetch items for this bill (these should have product_id)
                $sql_items_db = "SELECT id, product_id, quantity, price_per_unit
                                 FROM expense_items
                                 WHERE bill_id = ? ORDER BY id ASC";
                $stmt_items_db = $conn->prepare($sql_items_db);
                if($stmt_items_db){
                    $stmt_items_db->bind_param("i", $bill_id_to_edit);
                    if($stmt_items_db->execute()){
                        $result_items_db = $stmt_items_db->get_result();
                        while ($row_item = $result_items_db->fetch_assoc()) {
                            $items_in_bill_from_db[] = [
                                'db_item_id' => $row_item['id'], // ID of the expense_item row itself
                                'product_id' => $row_item['product_id'],
                                'quantity' => $row_item['quantity'],
                                'price' => $row_item['price_per_unit'] // price_per_unit from expense_items
                            ];
                        }
                        $result_items_db->free();
                    } else {
                        error_log("Edit Bill Form - Error executing items query: " . $stmt_items_db->error);
                    }
                    $stmt_items_db->close();
                } else {
                     error_log("Edit Bill Form - Error preparing items in bill query: " . $conn->error);
                }
            } else {
                $_SESSION['message'] = "ไม่พบบิล ID: " . $bill_id_to_edit . " หรือคุณไม่มีสิทธิ์แก้ไขบิลนี้";
                $_SESSION['message_type'] = "error";
                if($stmt_bill) $stmt_bill->close();
                header("Location: index.php");
                exit();
            }
        } else {
            error_log("Edit Bill Form - Error executing bill query: " . $stmt_bill->error);
            $_SESSION['message'] = "เกิดข้อผิดพลาดในการดึงข้อมูลบิล";
            $_SESSION['message_type'] = "error";
            if($stmt_bill) $stmt_bill->close();
            header("Location: index.php");
            exit();
        }
         if($stmt_bill) $stmt_bill->close();
    } else {
        error_log("Edit Bill Form - Error preparing bill query: " . $conn->error);
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการดึงข้อมูลบิล (prepare failed)";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }

    // Fetch vendors FOR THIS USER from the 'vendors' table
    $sql_vendors_edit = "SELECT id, name FROM vendors WHERE user_id = ? ORDER BY name ASC";
    $stmt_vendors_edit = $conn->prepare($sql_vendors_edit);
    if($stmt_vendors_edit) {
        $stmt_vendors_edit->bind_param("i", $current_user_id);
        if($stmt_vendors_edit->execute()){
            $result_vendors_edit = $stmt_vendors_edit->get_result();
            while($row_v_edit = $result_vendors_edit->fetch_assoc()){
                $vendors_for_dropdown_edit[] = $row_v_edit;
            }
            $result_vendors_edit->free();
        } else {
            error_log("Edit Bill Form - Error executing vendors for dropdown query: " . $stmt_vendors_edit->error);
        }
        $stmt_vendors_edit->close();
    } else {
        error_log("Edit Bill Form - Error preparing vendors for dropdown query: " . $conn->error);
    }

    // Fetch products FOR THIS USER from the 'products' table
    $sql_products_edit = "SELECT id, name, default_price FROM products WHERE user_id = ? ORDER BY name ASC";
    $stmt_products_edit = $conn->prepare($sql_products_edit);
    if ($stmt_products_edit) {
        $stmt_products_edit->bind_param("i", $current_user_id);
        if ($stmt_products_edit->execute()) {
            $result_products_edit = $stmt_products_edit->get_result();
            while ($row_p_edit = $result_products_edit->fetch_assoc()) {
                $products_for_dropdown_edit[] = $row_p_edit;
            }
            $result_products_edit->free();
        } else {
            error_log("Edit Bill Form - Error executing products for dropdown query: " . $stmt_products_edit->error);
        }
        $stmt_products_edit->close();
    } else {
        error_log("Edit Bill Form - Error preparing products for dropdown query: " . $conn->error);
    }
} else {
    $_SESSION['message'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Retrieve old form data if validation failed during an update attempt for THIS bill_id
$old_data_session_key_edit = 'old_form_data_edit'; // Unique session key for edit form
$old_data_edit = $_SESSION[$old_data_session_key_edit][$bill_id_to_edit] ?? [];
if (isset($_SESSION[$old_data_session_key_edit][$bill_id_to_edit])) {
    unset($_SESSION[$old_data_session_key_edit][$bill_id_to_edit]);
    if (empty($_SESSION[$old_data_session_key_edit])) { 
        unset($_SESSION[$old_data_session_key_edit]);
    }
}

// Determine values for form fields (prioritize old_data_edit, then bill_data from DB)
$bill_date_val = $old_data_edit['bill_date'] ?? ($bill_data['bill_date'] ?? date('Y-m-d'));
$selected_vendor_id_val = $old_data_edit['vendor_id'] ?? ($bill_data['vendor_id'] ?? '');
$notes_val = $old_data_edit['notes'] ?? ($bill_data['notes'] ?? '');
$is_paid_val = isset($old_data_edit['is_paid']) ? (int)$old_data_edit['is_paid'] : (isset($bill_data['is_paid']) ? (int)$bill_data['is_paid'] : 0);

$items_val_for_form = $old_data_edit['items'] ?? $items_in_bill_from_db;
if (empty($items_val_for_form)) { // Ensure there's at least one item row structure for the form
    $items_val_for_form = [['db_item_id' => null, 'product_id' => '', 'quantity' => 1, 'price' => '']];
}
?>

<h2 class="h4 mb-4"><?php echo htmlspecialchars($page_title); ?> (บิล ID: <?php echo htmlspecialchars($bill_id_to_edit); ?>)</h2>
<form action="update_bill_process.php" method="POST" id="editBillForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="bill_id" value="<?php echo htmlspecialchars($bill_id_to_edit); ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            ข้อมูลทั่วไปของบิล
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="bill_date_edit" class="form-label">วันที่ในบิล <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" id="bill_date_edit" name="bill_date" value="<?php echo htmlspecialchars($bill_date_val); ?>" required>
                </div>
                <div class="col-md-5">
                    <label for="vendor_id_edit" class="form-label">ชื่อร้านค้า/ผู้ให้บริการ <?php if (empty($vendors_for_dropdown_edit)) echo "<span class='text-danger'>*</span>"; ?></label>
                    <div class="input-group input-group-sm">
                        <select class="form-select form-select-sm" id="vendor_id_edit" name="vendor_id" <?php if (empty($vendors_for_dropdown_edit)) echo "required"; ?>>
                            <option value="">-- กรุณาเลือกผู้ขาย --</option>
                            <?php if (!empty($vendors_for_dropdown_edit)): ?>
                                <?php foreach ($vendors_for_dropdown_edit as $vendor_item_edit): ?>
                                    <option value="<?php echo htmlspecialchars($vendor_item_edit['id']); ?>" <?php echo ($selected_vendor_id_val == $vendor_item_edit['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vendor_item_edit['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>ไม่มีร้านค้าในระบบ กรุณาเพิ่มก่อน</option>
                            <?php endif; ?>
                        </select>
                        <a href="add_vendor_form.php?source=edit_bill&bill_id=<?php echo $bill_id_to_edit; ?>" class="btn btn-outline-secondary" type="button" title="เพิ่มร้านค้าใหม่" target="_blank">➕</a>
                    </div>
                     <small class="form-text text-muted">
                        เลือกจากรายการ หากไม่พบร้านค้า คลิก ➕ เพื่อเพิ่ม
                        <?php if (empty($vendors_for_dropdown_edit)): ?>
                            <strong class="text-danger"> (ต้องเพิ่มร้านค้าก่อน)</strong>
                        <?php endif; ?>
                    </small>
                </div>
                 <div class="col-md-3">
                    <label for="is_paid_edit" class="form-label">สถานะการจ่าย</label>
                    <select name="is_paid" id="is_paid_edit" class="form-select form-select-sm">
                        <option value="0" <?php echo ($is_paid_val == 0) ? 'selected' : ''; ?>>ยังไม่จ่าย</option>
                        <option value="1" <?php echo ($is_paid_val == 1) ? 'selected' : ''; ?>>จ่ายแล้ว</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="notes_edit" class="form-label">หมายเหตุ (ถ้ามี)</label>
                    <textarea class="form-control form-control-sm" id="notes_edit" name="notes" rows="2"><?php echo htmlspecialchars($notes_val); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>รายการสินค้า/บริการ <span class="text-danger">*</span></span>
            <div>
                <a href="add_product_form.php?source=edit_bill&bill_id=<?php echo $bill_id_to_edit; ?>" class="btn btn-sm btn-info me-2" title="เพิ่มสินค้าใหม่เข้าคลัง" target="_blank">➕ เพิ่มสินค้าเข้าคลัง</a>
                <button type="button" class="btn btn-sm btn-success" id="addItemBtnEdit">➕ เพิ่มรายการในบิล</button>
            </div>
        </div>
        <div class="card-body">
            <div id="items_container_edit" class="mb-0">
                <?php foreach ($items_val_for_form as $key => $item_data_form): ?>
                <div class="row g-2 mb-2 item-row-edit align-items-center" data-row-index="<?php echo $key; ?>">
                    <input type="hidden" name="items[<?php echo $key; ?>][db_item_id]" value="<?php echo htmlspecialchars($item_data_form['db_item_id'] ?? ''); ?>">
                    <div class="col-md-5">
                        <label for="item_product_id_edit_<?php echo $key; ?>" class="form-label visually-hidden">ชื่อสินค้า/บริการ</label>
                        <select class="form-select form-select-sm item-product-id-edit" id="item_product_id_edit_<?php echo $key; ?>" name="items[<?php echo $key; ?>][product_id]" required>
                            <option value="">-- เลือกสินค้า --</option>
                            <?php if (!empty($products_for_dropdown_edit)): ?>
                                <?php foreach ($products_for_dropdown_edit as $prod_item_edit): ?>
                                    <option value="<?php echo htmlspecialchars($prod_item_edit['id']); ?>" 
                                            data-default-price="<?php echo htmlspecialchars($prod_item_edit['default_price'] ?? ''); ?>"
                                        <?php echo (isset($item_data_form['product_id']) && $item_data_form['product_id'] == $prod_item_edit['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prod_item_edit['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>ไม่มีสินค้าในคลัง</option>
                            <?php endif; ?>
                        </select>
                         <small class="form-text text-muted item-name-helper">
                            เลือกสินค้าจากคลัง
                            <?php if (empty($products_for_dropdown_edit)): ?>
                                <strong class="text-danger">(ต้องเพิ่มสินค้าเข้าคลังก่อน)</strong>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="col-md-2 col-4">
                        <label for="item_quantity_edit_<?php echo $key; ?>" class="form-label visually-hidden">จำนวน</label>
                        <input type="number" class="form-control form-control-sm item-quantity-edit" id="item_quantity_edit_<?php echo $key; ?>" name="items[<?php echo $key; ?>][quantity]" placeholder="จำนวน" value="<?php echo htmlspecialchars($item_data_form['quantity'] ?? '1'); ?>" min="0.001" step="any" required>
                    </div>
                    <div class="col-md-3 col-5">
                        <label for="item_price_edit_<?php echo $key; ?>" class="form-label visually-hidden">ราคาต่อหน่วย</label>
                        <input type="number" class="form-control form-control-sm item-price-edit" id="item_price_edit_<?php echo $key; ?>" name="items[<?php echo $key; ?>][price]" placeholder="ราคาต่อหน่วย" value="<?php echo htmlspecialchars($item_data_form['price'] ?? ''); ?>" min="0" step="any" required>
                    </div>
                    <div class="col-md-1 col-2 text-end">
                        <button type="button" class="btn btn-sm btn-danger removeItemBtnEdit" <?php echo (count($items_val_for_form) <=1 && $key==0) ? 'disabled' : ''; ?>>✖</button>
                    </div>
                    <div class="col-md-1 col-1 text-end">
                        <span class="item-subtotal-display-edit small text-muted" id="item_subtotal_display_edit_<?php echo $key; ?>">0.00</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="row mt-3">
                <div class="col text-end">
                    <h4 class="mb-0">ยอดรวมบิล: <span id="grandTotalDisplayEdit" class="text-primary">0.00</span> บาท</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 text-center">
        <button type="submit" class="btn btn-primary btn-lg px-5" <?php if (empty($vendors_for_dropdown_edit) || empty($products_for_dropdown_edit)) echo "disabled"; ?>>💾 อัปเดตข้อมูลบิล</button>
        <a href="view_bill_details.php?bill_id=<?php echo $bill_id_to_edit; ?>" class="btn btn-outline-secondary ms-2">ยกเลิก</a>
        <?php if (empty($vendors_for_dropdown_edit) || empty($products_for_dropdown_edit)): ?>
            <p class="text-danger mt-2 small">กรุณาเพิ่มข้อมูลร้านค้าและสินค้าในระบบก่อนจึงจะสามารถบันทึกบิลได้</p>
        <?php endif; ?>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsContainerEdit = document.getElementById('items_container_edit');
    const addItemBtnEdit = document.getElementById('addItemBtnEdit');
    const grandTotalDisplayEdit = document.getElementById('grandTotalDisplayEdit');
    
    const productsForDropdownEditJS = <?php echo json_encode($products_for_dropdown_edit); ?>;

    let itemIndexEdit = <?php echo count($items_val_for_form); ?>;

    function updateGrandTotalEdit() {
        let total = 0;
        if (itemsContainerEdit) {
            itemsContainerEdit.querySelectorAll('.item-row-edit').forEach(function(row) {
                const quantityInput = row.querySelector('.item-quantity-edit');
                const priceInput = row.querySelector('.item-price-edit');
                const subtotalDisplay = row.querySelector('.item-subtotal-display-edit');

                const quantity = parseFloat(quantityInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                const subtotal = quantity * price;
                total += subtotal;

                if(subtotalDisplay) {
                    subtotalDisplay.textContent = subtotal.toFixed(2);
                }
            });
        }
        if(grandTotalDisplayEdit) grandTotalDisplayEdit.textContent = total.toFixed(2);
    }

    function handleItemRowChangeEdit(row) {
        const itemProductIdSelect = row.querySelector('.item-product-id-edit');
        const priceInput = row.querySelector('.item-price-edit');
        const quantityInput = row.querySelector('.item-quantity-edit');

        if (itemProductIdSelect) {
            itemProductIdSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const defaultPrice = selectedOption.dataset.defaultPrice;
                if (defaultPrice && defaultPrice.trim() !== '' && (priceInput.value.trim() === '' || parseFloat(priceInput.value) === 0)) {
                    priceInput.value = parseFloat(defaultPrice).toFixed(2);
                }
                updateGrandTotalEdit();
            });
        }
        if(quantityInput) {
            quantityInput.addEventListener('input', updateGrandTotalEdit);
        }
        if(priceInput) {
            priceInput.addEventListener('input', updateGrandTotalEdit);
        }
    }

    function addRowEdit(itemData = { product_id: '', quantity: 1, price: '' }) {
        if (!itemsContainerEdit) return;
        const currentRowIndex = itemIndexEdit;
        const newRow = document.createElement('div');
        newRow.className = 'row g-2 mb-2 item-row-edit align-items-center';
        newRow.dataset.rowIndex = currentRowIndex;

        let itemSelectHTML = `<select class="form-select form-select-sm item-product-id-edit" name="items[${currentRowIndex}][product_id]" id="item_product_id_edit_${currentRowIndex}" required>`;
        itemSelectHTML += `<option value="">-- เลือกสินค้า --</option>`;
        productsForDropdownEditJS.forEach(function(product) {
            const defaultPriceAttr = product.default_price ? `data-default-price="${escapeHTMLEdit(product.default_price.toString())}"` : '';
            itemSelectHTML += `<option value="${escapeHTMLEdit(product.id.toString())}" ${defaultPriceAttr}>${escapeHTMLEdit(product.name)}</option>`;
        });
        itemSelectHTML += `</select>`;
        
        newRow.innerHTML = `
            <input type="hidden" name="items[${currentRowIndex}][db_item_id]" value="">
            <div class="col-md-5">
                <label for="item_product_id_edit_${currentRowIndex}" class="form-label visually-hidden">ชื่อสินค้า/บริการ</label>
                ${itemSelectHTML}
                <small class="form-text text-muted item-name-helper">เลือกสินค้า (หากไม่พบให้ "เพิ่มสินค้าเข้าคลัง" ก่อน)</small>
            </div>
            <div class="col-md-2 col-4">
                <label for="item_quantity_edit_${currentRowIndex}" class="form-label visually-hidden">จำนวน</label>
                <input type="number" class="form-control form-control-sm item-quantity-edit" id="item_quantity_edit_${currentRowIndex}" name="items[${currentRowIndex}][quantity]" placeholder="จำนวน" value="${escapeHTMLEdit(itemData.quantity.toString())}" min="0.001" step="any" required>
            </div>
            <div class="col-md-3 col-5">
                <label for="item_price_edit_${currentRowIndex}" class="form-label visually-hidden">ราคาต่อหน่วย</label>
                <input type="number" class="form-control form-control-sm item-price-edit" id="item_price_edit_${currentRowIndex}" name="items[${currentRowIndex}][price]" placeholder="ราคาต่อหน่วย" value="${escapeHTMLEdit(itemData.price.toString())}" min="0" step="any" required>
            </div>
            <div class="col-md-1 col-2 text-end">
                <button type="button" class="btn btn-sm btn-danger removeItemBtnEdit">✖</button>
            </div>
            <div class="col-md-1 col-1 text-end">
                <span class="item-subtotal-display-edit small text-muted" id="item_subtotal_display_edit_${currentRowIndex}">0.00</span>
            </div>
        `;
        itemsContainerEdit.appendChild(newRow);

        const newProductSelect = newRow.querySelector('.item-product-id-edit');
        if (itemData.product_id && newProductSelect) {
            newProductSelect.value = itemData.product_id;
             const event = new Event('change', { bubbles: true });
             newProductSelect.dispatchEvent(event);
        }
        
        handleItemRowChangeEdit(newRow);
        updateRemoveButtonStatesEdit();
        itemIndexEdit++;
        updateGrandTotalEdit();
    }
    
    function escapeHTMLEdit(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function (match) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match];
        });
    }

    function updateRemoveButtonStatesEdit() {
        if (!itemsContainerEdit) return;
        const allRows = itemsContainerEdit.querySelectorAll('.item-row-edit');
        allRows.forEach(function(row, idx) {
            const removeBtn = row.querySelector('.removeItemBtnEdit');
            if (removeBtn) {
                removeBtn.disabled = (allRows.length <= 1);
            }
            const newIndex = idx;
            row.dataset.rowIndex = newIndex;

            row.querySelectorAll('input, select').forEach(input => {
                let nameAttr = input.getAttribute('name');
                if (nameAttr && nameAttr.startsWith('items[')) {
                    input.setAttribute('name', nameAttr.replace(/items\[\d+\]/, `items[${newIndex}]`));
                }
                let idAttr = input.getAttribute('id');
                if (idAttr) {
                    input.setAttribute('id', idAttr.replace(/_\d+$/, `_${newIndex}`));
                }
            });
            
            const subtotalDisplay = row.querySelector('.item-subtotal-display-edit');
            if(subtotalDisplay) {
                subtotalDisplay.id = `item_subtotal_display_edit_${newIndex}`;
            }
            row.querySelectorAll('label.visually-hidden').forEach(label => {
                let forAttr = label.getAttribute('for');
                if(forAttr){
                    label.setAttribute('for', forAttr.replace(/_\d+$/, `_${newIndex}`));
                }
            });
        });
        itemIndexEdit = allRows.length;
    }

    if (addItemBtnEdit) {
        addItemBtnEdit.addEventListener('click', function() { addRowEdit(); });
    }
    if (itemsContainerEdit) {
        itemsContainerEdit.addEventListener('click', function(event) {
            if (event.target.classList.contains('removeItemBtnEdit')) {
                const rowToRemove = event.target.closest('.item-row-edit');
                if (itemsContainerEdit.querySelectorAll('.item-row-edit').length > 1) {
                    rowToRemove.remove();
                    updateRemoveButtonStatesEdit(); 
                    updateGrandTotalEdit();
                }
            }
        });
    }

    document.querySelectorAll('.item-row-edit').forEach(function(row, index) {
        row.dataset.rowIndex = index; 
        handleItemRowChangeEdit(row);
        const productSelect = row.querySelector('.item-product-id-edit');
        const priceInput = row.querySelector('.item-price-edit');
        if (productSelect && productSelect.value && productSelect.value !== '') {
             // For existing items, if price is already set, don't override with default
            if (priceInput.value.trim() === '' || parseFloat(priceInput.value) === 0) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                if (selectedOption) { // Check if an option is actually selected
                    const defaultPrice = selectedOption.dataset.defaultPrice;
                    if (defaultPrice && defaultPrice.trim() !== '') {
                        priceInput.value = parseFloat(defaultPrice).toFixed(2);
                    }
                }
            }
        }
    });
    updateRemoveButtonStatesEdit(); 
    updateGrandTotalEdit(); 
});
</script>

<?php
require_once 'footer.php';
?>
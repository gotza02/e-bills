<?php
$page_title = "เพิ่มบิลใหม่";
require_once 'header.php'; // Handles session, auth, CSRF, db_connect

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message_login'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    $_SESSION['message_type_login'] = "warning";
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

$vendors_for_dropdown = [];
$products_for_dropdown = []; // Will store {id, name, default_price}

if ($conn) {
    // Fetch vendors for this user from the 'vendors' table
    $sql_vendors = "SELECT id, name FROM vendors WHERE user_id = ? ORDER BY name ASC";
    $stmt_vendors = $conn->prepare($sql_vendors);
    if ($stmt_vendors) {
        $stmt_vendors->bind_param("i", $current_user_id);
        if ($stmt_vendors->execute()) {
            $result_vendors = $stmt_vendors->get_result();
            while ($row = $result_vendors->fetch_assoc()) {
                $vendors_for_dropdown[] = $row;
            }
            // $result_vendors->free(); // Not strictly necessary for buffered queries
        } else {
            error_log("Add Bill Form - Error executing vendors query: " . $stmt_vendors->error);
        }
        $stmt_vendors->close();
    } else {
        error_log("Add Bill Form - Error preparing vendors query: " . $conn->error);
    }

    // Fetch products for this user from the 'products' table
    $sql_products = "SELECT id, name, default_price FROM products WHERE user_id = ? ORDER BY name ASC";
    $stmt_products = $conn->prepare($sql_products);
    if ($stmt_products) {
        $stmt_products->bind_param("i", $current_user_id);
        if ($stmt_products->execute()) {
            $result_products = $stmt_products->get_result();
            while ($row = $result_products->fetch_assoc()) {
                $products_for_dropdown[] = $row;
            }
            // $result_products->free();
        } else {
            error_log("Add Bill Form - Error executing products query: " . $stmt_products->error);
        }
        $stmt_products->close();
    } else {
        error_log("Add Bill Form - Error preparing products query: " . $conn->error);
    }
}

$old_data = $_SESSION['old_form_data'] ?? [];
if (isset($_SESSION['old_form_data'])) {
    unset($_SESSION['old_form_data']);
}

$bill_date_val = $old_data['bill_date'] ?? date('Y-m-d');
$selected_vendor_id_val = $old_data['vendor_id'] ?? ''; // This should be vendor_id
$notes_val = $old_data['notes'] ?? '';
$items_val_for_form = $old_data['items'] ?? [['product_id' => '', 'quantity' => 1, 'price' => '']];
if (empty($items_val_for_form)) {
    $items_val_for_form = [['product_id' => '', 'quantity' => 1, 'price' => '']];
}
?>

<h2 class="h4 mb-4">เพิ่มข้อมูลบิลใหม่</h2>
<form action="add_bill_process.php" method="POST" id="addBillForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            ข้อมูลทั่วไปของบิล
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="bill_date" class="form-label">วันที่ในบิล <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" id="bill_date" name="bill_date" value="<?php echo htmlspecialchars($bill_date_val); ?>" required>
                </div>
                <div class="col-md-8">
                    <label for="vendor_id" class="form-label">ชื่อร้านค้า/ผู้ให้บริการ <?php if (empty($vendors_for_dropdown)) echo "<span class='text-danger'>*</span>"; ?></label>
                    <div class="input-group input-group-sm">
                        <select class="form-select form-select-sm" id="vendor_id" name="vendor_id" <?php if (empty($vendors_for_dropdown)) echo "required"; ?>>
                            <option value="">-- กรุณาเลือกผู้ขาย --</option>
                            <?php if (!empty($vendors_for_dropdown)): ?>
                                <?php foreach ($vendors_for_dropdown as $vendor_item): ?>
                                    <option value="<?php echo htmlspecialchars($vendor_item['id']); ?>" <?php echo ($selected_vendor_id_val == $vendor_item['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vendor_item['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>ไม่มีร้านค้าในระบบ กรุณาเพิ่มผ่านหน้า "จัดการข้อมูล"</option>
                            <?php endif; ?>
                        </select>
                        <a href="add_vendor_form.php?source=add_bill" class="btn btn-outline-secondary" type="button" title="เพิ่มร้านค้าใหม่" target="_blank">➕</a>
                    </div>
                    <small class="form-text text-muted">
                        เลือกจากรายการ หากไม่พบร้านค้า คลิก ➕ เพื่อเพิ่ม (หน้าต่างใหม่จะเปิด)
                        <?php if (empty($vendors_for_dropdown)): ?>
                            <strong class="text-danger">คุณต้องเพิ่มร้านค้าอย่างน้อย 1 แห่งก่อน จึงจะบันทึกบิลได้ (ผ่านหน้า "จัดการข้อมูล > จัดการร้านค้า")</strong>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">หมายเหตุ (ถ้ามี)</label>
                    <textarea class="form-control form-control-sm" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($notes_val); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>รายการสินค้า/บริการ <span class="text-danger">*</span></span>
            <div>
                <a href="add_product_form.php?source=add_bill" class="btn btn-sm btn-info me-2" title="เพิ่มสินค้าใหม่เข้าคลัง" target="_blank">➕ เพิ่มสินค้าเข้าคลัง</a>
                <button type="button" class="btn btn-sm btn-success" id="addItemBtn">➕ เพิ่มรายการในบิล</button>
            </div>
        </div>
        <div class="card-body">
            <div id="items_container" class="mb-0">
                <?php foreach ($items_val_for_form as $key => $item_data): ?>
                <div class="row g-2 mb-2 item-row align-items-center" data-row-index="<?php echo $key; ?>">
                    <div class="col-md-5">
                        <label for="item_product_id_<?php echo $key; ?>" class="form-label visually-hidden">ชื่อสินค้า/บริการ</label>
                        <select class="form-select form-select-sm item-product-id" id="item_product_id_<?php echo $key; ?>" name="items[<?php echo $key; ?>][product_id]" required>
                            <option value="">-- เลือกสินค้า --</option>
                            <?php if (!empty($products_for_dropdown)): ?>
                                <?php foreach ($products_for_dropdown as $prod_item): ?>
                                    <option value="<?php echo htmlspecialchars($prod_item['id']); ?>"
                                            data-default-price="<?php echo htmlspecialchars($prod_item['default_price'] ?? ''); ?>"
                                        <?php echo (isset($item_data['product_id']) && $item_data['product_id'] == $prod_item['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prod_item['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                 <option value="" disabled>ไม่มีสินค้าในคลัง กรุณาเพิ่มผ่านหน้า "จัดการข้อมูล"</option>
                            <?php endif; ?>
                        </select>
                        <small class="form-text text-muted item-name-helper">
                            เลือกสินค้าจากคลัง
                            <?php if (empty($products_for_dropdown)): ?>
                                <strong class="text-danger">คุณต้องเพิ่มสินค้าเข้าคลังอย่างน้อย 1 รายการก่อน (ผ่านหน้า "จัดการข้อมูล > จัดการสินค้า")</strong>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="col-md-2 col-4">
                        <label for="item_quantity_<?php echo $key; ?>" class="form-label visually-hidden">จำนวน</label>
                        <input type="number" class="form-control form-control-sm item-quantity" id="item_quantity_<?php echo $key; ?>" name="items[<?php echo $key; ?>][quantity]" placeholder="จำนวน" value="<?php echo htmlspecialchars($item_data['quantity'] ?? '1'); ?>" min="0.001" step="any" required>
                    </div>
                    <div class="col-md-3 col-5">
                        <label for="item_price_<?php echo $key; ?>" class="form-label visually-hidden">ราคาต่อหน่วย</label>
                        <input type="number" class="form-control form-control-sm item-price" id="item_price_<?php echo $key; ?>" name="items[<?php echo $key; ?>][price]" placeholder="ราคาต่อหน่วย" value="<?php echo htmlspecialchars($item_data['price'] ?? ''); ?>" min="0" step="any" required>
                    </div>
                    <div class="col-md-1 col-2 text-end">
                        <button type="button" class="btn btn-sm btn-danger removeItemBtn" <?php echo (count($items_val_for_form) <=1 && $key==0) ? 'disabled' : ''; ?>>✖</button>
                    </div>
                    <div class="col-md-1 col-1 text-end">
                        <span class="item-subtotal-display small text-muted" id="item_subtotal_display_<?php echo $key; ?>">0.00</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row mt-3">
                <div class="col text-end">
                    <h4 class="mb-0">ยอดรวมบิล: <span id="grandTotalDisplay" class="text-primary">0.00</span> บาท</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 text-center">
        <button type="submit" class="btn btn-primary btn-lg px-5" <?php if (empty($vendors_for_dropdown) || empty($products_for_dropdown)) echo "disabled"; ?>>💾 บันทึกข้อมูลบิล</button>
        <a href="index.php" class="btn btn-outline-secondary ms-2">ยกเลิก</a>
         <?php if (empty($vendors_for_dropdown) || empty($products_for_dropdown)): ?>
            <p class="text-danger mt-2 small">กรุณาเพิ่มข้อมูลร้านค้าและสินค้าในระบบผ่านหน้า "จัดการข้อมูล" ก่อนจึงจะสามารถบันทึกบิลได้</p>
        <?php endif; ?>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsContainer = document.getElementById('items_container');
    const addItemBtn = document.getElementById('addItemBtn');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');

    const productsForDropdownJS = <?php echo json_encode($products_for_dropdown); ?>;

    let itemIndex = <?php echo count($items_val_for_form); ?>;

    function updateGrandTotal() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(function(row) {
            const quantityInput = row.querySelector('.item-quantity');
            const priceInput = row.querySelector('.item-price');
            const subtotalDisplay = row.querySelector('.item-subtotal-display');

            const quantity = parseFloat(quantityInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const subtotal = quantity * price;
            total += subtotal;

            if(subtotalDisplay) {
                subtotalDisplay.textContent = subtotal.toFixed(2);
            }
        });
        if (grandTotalDisplay) {
            grandTotalDisplay.textContent = total.toFixed(2);
        }
    }

    function handleItemRowChange(row) {
        const itemProductIdSelect = row.querySelector('.item-product-id');
        const priceInput = row.querySelector('.item-price');
        const quantityInput = row.querySelector('.item-quantity');

        if (itemProductIdSelect) {
            itemProductIdSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption) { // Ensure an option is selected
                    const defaultPrice = selectedOption.dataset.defaultPrice;
                    // Only set default price if price input is empty or zero
                    if (priceInput && defaultPrice && defaultPrice.trim() !== '' && (priceInput.value.trim() === '' || parseFloat(priceInput.value) === 0)) {
                        priceInput.value = parseFloat(defaultPrice).toFixed(2);
                    }
                }
                updateGrandTotal();
            });
        }
        if(quantityInput) {
            quantityInput.addEventListener('input', updateGrandTotal);
        }
        if(priceInput) {
            priceInput.addEventListener('input', updateGrandTotal);
        }
    }

    function addRow(itemData = { product_id: '', quantity: 1, price: '' }) {
        const currentRowIndex = itemIndex;
        const newRow = document.createElement('div');
        newRow.className = 'row g-2 mb-2 item-row align-items-center';
        newRow.dataset.rowIndex = currentRowIndex;

        let itemSelectHTML = `<select class="form-select form-select-sm item-product-id" name="items[${currentRowIndex}][product_id]" id="item_product_id_${currentRowIndex}" required>`;
        itemSelectHTML += `<option value="">-- เลือกสินค้า --</option>`;
        if (productsForDropdownJS && productsForDropdownJS.length > 0) {
            productsForDropdownJS.forEach(function(product) {
                const defaultPriceAttr = product.default_price ? `data-default-price="${escapeHTML(product.default_price.toString())}"` : '';
                itemSelectHTML += `<option value="${escapeHTML(product.id.toString())}" ${defaultPriceAttr}>${escapeHTML(product.name)}</option>`;
            });
        } else {
             itemSelectHTML += `<option value="" disabled>ไม่มีสินค้าในคลัง</option>`;
        }
        itemSelectHTML += `</select>`;

        newRow.innerHTML = `
            <div class="col-md-5">
                <label for="item_product_id_${currentRowIndex}" class="form-label visually-hidden">ชื่อสินค้า/บริการ</label>
                ${itemSelectHTML}
                <small class="form-text text-muted item-name-helper">เลือกสินค้า (หากไม่พบให้ "เพิ่มสินค้าเข้าคลัง" ก่อน)</small>
            </div>
            <div class="col-md-2 col-4">
                <label for="item_quantity_${currentRowIndex}" class="form-label visually-hidden">จำนวน</label>
                <input type="number" class="form-control form-control-sm item-quantity" id="item_quantity_${currentRowIndex}" name="items[${currentRowIndex}][quantity]" placeholder="จำนวน" value="${escapeHTML(itemData.quantity.toString())}" min="0.001" step="any" required>
            </div>
            <div class="col-md-3 col-5">
                <label for="item_price_${currentRowIndex}" class="form-label visually-hidden">ราคาต่อหน่วย</label>
                <input type="number" class="form-control form-control-sm item-price" id="item_price_${currentRowIndex}" name="items[${currentRowIndex}][price]" placeholder="ราคาต่อหน่วย" value="${escapeHTML(itemData.price.toString())}" min="0" step="any" required>
            </div>
            <div class="col-md-1 col-2 text-end">
                <button type="button" class="btn btn-sm btn-danger removeItemBtn">✖</button>
            </div>
            <div class="col-md-1 col-1 text-end">
                <span class="item-subtotal-display small text-muted" id="item_subtotal_display_${currentRowIndex}">0.00</span>
            </div>
        `;
        if(itemsContainer) itemsContainer.appendChild(newRow);

        const newProductSelect = newRow.querySelector('.item-product-id');
        if (itemData.product_id && newProductSelect) {
            newProductSelect.value = itemData.product_id;
             const event = new Event('change', { bubbles: true });
             newProductSelect.dispatchEvent(event);
        }

        handleItemRowChange(newRow);
        updateRemoveButtonStates();
        itemIndex++;
        updateGrandTotal();
    }

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function (match) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match];
        });
    }

    function updateRemoveButtonStates() {
        if (!itemsContainer) return;
        const allRows = itemsContainer.querySelectorAll('.item-row');
        allRows.forEach(function(row, idx) {
            const removeBtn = row.querySelector('.removeItemBtn');
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
                if (idAttr && idAttr.match(/_\d+$/)) { // Check if ID ends with _<number>
                    input.setAttribute('id', idAttr.replace(/_\d+$/, `_${newIndex}`));
                }
            });

            const subtotalDisplay = row.querySelector('.item-subtotal-display');
            if(subtotalDisplay && subtotalDisplay.id.match(/_\d+$/)) {
                subtotalDisplay.id = `item_subtotal_display_${newIndex}`;
            }
             row.querySelectorAll('label.visually-hidden').forEach(label => {
                let forAttr = label.getAttribute('for');
                if(forAttr && forAttr.match(/_\d+$/)){
                    label.setAttribute('for', forAttr.replace(/_\d+$/, `_${newIndex}`));
                }
            });
        });
        itemIndex = allRows.length;
    }

    if (addItemBtn) {
        addItemBtn.addEventListener('click', function() { addRow(); });
    }
    if (itemsContainer) {
        itemsContainer.addEventListener('click', function(event) {
            if (event.target.classList.contains('removeItemBtn')) {
                const rowToRemove = event.target.closest('.item-row');
                if (itemsContainer.querySelectorAll('.item-row').length > 1) {
                    rowToRemove.remove();
                    updateRemoveButtonStates();
                    updateGrandTotal();
                }
            }
        });
    }

    document.querySelectorAll('.item-row').forEach(function(row, index) {
        row.dataset.rowIndex = index;
        handleItemRowChange(row);
        const productSelect = row.querySelector('.item-product-id');
        const priceInput = row.querySelector('.item-price');
        if (productSelect && productSelect.value && productSelect.value !== '') {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            if (selectedOption) { // Check if an option is actually selected
                const defaultPrice = selectedOption.dataset.defaultPrice;
                if (priceInput && defaultPrice && defaultPrice.trim() !== '' && (priceInput.value.trim() === '' || parseFloat(priceInput.value) === 0)) {
                     priceInput.value = parseFloat(defaultPrice).toFixed(2);
                }
            }
        }
    });
    updateRemoveButtonStates();
    updateGrandTotal();
});
</script>

<?php
require_once 'footer.php';
?>
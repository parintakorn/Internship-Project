<?php
include 'config.php';

// ดึงข้อมูลวัตถุดิบทั้งหมด
$query = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.CATEGORY, p.PRICE_PER_GRAM
          FROM PRODUCTS p
          ORDER BY p.CATEGORY, p.PRODUCT_NAME";
$stid = oci_parse($conn, $query);
oci_execute($stid);

$products = [];
while ($row = oci_fetch_assoc($stid)) {
    $products[] = $row;
}
oci_free_statement($stid);

// จัดกลุ่มตามหมวดหมู่
$categorized = [];
foreach ($products as $product) {
    $category = $product['CATEGORY'] ?: 'อื่นๆ';
    if (!isset($categorized[$category])) {
        $categorized[$category] = [];
    }
    $categorized[$category][] = $product;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าราคาวัตถุดิบ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
        }

        .top-bar {
            width: 100%;
            background: rgba(255,255,255,0.95);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 20;
            backdrop-filter: blur(10px);
        }

        .back-btn {
            font-size: 24px;
            margin-right: 15px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            background: #667eea;
            color: white;
            transition: all 0.3s;
            text-decoration: none;
        }

        .back-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .container {
            margin-top: 90px;
            margin-left: 30px;
            margin-right: 30px;
            padding-bottom: 50px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .category-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        .price-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }

        .price-item:hover {
            border-color: #667eea;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.2);
        }

        .price-item label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .price-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-input-group input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Kanit', sans-serif;
            transition: all 0.3s;
        }

        .price-input-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .price-input-group span {
            font-weight: 600;
            color: #666;
            white-space: nowrap;
        }

        .btn-save {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            font-family: 'Kanit', sans-serif;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }

        .btn-save-all {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.4);
            transition: all 0.3s;
            z-index: 10;
            font-family: 'Kanit', sans-serif;
        }

        .btn-save-all:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            animation: slideIn 0.3s ease;
        }

        .success-message.show {
            display: block;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        @media (max-width: 768px) {
            .container {
                margin-left: 10px;
                margin-right: 10px;
            }
            .price-grid {
                grid-template-columns: 1fr;
            }
            .btn-save-all {
                bottom: 15px;
                right: 15px;
                padding: 12px 24px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="warehouse_report.php" class="back-btn">←</a>
        <h2 style="margin:0">⚙️ ตั้งค่าราคาวัตถุดิบ</h2>
    </div>

    <div class="container">
        <div class="header">
            <h1>⚙️ ตั้งค่าราคาวัตถุดิบ</h1>
            <p>กำหนดราคาต่อกรัมสำหรับแต่ละวัตถุดิบ</p>
        </div>

        <div id="successMessage" class="success-message">
            ✓ บันทึกราคาสำเร็จ!
        </div>

        <div id="errorMessage" class="error-message">
            เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง
        </div>

        <?php foreach ($categorized as $category => $items): ?>
        <div class="category-section">
            <div class="category-header">
                <span>📦 <?= htmlspecialchars($category) ?></span>
                <span><?= count($items) ?> รายการ</span>
            </div>

            <div class="price-grid">
                <?php foreach ($items as $product): ?>
                <div class="price-item">
                    <label><?= htmlspecialchars($product['PRODUCT_NAME']) ?></label>
                    <div class="price-input-group">
                        <input 
                            type="number" 
                            step="0.01" 
                            min="0" 
                            value="<?= $product['PRICE_PER_GRAM'] ?: '' ?>" 
                            placeholder="0.00"
                            data-product-id="<?= $product['PRODUCT_ID'] ?>"
                            class="price-input"
                        >
                        <span>฿/กรัม</span>
                        <button 
                            class="btn-save" 
                            onclick="savePrice(<?= $product['PRODUCT_ID'] ?>, this)"
                        >
                            💾 บันทึก
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <button class="btn-save-all" onclick="saveAllPrices()">
            💾 บันทึกทั้งหมด
        </button>
    </div>

    <script>
        function savePrice(productId, button) {
            const input = document.querySelector(`input[data-product-id="${productId}"]`);
            const price = input.value;

            if (!price || price < 0) {
                showError('กรุณาระบุราคาที่ถูกต้อง');
                return;
            }

            const formData = new FormData();
            formData.append('productId', productId);
            formData.append('price', price);

            button.disabled = true;
            button.textContent = '⏳ กำลังบันทึก...';

            fetch('save_ingredient_price.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('บันทึกราคาสำเร็จ!');
                    button.textContent = '✓ บันทึกแล้ว';
                    setTimeout(() => {
                        button.textContent = '💾 บันทึก';
                        button.disabled = false;
                    }, 2000);
                } else {
                    showError(data.error || 'เกิดข้อผิดพลาด');
                    button.disabled = false;
                    button.textContent = '💾 บันทึก';
                }
            })
            .catch(error => {
                showError('ไม่สามารถเชื่อมต่อได้');
                button.disabled = false;
                button.textContent = '💾 บันทึก';
            });
        }

        function saveAllPrices() {
            const inputs = document.querySelectorAll('.price-input');
            const prices = [];

            inputs.forEach(input => {
                if (input.value && input.value > 0) {
                    prices.push({
                        productId: input.dataset.productId,
                        price: input.value
                    });
                }
            });

            if (prices.length === 0) {
                showError('กรุณาระบุราคาอย่างน้อย 1 รายการ');
                return;
            }

            const button = document.querySelector('.btn-save-all');
            button.disabled = true;
            button.textContent = '⏳ กำลังบันทึกทั้งหมด...';

            fetch('save_all_prices.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ prices: prices })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`บันทึกราคาสำเร็จ ${data.updated} รายการ!`);
                    button.textContent = '✓ บันทึกสำเร็จ';
                    setTimeout(() => {
                        button.textContent = '💾 บันทึกทั้งหมด';
                        button.disabled = false;
                    }, 3000);
                } else {
                    showError(data.error || 'เกิดข้อผิดพลาด');
                    button.disabled = false;
                    button.textContent = '💾 บันทึกทั้งหมด';
                }
            })
            .catch(error => {
                showError('ไม่สามารถเชื่อมต่อได้');
                button.disabled = false;
                button.textContent = '💾 บันทึกทั้งหมด';
            });
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = '✓ ' + message;
            successDiv.classList.add('show');
            setTimeout(() => {
                successDiv.classList.remove('show');
            }, 3000);
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = '✗ ' + message;
            errorDiv.classList.add('show');
            setTimeout(() => {
                errorDiv.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
<?php oci_close($conn); ?>
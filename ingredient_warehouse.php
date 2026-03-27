<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator - คลังวัตถุดิบ</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Kanit', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .date-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .date-btn {
            flex: 1;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .date-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .result-container {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .qr-display {
            text-align: center;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .qr-display img {
            max-width: 300px;
            width: 100%;
            border: 5px solid white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .product-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .product-info h3 {
            font-size: 24px;
            margin-bottom: 15px;
            text-align: center;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .print-btn {
            background: #4caf50;
            color: white;
        }

        .print-btn:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .back-btn {
            background: #95a5a6;
            color: white;
            padding: 12px;
        }

        .back-btn:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #667eea;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .error.active {
            display: block;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .success.active {
            display: block;
            animation: slideDown 0.5s;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .add-product-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }

        .withdraw-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .withdraw-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
        }

        .manage-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ffa500 0%, #ff8c00 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .manage-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 165, 0, 0.4);
        }

        .product-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }

        .product-list::-webkit-scrollbar {
            width: 8px;
        }

        .product-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .product-list::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .product-list::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .product-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .product-item-info {
            flex: 1;
        }

        .product-item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .product-item-category {
            font-size: 14px;
            color: #666;
        }

        .delete-btn {
            padding: 8px 15px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #667eea;
            font-size: 24px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            @page {
                size: 60mm 40mm landscape;
                margin: 0;
            }

            .container {
                box-shadow: none;
                padding: 0;
                max-width: 60mm;
                height: 40mm;
                display: flex;
                flex-direction: row;
                align-items: center;
            }

            .form-container,
            .action-buttons,
            .header {
                display: none !important;
            }

            .result-container {
                display: flex !important;
                width: 100%;
                height: 100%;
                padding: 2mm;
            }

            .qr-display {
                flex: 1;
                padding: 0;
                margin: 0;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .qr-display img {
                max-width: 35mm;
                max-height: 35mm;
                border: none;
                box-shadow: none;
                border-radius: 0;
            }

            .product-info {
                flex: 1;
                padding: 2mm;
                margin: 0;
                background: white;
                color: #000;
                border-radius: 0;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .product-info h3 {
                display: none;
            }

            .info-item {
                border: none;
                padding: 1mm 0;
                display: block;
            }

            .info-label {
                display: none;
            }

            .info-value {
                font-size: 10pt;
                color: #000;
                text-align: left;
            }
        }

        @media screen and (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .modal-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">📦</div>
            <h1>QR Code Generator</h1>
            <p style="color: #666;">คลังวัตถุดิบ</p>
        </div>

        <div class="error" id="errorMessage"></div>
        <div class="success" id="successMessage"></div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>กำลังประมวลผล...</p>
        </div>

        <div class="form-container" id="formContainer">
            <form id="qrForm">
                <div class="form-group">
                    <label for="prefixCode">ตัวนำหน้ารหัส (Prefix)</label>
                    <select id="prefixCode" required>
                        <option value="">-- เลือกตัวนำหน้า --</option>
                    </select>
                    <button type="button" class="date-btn" onclick="showAddPrefixModal()" style="width: 100%; margin-top: 10px;">
                        ➕ สร้าง Prefix ใหม่
                    </button>
                </div>

                <div class="form-group">
                    <label for="productType">ประเภทสินค้า</label>
                    <select id="productType" required>
                        <option value="">-- เลือกประเภทสินค้า --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="weight">น้ำหนัก (กิโลกรัม)</label>
                    <input 
                        type="number" 
                        id="weight" 
                        step="0.01" 
                        min="0.01" 
                        placeholder="0.00"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="receiveDate">วันที่รับเข้า</label>
                    <input 
                        type="date" 
                        id="receiveDate" 
                        required
                    >
                    <div class="date-buttons">
                        <button type="button" class="date-btn" onclick="setToday()">วันนี้</button>
                        <button type="button" class="date-btn" onclick="setYesterday()">เมื่อวาน</button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    🔍 สร้าง QR Code
                </button>

                <button type="button" class="add-product-btn" onclick="showAddProductModal()">
                    ➕ เพิ่มวัตถุดิบใหม่
                </button>

                <button type="button" class="withdraw-btn" onclick="showWithdrawModal()">
                    📤 เบิกวัตถุดิบออก
                </button>

                <button type="button" class="manage-btn" onclick="showManageProductModal()">
                    🗑️ จัดการวัตถุดิบ
                </button>

                <button type="button" class="manage-btn" onclick="showManagePrefixModal()" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                    🏷️ จัดการ Prefix
                </button>
            </form>
        </div>

        <div class="result-container" id="resultContainer">
            <div class="qr-display">
                <img id="qrImage" src="" alt="QR Code">
            </div>

            <div class="product-info">
                <h3>ข้อมูลสินค้า</h3>
                <div class="info-item">
                    <span class="info-label">รหัสวัตถุดิบ:</span>
                    <span class="info-value" id="displayProductId"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">ประเภทเนื้อ:</span>
                    <span class="info-value" id="displayProductType"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">น้ำหนัก:</span>
                    <span class="info-value" id="displayWeight"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">วันที่รับเข้า:</span>
                    <span class="info-value" id="displayDate"></span>
                </div>
            </div>

            <div class="action-buttons">
                <button class="action-btn print-btn" onclick="printQR()">
                    🖨️ พิมพ์
                </button>
                <button class="action-btn back-btn" onclick="resetForm()">
                    ← สร้างใหม่
                </button>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับเพิ่ม Prefix -->
    <div id="addPrefixModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ สร้าง Prefix ใหม่</h2>
            </div>
            <form id="addPrefixForm">
                <div class="form-group">
                    <label for="newPrefixCode">รหัส Prefix (ตัวอักษรภาษาอังกฤษ 2-5 ตัว)</label>
                    <input 
                        type="text" 
                        id="newPrefixCode" 
                        placeholder="เช่น CHK, PORK, BEEF"
                        pattern="[A-Za-z]{2,5}"
                        maxlength="5"
                        style="text-transform: uppercase;"
                        required
                    >
                    <small style="color: #666; display: block; margin-top: 5px;">
                        ตัวอย่าง: CHK (ไก่), PORK (หมู), BEEF (เนื้อ)
                    </small>
                </div>
                <div class="form-group">
                    <label for="newPrefixDescription">คำอธิบาย</label>
                    <input 
                        type="text" 
                        id="newPrefixDescription" 
                        placeholder="เช่น วัตถุดิบไก่, วัตถุดิบหมู"
                        required
                    >
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="submit-btn" style="flex: 1;">
                        ✓ บันทึก
                    </button>
                    <button type="button" class="back-btn" style="flex: 1;" onclick="closeAddPrefixModal()">
                        ✕ ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สำหรับจัดการ Prefix -->
    <div id="managePrefixModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 style="color: #9b59b6;">🏷️ จัดการ Prefix</h2>
            </div>
            
            <div class="form-group">
                <label>ค้นหา Prefix</label>
                <input 
                    type="text" 
                    id="searchPrefix" 
                    placeholder="พิมพ์รหัสหรือคำอธิบายเพื่อค้นหา"
                    onkeyup="filterPrefixes()"
                >
            </div>

            <div id="prefixList" class="product-list">
                <div style="text-align: center; padding: 40px; color: #999;">
                    <div style="font-size: 48px; margin-bottom: 10px;">🏷️</div>
                    <p>กำลังโหลดข้อมูล...</p>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="button" class="back-btn" style="width: 100%; padding: 15px;" onclick="closeManagePrefixModal()">
                    ✕ ปิด
                </button>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับแก้ไข Prefix -->
    <div id="editPrefixModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ แก้ไข Prefix</h2>
            </div>
            <form id="editPrefixForm">
                <input type="hidden" id="editPrefixId">
                <div class="form-group">
                    <label for="editPrefixCode">รหัส Prefix</label>
                    <input 
                        type="text" 
                        id="editPrefixCode" 
                        readonly
                        style="background:#f5f5f5;cursor:not-allowed"
                    >
                    <small style="color: #666; display: block; margin-top: 5px;">
                        ⚠️ ไม่สามารถแก้ไขรหัสได้ เนื่องจากมีการใช้งานอยู่
                    </small>
                </div>
                <div class="form-group">
                    <label for="editPrefixDescription">คำอธิบาย</label>
                    <input 
                        type="text" 
                        id="editPrefixDescription" 
                        placeholder="เช่น วัตถุดิบไก่, วัตถุดิบหมู"
                        required
                    >
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="submit-btn" style="flex: 1;">
                        ✓ บันทึก
                    </button>
                    <button type="button" class="back-btn" style="flex: 1;" onclick="closeEditPrefixModal()">
                        ✕ ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สำหรับเพิ่มวัตถุดิบ -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ เพิ่มวัตถุดิบใหม่</h2>
            </div>
            <form id="addProductForm">
                <div class="form-group">
                    <label for="newProductName">ชื่อวัตถุดิบ</label>
                    <input 
                        type="text" 
                        id="newProductName" 
                        placeholder="เช่น เนื้อสันใน"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="newProductCategory">หมวดหมู่</label>
                    <select id="newProductCategory" required>
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <option value="หมู">หมู</option>
                        <option value="เนื้อ">เนื้อ</option>
                        <option value="ไก่">ไก่</option>
                        <option value="อาหารทะเล">อาหารทะเล</option>
                        <option value="ผัก">ผัก</option>
                        <option value="เครื่องเทศ">เครื่องเทศ</option>
                        <option value="อื่นๆ">อื่นๆ</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="submit-btn" style="flex: 1;">
                        ✓ บันทึก
                    </button>
                    <button type="button" class="back-btn" style="flex: 1;" onclick="closeAddProductModal()">
                        ✕ ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สำหรับเบิกวัตถุดิบ -->
    <div id="withdrawModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 style="color: #ff6b6b;">📤 เบิกวัตถุดิบออก</h2>
            </div>
            
            <div class="form-group">
                <label>ค้นหาด้วย QR Code หรือ รหัสวัตถุดิบ</label>
                <input 
                    type="text" 
                    id="searchInventory" 
                    placeholder="สแกน QR Code หรือพิมพ์รหัส (เช่น PORK-000001)"
                >
                <button type="button" class="date-btn" onclick="searchInventory()" style="width: 100%; margin-top: 10px;">
                    🔍 ค้นหา
                </button>
            </div>

            <div id="inventoryResult" style="display: none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                <h3 style="color: #667eea; margin-bottom: 15px;">ข้อมูลวัตถุดิบ</h3>
                <div style="margin-bottom: 10px;">
                    <strong>รหัส:</strong> <span id="withdrawInventoryId"></span>
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>ชื่อสินค้า:</strong> <span id="withdrawProductName"></span>
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>น้ำหนัก:</strong> <span id="withdrawWeight"></span> ก.
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>วันที่รับเข้า:</strong> <span id="withdrawReceiveDate"></span>
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>สถานะ:</strong> <span id="withdrawStatus"></span>
                </div>

                <form id="withdrawForm" style="margin-top: 20px;">
                    <input type="hidden" id="withdrawInventoryIdHidden">
                    <div class="form-group">
                        <label for="withdrawAmount">จำนวนที่ต้องการเบิก (กรัม)</label>
                        <input 
                            type="number" 
                            id="withdrawAmount" 
                            step="0.01" 
                            min="0.01" 
                            placeholder="0.00"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="withdrawReason">เหตุผลการเบิก</label>
                        <textarea 
                            id="withdrawReason" 
                            rows="3" 
                            placeholder="ระบุเหตุผล (ถ้ามี)"
                        ></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="withdraw-btn" style="flex: 1;">
                            ✓ ยืนยันเบิก
                        </button>
                        <button type="button" class="back-btn" style="flex: 1;" onclick="closeWithdrawModal()">
                            ✕ ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับจัดการวัตถุดิบ -->
    <div id="manageProductModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 style="color: #ffa500;">🗑️ จัดการวัตถุดิบ</h2>
            </div>
            
            <div class="form-group">
                <label>ค้นหาวัตถุดิบ</label>
                <input 
                    type="text" 
                    id="searchProduct" 
                    placeholder="พิมพ์ชื่อวัตถุดิบหรือหมวดหมู่เพื่อค้นหา"
                    onkeyup="filterProducts()"
                >
            </div>

            <div id="productList" class="product-list">
                <div style="text-align: center; padding: 40px; color: #999;">
                    <div style="font-size: 48px; margin-bottom: 10px;">📦</div>
                    <p>กำลังโหลดข้อมูล...</p>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="button" class="back-btn" style="width: 100%; padding: 15px;" onclick="closeManageProductModal()">
                    ✕ ปิด
                </button>
            </div>
        </div>
    </div>

    <script>
        // ตัวแปรสำหรับเก็บข้อมูล
        let allProducts = [];
        let allPrefixes = [];

        // เริ่มต้นโปรแกรม
        document.addEventListener('DOMContentLoaded', function() {
            setToday();
            loadProducts();
            loadPrefixes();
        });

        // ฟังก์ชันจัดการวันที่
        function setToday() {
            const today = new Date();
            document.getElementById('receiveDate').value = formatDate(today);
        }

        function setYesterday() {
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            document.getElementById('receiveDate').value = formatDate(yesterday);
        }

        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // ฟังก์ชันแสดงข้อความ
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.classList.add('active');
            setTimeout(() => {
                errorDiv.classList.remove('active');
            }, 5000);
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.classList.add('active');
            setTimeout(() => {
                successDiv.classList.remove('active');
            }, 5000);
        }

        // โหลดรายการสินค้า
        function loadProducts() {
            fetch('get_products.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('productType');
                    select.innerHTML = '<option value="">-- เลือกประเภทสินค้า --</option>';
                    
                    if (data.success) {
                        data.products.forEach(product => {
                            const option = document.createElement('option');
                            option.value = product.PRODUCT_ID;
                            option.textContent = product.PRODUCT_NAME;
                            select.appendChild(option);
                        });
                    } else {
                        showError('ไม่สามารถโหลดรายการสินค้าได้');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }

        // โหลดรายการ Prefix
        function loadPrefixes() {
            fetch('get_prefixes.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('prefixCode');
                    select.innerHTML = '<option value="">-- เลือกตัวนำหน้า --</option>';
                    
                    if (data.success) {
                        allPrefixes = data.prefixes;
                        data.prefixes.forEach(prefix => {
                            const option = document.createElement('option');
                            option.value = prefix.PREFIX_CODE;
                            option.textContent = `${prefix.PREFIX_CODE} - ${prefix.DESCRIPTION}`;
                            select.appendChild(option);
                        });
                    } else {
                        showError('ไม่สามารถโหลดรายการ Prefix ได้');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('เกิดข้อผิดพลาดในการโหลด Prefix');
                });
        }

        // สร้าง QR Code
        document.getElementById('qrForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('prefixCode', document.getElementById('prefixCode').value);
            formData.append('productId', document.getElementById('productType').value);
            formData.append('weight', document.getElementById('weight').value);
            formData.append('receiveDate', document.getElementById('receiveDate').value);

            if (!formData.get('prefixCode') || !formData.get('productId') || !formData.get('weight') || !formData.get('receiveDate')) {
                showError('กรุณากรอกข้อมูลให้ครบถ้วน');
                return;
            }

            document.getElementById('loading').classList.add('active');
            document.getElementById('formContainer').style.display = 'none';

            fetch('generate_qr.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    document.getElementById('qrImage').src = data.qrUrl;
                    document.getElementById('displayProductId').textContent = data.inventoryId;
                    document.getElementById('displayProductType').textContent = data.productName;
                    document.getElementById('displayWeight').textContent = data.weight + ' กก.';
                    document.getElementById('displayDate').textContent = data.receiveDate;
                    
                    document.getElementById('resultContainer').style.display = 'block';
                    showSuccess('สร้าง QR Code สำเร็จ!');
                } else {
                    showError('เกิดข้อผิดพลาด: ' + data.error);
                    document.getElementById('formContainer').style.display = 'block';
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
                document.getElementById('formContainer').style.display = 'block';
            });
        });

        // Modal เพิ่ม Prefix
        function showAddPrefixModal() {
            document.getElementById('addPrefixModal').classList.add('active');
            document.getElementById('newPrefixCode').focus();
        }

        function closeAddPrefixModal() {
            document.getElementById('addPrefixModal').classList.remove('active');
            document.getElementById('addPrefixForm').reset();
        }

        document.getElementById('addPrefixForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const prefixCode = document.getElementById('newPrefixCode').value.toUpperCase();
            const description = document.getElementById('newPrefixDescription').value;

            const formData = new FormData();
            formData.append('prefixCode', prefixCode);
            formData.append('description', description);

            document.getElementById('loading').classList.add('active');

            fetch('add_prefix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    showSuccess('เพิ่ม Prefix สำเร็จ!');
                    closeAddPrefixModal();
                    loadPrefixes();
                } else {
                    showError('เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
            });
        });

        // Modal แก้ไข Prefix
        function editPrefix(prefixId) {
            document.getElementById('loading').classList.add('active');
            
            fetch('get_prefix_detail.php?id=' + prefixId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').classList.remove('active');
                    
                    if (data.success) {
                        document.getElementById('editPrefixId').value = data.prefix.PREFIX_ID;
                        document.getElementById('editPrefixCode').value = data.prefix.PREFIX_CODE;
                        document.getElementById('editPrefixDescription').value = data.prefix.DESCRIPTION;
                        document.getElementById('editPrefixModal').classList.add('active');
                    } else {
                        showError('ไม่พบข้อมูล Prefix');
                    }
                })
                .catch(error => {
                    document.getElementById('loading').classList.remove('active');
                    showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
                });
        }

        function closeEditPrefixModal() {
            document.getElementById('editPrefixModal').classList.remove('active');
            document.getElementById('editPrefixForm').reset();
        }

        document.getElementById('editPrefixForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const prefixId = document.getElementById('editPrefixId').value;
            const description = document.getElementById('editPrefixDescription').value;

            const formData = new FormData();
            formData.append('prefixId', prefixId);
            formData.append('description', description);

            document.getElementById('loading').classList.add('active');

            fetch('edit_prefix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    showSuccess('แก้ไข Prefix สำเร็จ!');
                    closeEditPrefixModal();
                    loadPrefixes();
                    loadPrefixesForManagement();
                } else {
                    showError('เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
            });
        });

        // Modal จัดการ Prefix
        function showManagePrefixModal() {
            document.getElementById('managePrefixModal').classList.add('active');
            loadPrefixesForManagement();
        }

        function closeManagePrefixModal() {
            document.getElementById('managePrefixModal').classList.remove('active');
            document.getElementById('searchPrefix').value = '';
        }

        function loadPrefixesForManagement() {
            document.getElementById('loading').classList.add('active');
            
            fetch('get_prefixes.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').classList.remove('active');
                    
                    if (data.success) {
                        allPrefixes = data.prefixes;
                        displayPrefixes(allPrefixes);
                    } else {
                        showError('ไม่สามารถโหลดรายการ Prefix ได้');
                    }
                })
                .catch(error => {
                    document.getElementById('loading').classList.remove('active');
                    console.error('Error:', error);
                    showError('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }

        function displayPrefixes(prefixes) {
            const prefixList = document.getElementById('prefixList');
            
            if (prefixes.length === 0) {
                prefixList.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #999;">
                        <div style="font-size: 48px; margin-bottom: 10px;">🏷️</div>
                        <p>ไม่พบ Prefix</p>
                    </div>
                `;
                return;
            }
            
            prefixList.innerHTML = prefixes.map(prefix => `
                <div class="product-item" id="prefix-${prefix.PREFIX_ID}">
                    <div class="product-item-info">
                        <div class="product-item-name">${prefix.PREFIX_CODE}</div>
                        <div class="product-item-category">${prefix.DESCRIPTION}</div>
                    </div>
                    <div style="display:flex;gap:5px">
                        <button class="delete-btn" style="background:#3498db" onclick="editPrefix(${prefix.PREFIX_ID})" title="แก้ไข">
                            ✏️ แก้ไข
                        </button>
                        <button class="delete-btn" onclick="confirmDeletePrefix(${prefix.PREFIX_ID}, '${prefix.PREFIX_CODE.replace(/'/g, "\\'")}')">
                            🗑️ ลบ
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function filterPrefixes() {
            const searchTerm = document.getElementById('searchPrefix').value.toLowerCase();
            const filtered = allPrefixes.filter(prefix => 
                prefix.PREFIX_CODE.toLowerCase().includes(searchTerm) ||
                (prefix.DESCRIPTION && prefix.DESCRIPTION.toLowerCase().includes(searchTerm))
            );
            displayPrefixes(filtered);
        }

        function confirmDeletePrefix(prefixId, prefixCode) {
            if (confirm(`คุณแน่ใจหรือไม่ที่จะลบ Prefix "${prefixCode}"?\n\nการลบจะไม่สามารถกู้คืนได้`)) {
                deletePrefix(prefixId);
            }
        }

        function deletePrefix(prefixId) {
            document.getElementById('loading').classList.add('active');
            
            const formData = new FormData();
            formData.append('prefixId', prefixId);
            
            fetch('delete_prefix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    showSuccess('ลบ Prefix สำเร็จ!');
                    loadPrefixesForManagement();
                    loadPrefixes();
                } else {
                    showError('เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
            });
        }

        // Modal เพิ่มวัตถุดิบ
        function showAddProductModal() {
            document.getElementById('addProductModal').classList.add('active');
            document.getElementById('newProductName').focus();
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').classList.remove('active');
            document.getElementById('addProductForm').reset();
        }

        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('productName', document.getElementById('newProductName').value);
            formData.append('category', document.getElementById('newProductCategory').value);

            document.getElementById('loading').classList.add('active');

            fetch('add_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    showSuccess('เพิ่มวัตถุดิบสำเร็จ!');
                    closeAddProductModal();
                    loadProducts();
                } else {
                    showError('เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
            });
        });

        // Modal เบิกวัตถุดิบ
        function showWithdrawModal() {
            document.getElementById('withdrawModal').classList.add('active');
            document.getElementById('searchInventory').focus();
        }

        function closeWithdrawModal() {
            document.getElementById('withdrawModal').classList.remove('active');
            document.getElementById('searchInventory').value = '';
            document.getElementById('inventoryResult').style.display = 'none';
            document.getElementById('withdrawForm').reset();
        }

        function searchInventory() {
            const searchValue = document.getElementById('searchInventory').value.trim();
            
            if (!searchValue) {
                showError('กรุณากรอกรหัสวัตถุดิบ');
                return;
            }

            document.getElementById('loading').classList.add('active');

            fetch('search_inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'inventoryCode=' + encodeURIComponent(searchValue)
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    document.getElementById('withdrawInventoryId').textContent = data.inventory.INVENTORY_CODE;
                    document.getElementById('withdrawProductName').textContent = data.inventory.PRODUCT_NAME;
                    document.getElementById('withdrawWeight').textContent = data.inventory.WEIGHT;
                    document.getElementById('withdrawReceiveDate').textContent = data.inventory.RECEIVE_DATE;
                    document.getElementById('withdrawStatus').textContent = data.inventory.STATUS === 'IN_STOCK' ? 'มีในคลัง' : 'เบิกแล้ว';
                    document.getElementById('withdrawInventoryIdHidden').value = data.inventory.INVENTORY_ID;
                    
                    document.getElementById('inventoryResult').style.display = 'block';
                    
                    if (data.inventory.STATUS !== 'IN_STOCK') {
                        showError('วัตถุดิบนี้ถูกเบิกออกแล้ว');
                        document.getElementById('withdrawForm').style.display = 'none';
                    } else {
                        document.getElementById('withdrawForm').style.display = 'block';
                    }
                } else {
                    showError('ไม่พบข้อมูล: ' + data.error);
                    document.getElementById('inventoryResult').style.display = 'none';
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
            });
        }

        document.getElementById('withdrawForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('inventoryId', document.getElementById('withdrawInventoryIdHidden').value);
            formData.append('amount', document.getElementById('withdrawAmount').value);
            formData.append('reason', document.getElementById('withdrawReason').value);

            document.getElementById('loading').classList.add('active');

            fetch('withdraw_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    showSuccess('เบิกวัตถุดิบสำเร็จ!');
                    closeWithdrawModal();
                } else {
                    showError('เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
            });
        });

        // Modal จัดการวัตถุดิบ
        function showManageProductModal() {
            document.getElementById('manageProductModal').classList.add('active');
            loadProductsForManagement();
        }

        function closeManageProductModal() {
            document.getElementById('manageProductModal').classList.remove('active');
            document.getElementById('searchProduct').value = '';
        }

        function loadProductsForManagement() {
            document.getElementById('loading').classList.add('active');
            
            fetch('get_products.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').classList.remove('active');
                    
                    if (data.success) {
                        allProducts = data.products;
                        displayProducts(allProducts);
                    } else {
                        showError('ไม่สามารถโหลดรายการสินค้าได้');
                    }
                })
                .catch(error => {
                    document.getElementById('loading').classList.remove('active');
                    console.error('Error:', error);
                    showError('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }

        function displayProducts(products) {
            const productList = document.getElementById('productList');
            
            if (products.length === 0) {
                productList.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #999;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📦</div>
                        <p>ไม่พบวัตถุดิบ</p>
                    </div>
                `;
                return;
            }
            
            productList.innerHTML = products.map(product => `
                <div class="product-item" id="product-${product.PRODUCT_ID}">
                    <div class="product-item-info">
                        <div class="product-item-name">${product.PRODUCT_NAME}</div>
                        <div class="product-item-category">หมวดหมู่: ${product.CATEGORY || 'ไม่ระบุ'}</div>
                    </div>
                    <button class="delete-btn" onclick="confirmDeleteProduct(${product.PRODUCT_ID}, '${product.PRODUCT_NAME.replace(/'/g, "\\'")}')">
                        🗑️ ลบ
                    </button>
                </div>
            `).join('');
        }

        function filterProducts() {
            const searchTerm = document.getElementById('searchProduct').value.toLowerCase();
            const filtered = allProducts.filter(product => 
                product.PRODUCT_NAME.toLowerCase().includes(searchTerm) ||
                (product.CATEGORY && product.CATEGORY.toLowerCase().includes(searchTerm))
            );
            displayProducts(filtered);
        }

        function confirmDeleteProduct(productId, productName) {
            if (confirm(`คุณแน่ใจหรือไม่ที่จะลบ "${productName}"?\n\nการลบจะไม่สามารถกู้คืนได้`)) {
                deleteProduct(productId);
            }
        }

        function deleteProduct(productId) {
            document.getElementById('loading').classList.add('active');
            
            const formData = new FormData();
            formData.append('productId', productId);
            
            fetch('delete_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    showSuccess('ลบวัตถุดิบสำเร็จ!');
                    loadProductsForManagement();
                    loadProducts();
                } else {
                    showError('เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                showError('ไม่สามารถเชื่อมต่อได้: ' + error.message);
            });
        }

        // ฟังก์ชันพิมพ์และรีเซ็ต
        function printQR() {
            window.print();
        }

        function resetForm() {
            document.getElementById('qrForm').reset();
            document.getElementById('resultContainer').style.display = 'none';
            document.getElementById('formContainer').style.display = 'block';
            setToday();
        }

        // ปิด Modal เมื่อคลิกนอกกรอบ
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        // รองรับการสแกน QR Code
        document.getElementById('searchInventory').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchInventory();
            }
        });
    </script>
</body>
</html>
<?php
include 'connect.php';
date_default_timezone_set('Asia/Bangkok');

// ฟังก์ชันช่วยเหลือ (copy มาจาก ingredient.php)
function getFileIcon($fileType) {
    $icons = [
        'pdf' => '📕', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
        'xlsx' => '📗', 'xls' => '📗', 'docx' => '📘', 'doc' => '📘', 'txt' => '📄'
    ];
    return $icons[strtolower($fileType)] ?? '📎';
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    else return $bytes . ' bytes';
}

// ---------------------- บันทึกไฟล์แนบ (ไม่ผูก INGREDIENTID) ----------------------
// ในส่วนบันทึกไฟล์ (แทนที่เดิมทั้งหมด)
if (isset($_POST['upload_attachment'])) {
    $description = trim($_POST['attachment_description'] ?? '');

    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == 0) {
        $file = $_FILES['attachment_file'];
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'xlsx', 'xls', 'docx', 'doc', 'txt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $fileSize = $file['size'];
        $maxSize = 10 * 1024 * 1024;

        if (!in_array($fileExtension, $allowed_types)) {
            $error = "ประเภทไฟล์ไม่รองรับ";
        } elseif ($fileSize > $maxSize) {
            $error = "ไฟล์ใหญ่เกิน 10MB";
        } else {
            $uploadDir = 'ingredient_attachments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $newFileName = 'GLOBAL_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // SQL ใช้ชื่อ placeholder ชัดเจน ไม่ซ้ำ ไม่มี space ท้าย
                $sql = "INSERT INTO GLOBAL_ATTACHMENTS 
                        (FILE_NAME, FILE_PATH, FILE_TYPE, FILE_SIZE, DESCRIPTION, UPLOAD_DATE)
                        VALUES (:p_fname, :p_fpath, :p_ftype, :p_fsize, :p_desc, SYSDATE)";

                $stid = oci_parse($conn, $sql);

                // Bind ให้ชื่อตรงเป๊ะ 100% (ใช้ prefix p_ เพื่อป้องกันซ้ำ)
                oci_bind_by_name($stid, ":p_fname", $originalName);
                oci_bind_by_name($stid, ":p_fpath", $uploadPath);
                oci_bind_by_name($stid, ":p_ftype", $fileExtension);
                oci_bind_by_name($stid, ":p_fsize", $fileSize);
                oci_bind_by_name($stid, ":p_desc", $description);

                if (oci_execute($stid)) {
                    oci_commit($conn);
                    header("Location: ingredient_attachment.php?msg=uploaded");
                    exit();
                } else {
                    $e = oci_error($stid);
                    $error = "บันทึกฐานข้อมูลล้มเหลว: " . htmlentities($e['message']);
                    unlink($uploadPath);
                }
            } else {
                $error = "อัปโหลดไฟล์ล้มเหลว (ตรวจสอบ permission โฟลเดอร์ ingredient_attachments/)";
            }
        }
    } else {
        $error = "กรุณาเลือกไฟล์ก่อน";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บิลไฟล์แนบเอกสาร/รูปภาพ - รวมทั้งระบบ</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 20px;
            color: #1f2937;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px;
        }
        h1 {
            color: #1e40af;
            margin-top: 0;
            font-size: 1.8rem;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 25px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }
        .back-btn:hover {
            text-decoration: underline;
        }
        .upload-section {
            background: #f0f9ff;
            border: 2px solid #0284c7;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 40px;
        }
        .success-msg, .error-msg {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success-msg {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .error-msg {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
        }
        input[type="file"], textarea {
            width: 100%;
            padding: 12px;
            border: 2px dashed #94a3b8;
            border-radius: 8px;
            background: white;
        }
        textarea {
            border: 1px solid #cbd5e1;
            resize: vertical;
            min-height: 90px;
        }
        .btn-upload {
            background: #0ea5e9;
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-upload:hover {
            background: #0284c7;
        }
        .attachment-list h3 {
            margin: 40px 0 20px;
            color: #1e40af;
            font-size: 1.4rem;
        }
        .attachment-item {
            background: #f8fafc;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 5px solid #3b82f6;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }
        .attachment-info {
            flex: 1;
        }
        .attachment-name {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 6px;
        }
        .attachment-meta {
            color: #6b7280;
            font-size: 0.9rem;
        }
        .attachment-actions a {
            color: #3b82f6;
            margin-right: 15px;
            text-decoration: none;
            font-weight: 500;
        }
        .attachment-actions a:hover {
            text-decoration: underline;
        }
        .no-files {
            text-align: center;
            color: #9ca3af;
            padding: 60px 0;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="container">
    <a href="ingredient.php" class="back-btn">← กลับไปหน้า Ingredient Management</a>
    
    <h1>📎 บิลไฟล์แนบเอกสาร/รูปภาพ - รวมทั้งระบบ</h1>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'uploaded'): ?>
        <div class="success-msg">✅ อัปโหลดไฟล์เรียบร้อยแล้ว! (<?= date('d/m/Y H:i') ?>)</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="error-msg">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ฟอร์มอัปโหลดไฟล์ -->
    <div class="upload-section">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="upload_attachment" value="1">

            <div class="form-group">
                <label>เลือกไฟล์ (PDF, รูปภาพ, Excel, Word, Text | สูงสุด 10MB)</label>
                <input type="file" name="attachment_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.xlsx,.xls,.docx,.doc,.txt" required>
            </div>

            <div class="form-group">
                <label>คำอธิบายไฟล์ (optional)</label>
                <textarea name="attachment_description" rows="3" placeholder="เช่น ใบส่งของรวมวันที่ 12/01/2569, COA หลายล็อต, เอกสารตรวจสอบคุณภาพ"></textarea>
            </div>

            <div style="text-align: right;">
                <button type="submit" class="btn-upload">📤 บันทึกไฟล์แนบ</button>
            </div>
        </form>
    </div>

    <!-- รายการไฟล์แนบทั้งหมด -->
    <!-- รายการไฟล์แนบทั้งหมด -->
<div class="attachment-list">
    <h3>ประวัติไฟล์แนบทั้งหมด (เรียงตามวันที่ล่าสุด)</h3>

    <?php
    $sql = "SELECT 
            ATTACHMENT_ID,
            FILE_NAME,
            FILE_PATH,
            FILE_TYPE,
            FILE_SIZE,
            DESCRIPTION,
            TO_CHAR(UPLOAD_DATE, 'DD/MM/YYYY HH24:MI:SS') AS UPLOAD_DATE_FORMATTED
        FROM GLOBAL_ATTACHMENTS 
        ORDER BY UPLOAD_DATE DESC";
    
    $stid = oci_parse($conn, $sql);
    
    if (!$stid) {
        $e = oci_error($conn);
        echo "<div class='error-msg'>Parse error: " . htmlentities($e['message']) . "</div>";
    } else {
        $exec = oci_execute($stid);
        if (!$exec) {
            $e = oci_error($stid);
            echo "<div class='error-msg'>Execute error: " . htmlentities($e['message']) . "</div>";
        } else {
            $has_files = false;
            while ($attach = oci_fetch_assoc($stid)):
    $has_files = true;
$upload_date = $attach['UPLOAD_DATE_FORMATTED'] ?? 'ไม่ระบุวันที่';
$upload_date = $attach['UPLOAD_DATE_FORMATTED'] ?? 'ไม่ระบุวันที่';
    
    // ถ้าต้องการปรับให้สวยงามขึ้น (เพิ่ม พ.ศ. + เดือนไทย) ให้ใช้โค้ดนี้แทนบรรทัดด้านบน
    /*
    $upload_date = 'ไม่ระบุวันที่';
    if (!empty($attach['UPLOAD_DATE_FORMATTED'])) {
        list($date_part, $time_part) = explode(' ', $attach['UPLOAD_DATE_FORMATTED']);
        list($d, $m, $y) = explode('/', $date_part);
        $year_th = (int)$y + 543;
        $thai_months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $upload_date = "$d {$thai_months[(int)$m - 1]} $year_th $time_part";
    }
    */
?>
    <div class="attachment-item">
        <div class="attachment-info">
            <div class="attachment-name">
                <?= getFileIcon($attach['FILE_TYPE']) ?> 
                <?= htmlspecialchars($attach['FILE_NAME']) . '.' . $attach['FILE_TYPE'] ?>
            </div>
            <div class="attachment-meta">
                อัปโหลด: <?= $upload_date ?> • 
                ขนาด: <?= formatFileSize($attach['FILE_SIZE']) ?>
            </div>
            <?php if (!empty($attach['DESCRIPTION'])): ?>
                <div style="margin-top: 6px; color: #4b5563;">
                    📝 <?= htmlspecialchars($attach['DESCRIPTION']) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="attachment-actions">
            <a href="<?= htmlspecialchars($attach['FILE_PATH']) ?>" target="_blank">เปิดดู</a>
            <a href="<?= htmlspecialchars($attach['FILE_PATH']) ?>" download>ดาวน์โหลด</a>
        </div>
    </div>
<?php endwhile; ?>

            <?php if (!$has_files): ?>
                <div class="no-files">
                    ยังไม่มีไฟล์แนบใด ๆ ในระบบ
                </div>
            <?php endif; ?>
        <?php } ?>
    <?php } ?>
    
</div>
</div>

</body>
</html>
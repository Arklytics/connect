<?php

use PhpOffice\PhpSpreadsheet\IOFactory;



include '../session.php';
include '../db_conn.php';
include 'header.php';

if (isset($_POST['check'])) {
    Security::verifyCsrf();
}

// Handle Excel Import Submission
if (isset($_POST['import'])) {
    Security::verifyCsrf();
    $group_id = Security::intFrom($_POST['group'] ?? null);

    if ($_FILES['file']['tmp_name']) {
        require 'vendor/autoload.php'; // Include PHPSpreadsheet

       

        $inputFileName = $_FILES['file']['tmp_name'];

        try {
            // Load Excel file
            $spreadsheet = IOFactory::load($inputFileName);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            // Loop through the Excel rows
            foreach ($sheetData as $rowIndex => $row) {
                if ($rowIndex == 1) continue; // Skip header row

                $full_name = $row['A']; // Assuming full_name is in column A
                $mobile_number = $row['B']; // Assuming mobile_number is in column B
                $email = $row['C']; // Assuming email is in column C

                if (!empty($full_name) && !empty($mobile_number) && !empty($email)) {
                    $biz_id = Auth::requireLogin();

                    // Insert into gd_user_contacts
                    $stmt = $db->prepare("INSERT INTO gd_user_contacts (biz_id, full_name, phone_number, email) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $biz_id, $full_name, $mobile_number, $email);
                    $stmt->execute();

                    // Get the inserted contact_id
                    $contact_id = $stmt->insert_id;

                    // Insert into gd_group_contacts
                    $stmt2 = $db->prepare("INSERT INTO gd_group_contacts (biz_id, group_id, contact_id) VALUES (?, ?, ?)");
                    $stmt2->bind_param("iii", $biz_id, $group_id, $contact_id);
                    $stmt2->execute();
                }
            }

            $message = "Contacts imported successfully!";
            $message_type = "success";
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            $message = "Error reading Excel file: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "No file uploaded.";
        $message_type = "warning";
    }
}
?>

<div class="position-fixed top-0 end-0 p-3" style="z-index: 5;">
    <?php if (!empty($message)): ?>
        <div class="toast align-items-center text-bg-<?php echo $message_type; ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <?php echo $message; ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-lg-10 col-md-9 wg-main">
            <h4 class="mt-3"><i class="bi bi-file-earmark-spreadsheet"></i> Import Contacts</h4>
            <form action="" method="POST" enctype="multipart/form-data" class="mt-3">
                <?php echo Security::csrfField(); ?>
                <div class="row bg-light mt-2">
                    <div class="col-md-4">
                        <select class="form-control" name="group" required>
                            <option value="">--Select Group--</option>
                            <?php
                            $biz_id = Auth::requireLogin();
                            $stmt = $db->prepare('SELECT id, group_name FROM gd_groups WHERE biz_id = ? ORDER BY group_name');
                            $stmt->bind_param('i', $biz_id);
                            $stmt->execute();
                            $sql3 = $stmt->get_result();
                            while ($get3 = mysqli_fetch_assoc($sql3)) {
                                echo "<option value='" . h($get3['id']) . "'>" . h($get3['group_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-info mb-0">Upload CSV or Excel with contact columns. Connect fields are supported when present in the sheet.</div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-success" name="check" type="submit"><i class="bi bi-cloud-download-fill"></i> Submit</button>
                    </div>
                </div>
            </form>

            <div class="row mt-3">
                <?php if (isset($_POST['check'])): ?>
                    <h4>Import File to Group</h4>
                    <form action="" method="post" enctype="multipart/form-data">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="group" value="<?php echo h($_POST['group']); ?>">
                        <div class="mb-3 mt-3">
                            <input type="file" name="file" class="form-control w-25" required />
                        </div>
                        <button type="submit" name="import" class="btn btn-success">Submit</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

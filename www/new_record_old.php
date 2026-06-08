<?php
require_once 'session_check.php';
require 'db.php';

// Get user's department from DB
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT department FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($user_department);
$stmt->fetch();
$stmt->close();

$is_ivr = (strcasecmp(trim((string)$user_department), 'ivr') === 0);
$tableName = $is_ivr ? 'ivr_details' : 'support_details';

$supportheaderMap = [
    'DATE' => 'date',
    'Agent' => 'agent',
    'COMPANY NAME' => 'company_name',
    'Location' => 'location',
    'REGION (XTEND SALES)' => 'region',
    'Contact Details' => 'contact_details',
    'PRODUCT CATEGORY' => 'product_category',
    'ISSUE CATEGORY' => 'issue_category',
    'ISSUE TYPE' => 'issue_type',
    'ISSUE DETAILS/NOTES' => 'issue_details',
    'SUPPORT CATEGORY' => 'support_category',
    'Software Details' => 'software_details',
    'HARDWARE DETAILS' => 'hardware_details',
    'SOLUTION' => 'solution',
    'Total Time' => 'total_time',
    'Support Status' => 'support_status',
    'Reason' => 'ticket_id'
];   

$ivrHeaderMap = [
    'DATE' => 'date',
    'Agent' => 'agent',
    'COMPANY NAME' => 'company_name',
    'Location' => 'location',
    'REGION (XTEND SALES)' => 'region',
    'Contact Details' => 'contact_details',
    'ISSUE DETAILS' => 'issue_details',
    'ISSUE CATEGORY' => 'issue_category',
    'Note' => 'note',
    'Support Category' => 'support_category',
    'SOFTWARE DETAILS' => 'software_details',
    'HARDWARE DETAILS' => 'hardware_details',
    'SOLUTION' => 'solution',
    'Total Time' => 'total_time',
    'Status' => 'support_status',
    'Remark' => 'remark'
];

$activeMap = $is_ivr ? $ivrHeaderMap : $supportheaderMap;

// Fetch clients from ODS file for autocomplete
$clients = [];
$odsFile = __DIR__ . '/uploads/ClientList/ClientList.ods';
if (file_exists($odsFile)) {
    $zip = new ZipArchive;
    if ($zip->open($odsFile) === TRUE) {
        $xmlString = $zip->getFromName('content.xml');
        $zip->close();
        if ($xmlString) {
            $xml = simplexml_load_string($xmlString);
            if ($xml !== false) {
                $xml->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
                $xml->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
                
                $tables = $xml->xpath('//table:table');
                foreach ($tables as $table) {
                    $rows = $table->xpath('.//table:table-row');
                    if (count($rows) > 0) {
                        $firstCellTexts = $rows[0]->xpath('.//table:table-cell//text:p');
                        $firstCellText = count($firstCellTexts) > 0 ? trim(strip_tags($firstCellTexts[0]->asXML())) : '';
                        if ($firstCellText === 'Client List') {
                            for ($i = 1; $i < count($rows); $i++) {
                                $cells = $rows[$i]->xpath('.//table:table-cell');
                                if (count($cells) > 0) {
                                    $p = $cells[0]->xpath('.//text:p');
                                    if (count($p) > 0) {
                                        $clientName = trim(strip_tags($p[0]->asXML()));
                                        if (!empty($clientName)) {
                                            $clients[] = $clientName;
                                        }
                                    }
                                }
                            }
                            break; // Stop after finding the Client List table
                        }
                    }
                }
            }
        }
    }
}
$clients = array_unique($clients);
sort($clients);

$message = '';
$message_class = '';

// if ($_SERVER["REQUEST_METHOD"] == "POST") {
//     $data = [];
//     foreach ($activeMap as $label => $dbCol) {
//         $data[$dbCol] = trim($_POST[$dbCol] ?? '');
//     }
    
//     // Add unique record_id
//     $next_num = 1000;
    
//     // Get max number from both tables to ensure unique incrementing numbers
//     $sql_support = "SELECT MAX(CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)) as max_num FROM support_details WHERE record_id REGEXP '^[0-9]+[a-zA-Z]{2}$'";
//     $res_sup = $conn->query($sql_support);
//     if ($res_sup && $row = $res_sup->fetch_assoc()) {
//         if ($row['max_num'] >= $next_num) {
//             $next_num = $row['max_num'] + 1;
//         }
//     }
    
//     $sql_ivr = "SELECT MAX(CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)) as max_num FROM ivr_details WHERE record_id REGEXP '^[0-9]+[a-zA-Z]{2}$'";
//     $res_ivr = $conn->query($sql_ivr);
//     if ($res_ivr && $row = $res_ivr->fetch_assoc()) {
//         if ($row['max_num'] >= $next_num) {
//             $next_num = $row['max_num'] + 1;
//         }
//     }

//     $success = false;

//     while (!$success) {
//         // Generate two random uppercase letters
//         $letters = chr(rand(65, 90)) . chr(rand(65, 90));
//         $record_id = $next_num . $letters;
        
//         $data['record_id'] = $record_id;

//         $columns = implode(',', array_map(function($col){ return "`$col`"; }, array_keys($data)));
//         $placeholders = implode(',', array_fill(0, count($data), '?'));
        
//         $insertStmt = $conn->prepare("INSERT INTO $tableName ($columns) VALUES ($placeholders)");
//         if ($insertStmt) {
//             $types = str_repeat('s', count($data));
//             $insertStmt->bind_param($types, ...array_values($data));
//             if ($insertStmt->execute()) {
//                 $message = "Record inserted successfully.";
//                 $message_class = "success-message";
//                 $success = true;
//             } else {
//                 // Duplicate error → retry
//                 if ($conn->errno == 1062) {
//                     $insertStmt->close();
//                     continue;
//                 } else {
//                     $message = "Error inserting record: " . $insertStmt->error;
//                     $message_class = "error-message";
//                     $success = true;
//                 }
//             }
//             $insertStmt->close();
//         } else {
//             $message = "Error preparing statement: " . $conn->error;
//             $message_class = "error-message";
//             $success = true;
//         }
//     }
// }

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $data = [];
    foreach ($activeMap as $label => $dbCol) {
        $data[$dbCol] = trim($_POST[$dbCol] ?? '');
    }

    // Start base number
    $next_num = 1000;

    // Get max from support_details
    $sql_support = "
        SELECT MAX(CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)) AS max_num 
        FROM support_details 
        WHERE record_id REGEXP '^[0-9]+[a-zA-Z]{2}$'
    ";
    $res_sup = $conn->query($sql_support);
    if ($res_sup && ($row = $res_sup->fetch_assoc()) && $row['max_num'] !== null) {
        $next_num = max($next_num, $row['max_num'] + 1);
    }

    // Get max from ivr_details
    $sql_ivr = "
        SELECT MAX(CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)) AS max_num 
        FROM ivr_details 
        WHERE record_id REGEXP '^[0-9]+[a-zA-Z]{2}$'
    ";
    $res_ivr = $conn->query($sql_ivr);
    if ($res_ivr && ($row = $res_ivr->fetch_assoc()) && $row['max_num'] !== null) {
        $next_num = max($next_num, $row['max_num'] + 1);
    }

    // Add placeholder for record_id so it is included in the prepared statement
    $data['record_id'] = '';

    // Prepare insert once (better performance)
    $columns = implode(',', array_map(fn($col) => "`$col`", array_keys($data)));
    $placeholders = implode(',', array_fill(0, count($data), '?'));
    $insertStmt = $conn->prepare("INSERT INTO $tableName ($columns) VALUES ($placeholders)");

    if (!$insertStmt) {
        $message = "Error preparing statement: " . $conn->error;
        $message_class = "error-message";
        return;
    }

    $types = str_repeat('s', count($data));

    $success = false;
    $attempts = 0;
    $max_attempts = 20;

    while (!$success && $attempts < $max_attempts) {
        $attempts++;

        // Generate random letters
        $letters = chr(rand(65, 90)) . chr(rand(65, 90));
        $record_id = $next_num . $letters;

        $data['record_id'] = $record_id;

        $insertStmt->bind_param($types, ...array_values($data));

        if ($insertStmt->execute()) {
            $message = "Record inserted successfully.";
            $message_class = "success-message";
            $success = true;
        } else {
            if ($conn->errno == 1062) {
                // Duplicate → try again with new letters
                continue;
            } else {
                $message = "Error inserting record: " . $insertStmt->error;
                $message_class = "error-message";
                break;
            }
        }
    }


    $insertStmt->close();

    if (!$success && $attempts >= $max_attempts) {
        $message = "Failed to generate unique record_id after multiple attempts.";
        $message_class = "error-message";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Record</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .add-record-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 20px;
        }
        .add-record-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 20px;
            margin: 10px auto;
            background: #f8fafd;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 4px 18px rgba(44, 62, 80, 0.3);
            width: 90%;
            max-width: 1000px;
            border: 1px solid #e0e6f7;
            max-height: 65vh;
            overflow-y: auto;
        }
        .add-record-form .form-group {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .add-record-form .full-width {
            grid-column: 1 / -1;
        }
        .add-record-form label {
            font-size: 0.9em;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            text-transform: uppercase;
        }
        .add-record-form input, .add-record-form select, .add-record-form textarea {
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            outline: none;
            font-size: 0.95em;
            width: 100%;
            box-sizing: border-box;
            background: #fff;
            font-family: inherit;
        }
        .add-record-form textarea {
            resize: vertical;
        }
        .add-record-form button {
            padding: 10px 20px;
            background-color: #014e82;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1em;
            height: 42px;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .add-record-form button:hover {
            background-color: #0a5891;
        }
        @media (max-width: 768px) {
            .add-record-form {
                grid-template-columns: 1fr;
            }
        }
        .message {
            padding: 15px;
            margin-bottom: 5px;
            border-radius: 6px;
            text-align: center;
            font-weight: 500;
            width: 80%;
            max-width: 800px;
        }
        .success-message {
            background-color: #d4edda;
            color: #087c23ff;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Autocomplete styles */
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background-color: white;
            border-radius: 0 0 6px 6px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4;
            font-size: 0.95em;
        }
        .autocomplete-items div:hover {
            background-color: #e9e9e9;
        }
        .autocomplete-active {
            background-color: #014e82 !important;
            color: #ffffff;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div>
    <div class="main-content search-top" style="align-items: center; margin-left: 0; width: 100%;">
        <div class="add-record-container">
            <h2 style="padding: 10px; margin: 40px 0px 0px 0px;">Add New Record (<?php echo $is_ivr ? 'IVR' : 'Support'; ?>)</h2>
            <?php if ($message): ?>
                <div id="toast-popup" class="message <?php echo $message_class; ?>" style="position: fixed; top: 30px; right: 30px; z-index: 9999; box-shadow: 0 6px 16px rgba(0,0,0,0.15); opacity: 1; transition: opacity 0.4s ease, transform 0.4s ease; transform: translateY(0); display: flex; align-items: center; justify-content: center; width: auto; max-width: 400px; padding: 16px 24px; border-radius: 8px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('toast-popup');
                        if (toast) {
                            toast.style.opacity = '0';
                            toast.style.transform = 'translateY(-20px)';
                        }
                    }, 3000);
                    setTimeout(() => {
                        const toast = document.getElementById('toast-popup');
                        if (toast) toast.style.display = 'none';
                    }, 3400);
                </script>
            <?php endif; ?>
            
            <form action="add_new_record.php" method="post" class="add-record-form">
                <?php foreach ($activeMap as $label => $dbCol): ?>
                    <?php $isTextarea = in_array($dbCol, ['issue_details', 'software_details', 'hardware_details', 'solution', 'note', 'remark']); ?>
                    <div class="form-group">
                        <label for="<?php echo htmlspecialchars($dbCol); ?>"><?php echo htmlspecialchars($label); ?></label>
                        <?php if ($dbCol === 'date'): ?>
                            <input type="date" name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" required>
                        <?php elseif ($dbCol === 'total_time'): ?>
                            <input type="time" step="1" name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" required>
                        <?php elseif ($dbCol === 'agent'): ?>
                            <input type="text" name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" value="<?php echo htmlspecialchars(strtoupper($_SESSION['username'] ?? '')); ?>" required>
                        <?php elseif ($dbCol === 'company_name'): ?>
                            <div class="autocomplete-container">
                                <input type="text" name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" autocomplete="off" required>
                            </div>
                        <?php elseif ($dbCol === 'region'): ?>
                            <select name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" required>
                                <option value="">Select Region</option>
                                <option value="INDIA">INDIA</option>
                                <option value="DUBAI">DUBAI</option>
                                <option value="SINGAPORE">SINGAPORE</option>
                            </select>
                        <?php elseif ($dbCol === 'product_category'): ?>
                            <select name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" onfocus="if(this.options.length>6){this.size=6;}" onchange='this.size=0;' onblur="this.size=0;" style="max-height: 200px; overflow-y: auto;" required>
                                <option value="">Select Product Category</option>
                                <option value="ANALOG_LOGGER">ANALOG_LOGGER</option>
                                <option value="PRI_LOGGER">PRI_LOGGER</option>
                                <option value="DIGITAL_EXTENSION">DIGITAL_EXTENSION</option>
                                <option value="IP_LOGGER">IP_LOGGER</option>
                                <option value="XTEND_SMARTLOG">XTEND_SMARTLOG</option>
                                <option value="CALLBILLING">CALLBILLING</option>
                                <option value="CMS_HO">CMS_HO</option>
                                <option value="IVR_GATEWAY">IVR_GATEWAY</option>
                                <option value="STANDALONE_LOGGER">STANDALONE_LOGGER</option>
                                <option value="ACTIVE_LOGGER">ACTIVE_LOGGER</option>
                                <option value="XTEND_ONCALL">XTEND_ONCALL</option>
                                <option value="XTEND_VX">XTEND_VX</option>
                                <option value="XTEND_SX2">XTEND_SX2</option>
                                <option value="XTEND_SX">XTEND_SX</option>
                                <option value="LINUX_STANDALONE">LINUX_STANDALONE</option>
                            </select>
                        <?php elseif ($dbCol === 'issue_type'): ?>
                            <select name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" required>
                                <option value="">Select Issue Type</option>
                                <option value="Hardware">Hardware</option>
                                <option value="Software">Software</option>
                                <option value="Driver">Driver</option>
                                <option value="Others">Others</option>
                            </select>
                        <?php elseif ($dbCol === 'support_category'): ?>
                            <select name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" required>
                                <option value="">Select Support Category</option>
                                <option value="Mail Support">Mail Support</option>
                                <option value="Skype Support">Skype Support</option>
                                <option value="Mobile Support">Mobile Support</option>
                                <option value="CC Support">CC Support</option>
                            </select>
                        <?php elseif ($dbCol === 'support_status'): ?>
                            <select name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" required>
                                <option value="">Select Status</option>
                                <option value="Closed">Closed</option>
                                <option value="Pending">Pending</option>
                                <option value="Under Observation">Under Observation</option>
                                <option value="Escalated">Escalated</option>
                                <option value="Escalated to Presales">Escalated to Presales</option>
                                <option value="Closed-Device Replaced">Closed-Device Replaced</option>
                            </select>
                        <?php elseif ($isTextarea): ?>
                            <textarea name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" rows="3" required></textarea>
                        <?php else: ?>
                            <input type="text" name="<?php echo htmlspecialchars($dbCol); ?>" id="<?php echo htmlspecialchars($dbCol); ?>" required>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="form-group full-width" style="align-items: center; margin-top: 10px;">
                    <button type="submit"><i class="fas fa-save"></i> Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clients = <?php echo json_encode($clients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    
    function autocomplete(inp, arr) {
        if (!inp) return;
        let currentFocus;
        inp.addEventListener("input", function(e) {
            let a, b, i, val = this.value;
            closeAllLists();
            if (!val) { return false;}
            currentFocus = -1;
            
            a = document.createElement("DIV");
            a.setAttribute("id", this.id + "autocomplete-list");
            a.setAttribute("class", "autocomplete-items");
            this.parentNode.appendChild(a);
            
            let count = 0;
            for (i = 0; i < arr.length; i++) {
                if (arr[i].toUpperCase().indexOf(val.toUpperCase()) > -1) {
                    if (count >= 50) break; // Limit to 50 results
                    b = document.createElement("DIV");
                    
                    let matchIndex = arr[i].toUpperCase().indexOf(val.toUpperCase());
                    b.innerHTML = arr[i].substring(0, matchIndex);
                    b.innerHTML += "<strong>" + arr[i].substring(matchIndex, matchIndex + val.length) + "</strong>";
                    b.innerHTML += arr[i].substring(matchIndex + val.length);
                    
                    b.innerHTML += "<input type='hidden' value='" + arr[i].replace(/'/g, "&#39;") + "'>";
                    b.addEventListener("click", function(e) {
                        inp.value = this.getElementsByTagName("input")[0].value;
                        closeAllLists();
                    });
                    a.appendChild(b);
                    count++;
                }
            }
        });
        
        inp.addEventListener("keydown", function(e) {
            let x = document.getElementById(this.id + "autocomplete-list");
            if (x) x = x.getElementsByTagName("div");
            if (e.keyCode == 40) { // DOWN
                currentFocus++;
                addActive(x);
            } else if (e.keyCode == 38) { // UP
                currentFocus--;
                addActive(x);
            } else if (e.keyCode == 13) { // ENTER
                if (currentFocus > -1) {
                    if (x) {
                        e.preventDefault();
                        x[currentFocus].click();
                    }
                }
            }
        });
        
        function addActive(x) {
            if (!x) return false;
            removeActive(x);
            if (currentFocus >= x.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = (x.length - 1);
            x[currentFocus].classList.add("autocomplete-active");
            x[currentFocus].scrollIntoView({block: "nearest"});
        }
        
        function removeActive(x) {
            for (let i = 0; i < x.length; i++) {
                x[i].classList.remove("autocomplete-active");
            }
        }
        
        function closeAllLists(elmnt) {
            let x = document.getElementsByClassName("autocomplete-items");
            for (let i = 0; i < x.length; i++) {
                if (elmnt != x[i] && elmnt != inp) {
                    x[i].parentNode.removeChild(x[i]);
                }
            }
        }
        
        document.addEventListener("click", function (e) {
            closeAllLists(e.target);
        });
    }

    if (clients && clients.length > 0) {
        autocomplete(document.getElementById("company_name"), clients);
    }
    console.log(clients);
});
</script>
</body>
</html>

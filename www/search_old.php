<?php
require_once 'session_check.php';
include 'db.php';

// Fetch user department
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';
$department = '';
$stmt = $conn->prepare('SELECT department FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($department);
$stmt->fetch();
$stmt->close();

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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Advanced Search Reports</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .advanced-search-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 20px;
        }
        .advanced-search-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 20px;
            margin: 20px auto;
            background: #f8fafd;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(44, 62, 80, 0.3);
            width: 80%;
            max-width: 800px;
            border: 1px solid #e0e6f7;
            max-height: 65vh;
            overflow-y: auto;
        }
        .advanced-search-form .form-group {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .advanced-search-form label {
            font-size: 0.9em;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
        }
        .advanced-search-form input, .advanced-search-form select {
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            outline: none;
            font-size: 0.95em;
            width: 100%;
            box-sizing: border-box;
        }
        .advanced-search-form button {
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
            /* margin-top: 10px; */
            white-space: nowrap;
        }
        .advanced-search-form button:hover {
            background-color: #0a5891;
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

<div class="layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content search-top" style="align-items: center;">
        <div class="advanced-search-container">
            <h2 style="padding: 10px; margin: 60px 0px 0px 0px;">Advanced Search</h2>
            
            <form method="get" action="search_results.php" class="advanced-search-form">
                <?php if (strtolower($department) === 'manager'): ?>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_select">
                        <option value="">-- Select Department --</option>
                        <option value="ivr" <?php if ((isset($_GET['department_select']) && $_GET['department_select'] === 'ivr')) echo 'selected'; ?>>IVR</option>
                        <option value="voice_logger" <?php if ((isset($_GET['department_select']) && $_GET['department_select'] === 'voice_logger')) echo 'selected'; ?>>Voice Logger</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Agent</label>
                    <input type="text" name="agent" value="<?php echo isset($_GET['agent']) ? htmlspecialchars($_GET['agent']) : ''; ?>" placeholder="">
                </div>
                
                <div class="form-group">
                    <label>Company Name</label>
                    <div class="autocomplete-container">
                        <input type="text" id="company_name" name="company_name" value="<?php echo isset($_GET['company_name']) ? htmlspecialchars($_GET['company_name']) : ''; ?>" placeholder="Company Name" autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" value="<?php echo isset($_GET['from_date']) ? htmlspecialchars($_GET['from_date']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" value="<?php echo isset($_GET['to_date']) ? htmlspecialchars($_GET['to_date']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo isset($_GET['location']) ? htmlspecialchars($_GET['location']) : ''; ?>" placeholder="Location">
                </div>
                
                <div class="form-group">
                    <label>Hardware Details</label>
                    <input type="text" name="hardware_details" value="<?php echo isset($_GET['hardware_details']) ? htmlspecialchars($_GET['hardware_details']) : ''; ?>" placeholder="Device Id">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1; align-items: center; margin-top: 10px;">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clients = <?php echo json_encode($clients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    
    function autocomplete(inp, arr) {
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
                    if (count >= 50) break; // Limit to 50 results for performance
                    b = document.createElement("DIV");
                    
                    // Highlight matching text
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
    } else {
        console.warn("No clients loaded from ODS file.");
    }
});
</script>
</body>
</html>

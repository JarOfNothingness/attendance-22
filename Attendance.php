<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Connect to the database
include("../LoginRegisterAuthentication/connection.php");
include("../crud/header.php");

// Fetch available sections and subjects for the dropdowns
$sections_query = "SELECT DISTINCT `grade & section` FROM students";
$sections_result = mysqli_query($connection, $sections_query);

// Fetch subjects from the subjects table
$subjects_query = "SELECT id, name FROM subjects";
$subjects_result = mysqli_query($connection, $subjects_query);
// Check if section and subject have been selected
$section = $_POST['section'] ?? $_GET['section'] ?? '';
$subject_id = $_POST['subject_id'] ?? $_GET['subject_id'] ?? '';
$month = $_POST['month'] ?? $_GET['month'] ?? date('Y-m');
$saved = $_GET['saved'] ?? 0;

$subject_names = array();
while ($row = mysqli_fetch_assoc($subjects_result)) {
    $subject_names[$row['id']] = $row['name'];
}

$current_subject_name = $subject_names[$subject_id] ?? 'Select Subject';



// If attendance was saved, store the subject_id in the session
if ($saved == 1 && $subject_id) {
    $_SESSION['last_subject_id'] = $subject_id;
}

// Use the session variable if available
$subject_id = $subject_id ?: ($_SESSION['last_subject_id'] ?? '');

// Default point values
$presentPoints = 10;
$absentPoints = 0;
$latePoints = 5;

function insertPointSetter($connection, $userId, $subjectId, $presentPoints, $absentPoints, $latePoints) {
    $query = "INSERT INTO point_setter (userid, subject_id, points_present, points_absent, points_late) 
              VALUES (?, ?, ?, ?, ?) 
              ON DUPLICATE KEY UPDATE 
              points_present = VALUES(points_present), 
              points_absent = VALUES(points_absent), 
              points_late = VALUES(points_late)";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("iiiii", $userId, $subjectId, $presentPoints, $absentPoints, $latePoints);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Sheet</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
            color: #333;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #34495e;
        }
        select, input[type="month"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        button[type="submit"] {
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button[type="submit"]:hover {
            background-color: #2980b9;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            white-space: nowrap;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        select {
            width: 100%;
            padding: 5px;
        }
        .student-name {
            text-align: left;
            min-width: 150px;
        }
        .date-column {
            min-width: 40px;
        }
        .total-column {
            min-width: 60px;
        }
        .side-modal {
            position: fixed;
            right: -300px;
            top: 0;
            width: 300px;
            height: 100%;
            background-color: #fff;
            box-shadow: -2px 0 5px rgba(0,0,0,0.1);
            transition: right 0.3s ease-out;
            z-index: 1000;
            overflow-y: auto;
        }
        .side-modal.open {
            right: 0;
        }
        .side-modal-content {
            padding: 20px;
        }
        .side-modal h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .side-modal label {
            display: block;
            margin-bottom: 5px;
            color: #34495e;
        }
        .side-modal input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .side-modal button {
            width: 100%;
            padding: 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .side-modal button:hover {
            background-color: #2980b9;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            color: #aaa;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .close-btn:hover {
            color: #333;
        }
        #openModalBtn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 999;
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        #openModalBtn:hover {
            background-color: #2980b9;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .save-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .save-button:hover {
            background-color: #27ae60;
        }

        .back-button {
            background-color: #f0f0f0;
            color: #333;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .back-button:hover {
            background-color: #e0e0e0;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <?php 
        $point_setter_query = "SELECT points_present, points_absent, points_late 
        FROM point_setter 
        WHERE userid = ? AND subject_id = ?";
        $point_setter_stmt = $connection->prepare($point_setter_query);
        $point_setter_stmt->bind_param("ii", $_SESSION['userid'], $subject_id);
        $point_setter_stmt->execute();
        $point_setter_result = $point_setter_stmt->get_result();

        $points_present = 10; // Default values
        $points_absent = 0;
        $points_late = 5;

        if ($point_setter_row = $point_setter_result->fetch_assoc()) {
        $points_present = $point_setter_row['points_present'];
        $points_absent = $point_setter_row['points_absent'];
        $points_late = $point_setter_row['points_late'];
        }
    ?>
    <?php if ($section && $subject_id): ?>
        <!-- Attendance Sheet View -->
        <h2>Attendance Sheet for Section: <?php echo htmlspecialchars($section); ?> - Subject: <?php echo htmlspecialchars($current_subject_name); ?></h2>
        <div class="button-container">
            <button class="back-button" onclick="goBack()">Back to Selection</button>
            <button id="openModalBtn" onclick="openModal()">Point Setter</button>
        </div>
        <!-- Rest of your attendance sheet HTML... -->

    <?php else: ?>
        <!-- Select Section and Subject View -->
        <h2>Select Section and Subject</h2>
        <!-- Your existing form for selecting section and subject... -->
    <?php endif; ?>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($section && $subject_id)) {
        // Show success message if attendance was saved
        if ($saved == 1) {
            echo "<div class='success-message'>Attendance has been successfully saved!</div>";
        }

        // Query to get the students in the selected section and subject
        $query = "SELECT id, learners_name FROM students WHERE `grade & section` = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $section);
        $stmt->execute();
        $students = $stmt->get_result();

        // Display the attendance form
        // echo "<h2>Attendance Sheet for Section: " . htmlspecialchars($section) . " - Subject: " . htmlspecialchars($subject_id) . "</h2>";
        echo "<button id='openModalBtn' onclick='openModal()'>Point Setter</button>";
        echo "<div style='overflow-x: auto;'>";
        echo "<form method='post' action='save_attendance.php'>";
        echo "<table>";
        echo "<thead><tr><th class='student-name'>Student Name</th>";

        // Get the total number of days in the month and iterate
        $numDays = date('t', strtotime($month));
        $days_in_month = [];
        
        for ($i = 1; $i <= $numDays; $i++) {
            $dayOfWeek = date('N', strtotime("$month-$i")); // Get the day of the week (1 = Monday, 7 = Sunday)
            
            if ($dayOfWeek < 6) { // Skip Saturdays (6) and Sundays (7)
                echo "<th class='date-column'>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</th>";
                $days_in_month[] = $i; // Store valid (non-weekend) days for later use
            }
        }
        echo "<th class='total-column'>Total Present</th><th class='total-column'>Total Absent</th><th class='total-column'>Total Late</th><th class='total-column'>Total Points</th></tr></thead><tbody>";

        // Loop through students
        while ($student = $students->fetch_assoc()) {
            echo "<tr><td class='student-name'>" . htmlspecialchars($student['learners_name']) . "</td>";
        
            // Query to get attendance for the student
            $attendanceQuery = "SELECT * FROM attendance WHERE student_id = ? AND month = ? AND subject_id = ?";
            $attendanceStmt = $connection->prepare($attendanceQuery);
            $attendanceStmt->bind_param("iss", $student['id'], $month, $subject_id);
            $attendanceStmt->execute();
            $attendance = $attendanceStmt->get_result()->fetch_assoc();
        
            $totalPresent = 0;
            $totalAbsent = 0;
            $totalLate = 0;
            $totalPoints = 0;
        
            // Generate attendance dropdown for each day, skipping weekends
            foreach ($days_in_month as $i) {
                $day = "day_" . str_pad($i, 2, '0', STR_PAD_LEFT);
                $attendanceStatus = $attendance[$day] ?? 'P'; // Default to "P" for present
        
                echo "<td>
                        <select name='attendance[" . $student['id'] . "][" . $day . "]' onchange='updateTotals(this)' style='width:42px'>
                            <option value='P'" . ($attendanceStatus == 'P' ? ' selected' : '') . ">P</option>
                            <option value='A'" . ($attendanceStatus == 'A' ? ' selected' : '') . ">A</option>
                            <option value='L'" . ($attendanceStatus == 'L' ? ' selected' : '') . ">L</option>
                            <option value='E'" . ($attendanceStatus == 'E' ? ' selected' : '') . ">E</option>
                        </select>
                    </td>";
        
                // Count total present, absent, late days and calculate points
                switch ($attendanceStatus) {
                    case 'P':
                        $totalPresent++;
                        $totalPoints += $points_present;
                        break;
                    case 'A':
                        $totalAbsent++;
                        $totalPoints += $points_absent;
                        break;
                    case 'L':
                        $totalLate++;
                        $totalPoints += $points_late;
                        break;
                    // 'E' (Excused) doesn't affect points
                }
            }
        
            // Add total columns for Present, Absent, Late, and Points
            echo "<td class='total-present'>$totalPresent</td>";
            echo "<td class='total-absent'>$totalAbsent</td>";
            echo "<td class='total-late'>$totalLate</td>";
            echo "<td class='total-points'>$totalPoints</td>";
            echo "</tr>";
        }
        

        echo "</tbody></table>";
        echo "<input type='hidden' name='section' value='" . htmlspecialchars($section) . "'>";
        echo "<input type='hidden' name='month' value='" . htmlspecialchars($month) . "'>";
        echo "<input type='hidden' name='subject_id' value='" . htmlspecialchars($subject_id) . "'>";
        echo "<button type='submit' class='save-button'>Save Attendance</button>";
        echo "</form>";
        echo "</div>";
    } else {
        // If no section or subject selected, show selection form
        ?>
        <form method="post" action="Attendance.php">
            <div class="form-group">
                <label for="section">Section:</label>
                <select name="section" id="section" required>
                    <option value="">Select Section</option>
                    <?php while ($row = mysqli_fetch_assoc($sections_result)) { ?>
                        <option value="<?php echo htmlspecialchars($row['grade & section']); ?>">
                            <?php echo htmlspecialchars($row['grade & section']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label for="subject_id">Subject:</label>
                <select name="subject_id" id="subject_id" required>
                    <option value="">Select Subject</option>
                    <?php foreach ($subject_names as $id => $name) : ?>
                        <option value="<?php echo htmlspecialchars($id); ?>"
                                <?php echo ($id == $subject_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="month">Month:</label>
                <input type="month" name="month" id="month" value="<?php echo date('Y-m'); ?>">
            </div>

            <button type="submit">Load Attendance</button>
        </form>
        <?php 
            echo "<h2>View Attendance</h2>";
            echo "<form method='get' action='view_attendance.php'>";
            echo "<div class='form-group'>";
            echo "<label for='view_section'>Section:</label>";
            echo "<select name='view_section' id='view_section' required>";
            echo "<option value=''>Select Section</option>";
            mysqli_data_seek($sections_result, 0); // Reset the result pointer
            while ($row = mysqli_fetch_assoc($sections_result)) {
                echo "<option value='" . htmlspecialchars($row['grade & section']) . "'>" . htmlspecialchars($row['grade & section']) . "</option>";
            }
            echo "</select>";
            echo "</div>";

            echo "<div class='form-group'>";
            echo "<label for='view_subject_id'>Subject:</label>";
            echo "<select name='view_subject_id' id='view_subject_id' required>";
            echo "<option value=''>Select Subject</option>";
            foreach ($subject_names as $id => $name) {
                echo "<option value='" . htmlspecialchars($id) . "'>" . htmlspecialchars($name) . "</option>";
            }
            echo "</select>";
            echo "</div>";

            echo "<div class='form-group'>";
            echo "<label for='view_month'>Month:</label>";
            echo "<input type='month' name='view_month' id='view_month' value='" . date('Y-m') . "' required>";
            echo "</div>";

            echo "<button type='submit'>View Attendance</button>";
            echo "</form>";
        ?>
        <?php
    }
    ?>
</div>

<div id="pointSetterModal" class="side-modal">
    <div class="side-modal-content">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <h2>Set Points</h2>
        <label for="presentPoints">Points for Present:</label>
        <input type="number" id="presentPoints" value="<?php echo $points_present?>">
        <label for="absentPoints">Points for Absent:</label>
        <input type="number" id="absentPoints" value="<?php echo $points_absent?>">
        <label for="latePoints">Points for Late:</label>
        <input type="number" id="latePoints" value="<?php echo $points_late?>">
        <select name="subject_id" id="modal_subject_id" required>
            <option value="">Select Subject</option>
            <?php foreach ($subject_names as $id => $name) : ?>
                <option value="<?php echo htmlspecialchars($id); ?>"
                        <?php echo ($id == $subject_id) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button onclick="setPoints()">Apply Points</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var mainSubjectSelect = document.getElementById('subject_id');
    var modalSubjectSelect = document.getElementById('modal_subject_id');
    var openModalBtn = document.getElementById('openModalBtn');

    if (mainSubjectSelect && modalSubjectSelect) {
        // Sync initial value
        modalSubjectSelect.value = mainSubjectSelect.value;

        // Update modal select when main select changes
        mainSubjectSelect.addEventListener('change', function() {
            modalSubjectSelect.value = this.value;
        });

        // Ensure modal select is updated when modal is opened
        if (openModalBtn) {
            openModalBtn.addEventListener('click', function() {
                modalSubjectSelect.value = mainSubjectSelect.value;
            });
        }
    }
});

function openModal() {
    document.getElementById('pointSetterModal').classList.add('open');
}

function closeModal() {
    document.getElementById('pointSetterModal').classList.remove('open');
}

function syncSubjectSelection() {
    var mainSubjectSelect = document.getElementById('subject_id');
    var modalSubjectSelect = document.getElementById('modal_subject_id');
    
    if (mainSubjectSelect && modalSubjectSelect) {
        modalSubjectSelect.value = mainSubjectSelect.value;
    }
}


function setPoints() {
    var presentPoints = parseInt(document.getElementById('presentPoints').value);
    var absentPoints = parseInt(document.getElementById('absentPoints').value);
    var latePoints = parseInt(document.getElementById('latePoints').value);
    var subjectId = document.getElementById('modal_subject_id').value;

    // Use AJAX to send the data to the server
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "set_points.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (this.readyState === XMLHttpRequest.DONE) {
            if (this.status === 200) {
                try {
                    var response = JSON.parse(this.responseText);
                    if (response.status === "success") {
                        console.log(response.message);
                        window.presentPoints = presentPoints;
                        window.absentPoints = absentPoints;
                        window.latePoints = latePoints;
                        updateAllTotals();
                        closeModal();
                        alert("Points saved successfully!");
                    } else {
                        console.error(response.message);
                        alert("Error: " + response.message);
                    }
                } catch (e) {
                    console.error("Error parsing JSON response:", e);
                    alert("An unexpected error occurred. Please try again.");
                }
            } else {
                console.error("HTTP error:", this.status, this.statusText);
                alert("An error occurred while saving the points. Please try again.");
            }
        }
    }
    xhr.send("presentPoints=" + presentPoints + "&absentPoints=" + absentPoints + "&latePoints=" + latePoints + "&subjectId=" + subjectId);
}

function updateAllTotals() {
    var selects = document.querySelectorAll('select[name^="attendance"]');
    selects.forEach(function(select) {
        updateTotals(select);
    });
}

function updateTotals(select) {
    var row = select.closest('tr');
    var presentCount = 0;
    var absentCount = 0;
    var lateCount = 0;
    var totalPoints = 0;

    row.querySelectorAll('select').forEach(function(s) {
        switch(s.value) {
            case 'P':
                presentCount++;
                totalPoints += <?php echo $points_present; ?>;
                break;
            case 'A':
                absentCount++;
                totalPoints += <?php echo $points_absent; ?>;
                break;
            case 'L':
                lateCount++;
                totalPoints += <?php echo $points_late; ?>;
                break;
            // 'E' (Excused) doesn't affect points
        }
    });

    row.querySelector('.total-present').textContent = presentCount;
    row.querySelector('.total-absent').textContent = absentCount;
    row.querySelector('.total-late').textContent = lateCount;
    row.querySelector('.total-points').textContent = totalPoints;
}

// Initialize point values from PHP
window.presentPoints = <?php echo $points_present; ?>;
window.absentPoints = <?php echo $points_absent; ?>;
window.latePoints = <?php echo $points_late; ?>;

function goBack() {
    window.location.href = 'Attendance.php';
}

</script>

</body>
</html>

<?php
include("../crud/footer.php");
?>
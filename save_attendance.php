<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("../LoginRegisterAuthentication/connection.php");
include("../crud/header.php");

if (!isset($_SESSION['username']) || !isset($_SESSION['userid'])) {
    header("Location: ../Home/login.php");
    exit();
}

$attendanceData = $_POST['attendance'] ?? [];
$section = $_POST['section'] ?? '';
$month = $_POST['month'] ?? '';
$subject_id = $_POST['subject_id'] ?? '';

if (empty($attendanceData) || !$section || !$month || !$subject_id) {
    die('Invalid input.');
}

// Get user_id from session
$username = $_SESSION['username'];
$userQuery = "SELECT userid FROM user WHERE username = ?";
$userStmt = $connection->prepare($userQuery);
$userStmt->bind_param("s", $username);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    die('User not found.');
}

$userData = $userResult->fetch_assoc();
$user_id = $userData['userid'];

// Fetch point values for the current subject
$pointQuery = "SELECT points_present, points_absent, points_late FROM point_setter WHERE userid = ? AND subject_id = ?";
$pointStmt = $connection->prepare($pointQuery);
$pointStmt->bind_param("ii", $user_id, $subject_id);
$pointStmt->execute();
$pointResult = $pointStmt->get_result();
$pointData = $pointResult->fetch_assoc();

$presentPoints = $pointData['points_present'] ?? 10; // Default to 10 if not set
$absentPoints = $pointData['points_absent'] ?? 0;
$latePoints = $pointData['points_late'] ?? 5;

foreach ($attendanceData as $student_id => $attendance) {
    // Calculate totals
    $totalPresent = 0;
    $totalAbsent = 0;
    $totalLate = 0;
    $totalExcused = 0;
    $totalPoints = 0;

    foreach ($attendance as $status) {
        switch ($status) {
            case 'P':
                $totalPresent++;
                $totalPoints += $presentPoints;
                break;
            case 'A':
                $totalAbsent++;
                $totalPoints += $absentPoints;
                break;
            case 'L':
                $totalLate++;
                $totalPoints += $latePoints;
                break;
            case 'E':
                $totalExcused++;
                break;
        }
    }

    // Check if attendance already exists for the student
    $checkQuery = "SELECT id FROM attendance WHERE student_id = ? AND month = ? AND subject_id = ?";
    $checkStmt = $connection->prepare($checkQuery);
    $checkStmt->bind_param("iss", $student_id, $month, $subject_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing attendance
        $updateQuery = "UPDATE attendance SET ";
        $fields = [];
        $values = [];

        foreach ($attendance as $day => $status) {
            $fields[] = "$day = ?";
            $values[] = $status;
        }

        // Add total fields
        $fields[] = "total_present = ?";
        $fields[] = "total_absent = ?";
        $fields[] = "total_late = ?";
        $fields[] = "total_excused = ?";
        $fields[] = "total_points = ?";
        $values[] = $totalPresent;
        $values[] = $totalAbsent;
        $values[] = $totalLate;
        $values[] = $totalExcused;
        $values[] = $totalPoints;

        $updateQuery .= implode(", ", $fields) . " WHERE student_id = ? AND month = ? AND subject_id = ?";
        $values[] = $student_id;
        $values[] = $month;
        $values[] = $subject_id;

        $stmt = $connection->prepare($updateQuery);
        $types = str_repeat('s', count($attendance)) . 'iiiii' . 'iss';
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    } else {
        // Insert new attendance record
        $columns = array_merge(
            ['student_id', 'month', 'subject_id', 'user_id', 'section'],
            array_keys($attendance),
            ['total_present', 'total_absent', 'total_late', 'total_excused', 'total_points']
        );
        
        $placeholders = array_fill(0, count($columns), '?');
        
        $insertQuery = "INSERT INTO attendance (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
        
        $stmt = $connection->prepare($insertQuery);
        
        $types = "issis" . str_repeat('s', count($attendance)) . "iiiii";
        $values = array_merge(
            [$student_id, $month, $subject_id, $user_id, $section],
            array_values($attendance),
            [$totalPresent, $totalAbsent, $totalLate, $totalExcused, $totalPoints]
        );
        
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    }
}

echo "
<script>
window.location.href='Attendance.php?saved=1&section=" . urlencode($section) . "&subject_id=" . urlencode($subject_id) . "&month=" . urlencode($month) . "';
</script>
";
?>
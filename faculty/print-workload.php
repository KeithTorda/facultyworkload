<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireFaculty();

$workload_id = $_GET['id'] ?? 0;
$faculty_id = $_SESSION['user_id'];

// Get workload with faculty information (ensure faculty can only access their own workload)
$workload_query = "
    SELECT w.*, u.name as faculty_name, u.faculty_rank, u.eligibility, 
           u.bachelor_degree, u.master_degree, u.doctorate_degree, 
           u.scholarship, u.length_of_service, u.photo
    FROM workloads w 
    JOIN users u ON w.faculty_id = u.id 
    WHERE w.id = ? AND w.faculty_id = ?
";
$stmt = $conn->prepare($workload_query);
$stmt->bind_param("ii", $workload_id, $faculty_id);
$stmt->execute();
$workload = $stmt->get_result()->fetch_assoc();

if (!$workload) {
    die('Workload not found or access denied');
}

// Get teaching loads
$teaching_loads_query = "SELECT * FROM teaching_loads WHERE workload_id = ? ORDER BY day, time_start";
$stmt = $conn->prepare($teaching_loads_query);
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$teaching_loads = $stmt->get_result();

// Get consultation hours
$consultation_hours_query = "SELECT * FROM consultation_hours WHERE workload_id = ? ORDER BY day, time_start";
$stmt = $conn->prepare($consultation_hours_query);
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$consultation_hours = $stmt->get_result();

// Get functions
$functions_query = "SELECT * FROM functions WHERE workload_id = ? ORDER BY type, description";
$stmt = $conn->prepare($functions_query);
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$functions = $stmt->get_result();

// Calculate totals
$total_units = 0;
$total_students = 0;
$teaching_hours = 0;
$consultation_total_hours = 0;
$admin_hours = 0;
$research_hours = 0;

$teaching_loads->data_seek(0);
while ($load = $teaching_loads->fetch_assoc()) {
    $total_units += $load['units'];
    $total_students += $load['students'];
    $teaching_hours += $load['units'];
}

$consultation_hours->data_seek(0);
while ($consultation = $consultation_hours->fetch_assoc()) {
    $start = strtotime($consultation['time_start']);
    $end = strtotime($consultation['time_end']);
    $consultation_total_hours += ($end - $start) / 3600;
}

$functions->data_seek(0);
while ($function = $functions->fetch_assoc()) {
    if ($function['type'] === 'admin') {
        $admin_hours += $function['hours'];
    } else {
        $research_hours += $function['hours'];
    }
}

$total_contact_hours = $teaching_hours + $consultation_total_hours + $research_hours + $admin_hours;

$page_title = 'Print Workload - ' . $workload['faculty_name'];
$hide_navigation = true;
$hide_sidebar = true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.1;
        }
        
        .workload-container {
            max-width: 8.5in;
            margin: 0 auto;
            padding: 15px;
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            position: relative;
            min-height: 90px;
            padding-top: 5px;
        }
        
        .college-logo {
            position: absolute;
            left: 20px;
            top: 10px;
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        
        .faculty-photo {
            position: absolute;
            right: 20px;
            top: 10px;
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 1px solid #000;
        }
        
        .college-info {
            font-weight: bold;
            margin-bottom: 3px;
            padding-top: 5px;
        }
        
        .college-name {
            font-size: 12px;
            color: #2c5aa0;
        }
        
        .college-address {
            font-size: 9px;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
            margin: 5px 0;
        }
        
        .semester-info {
            font-size: 10px;
            font-weight: bold;
        }
        
        .program-title {
            font-size: 11px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .faculty-info-section {
            margin: 8px 0;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 1px;
            align-items: center;
        }
        
        .info-label {
            font-weight: bold;
            width: 120px;
            flex-shrink: 0;
            font-size: 9px;
        }
        
        .info-value {
            border-bottom: 1px solid #000;
            flex-grow: 1;
            padding: 1px 3px;
            min-height: 16px;
            font-size: 9px;
        }
        
        .length-service {
            width: 100px;
            margin-left: 15px;
        }
        
        .teaching-schedule {
            margin: 8px 0;
        }
        
        .section-title {
            background-color: #e9ecef;
            padding: 4px;
            font-weight: bold;
            border: 1px solid #000;
            text-align: center;
            font-size: 10px;
        }
        
        .table {
            font-size: 8px;
            margin-bottom: 0;
        }
        
        .table th,
        .table td {
            border: 1px solid #000 !important;
            padding: 2px !important;
            text-align: center;
            vertical-align: middle;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 7px;
        }
        
        .consultation-section {
            margin: 8px 0;
        }
        
        .summary-section {
            margin: 8px 0;
            float: right;
            width: 250px;
        }
        
        .summary-table {
            font-size: 8px;
        }
        
        .summary-table th,
        .summary-table td {
            border: 1px solid #000 !important;
            padding: 2px !important;
        }
        
        .approval-section {
            margin-top: 15px;
            clear: both;
        }
        
        .approval-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .approval-box {
            text-align: center;
            width: 30%;
        }
        
        .approval-title {
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 9px;
        }
        
        .approval-name {
            border-bottom: 1px solid #000;
            padding: 3px;
            margin-bottom: 3px;
            font-weight: bold;
            font-size: 8px;
            min-height: 15px;
        }
        
        .approval-position {
            font-style: italic;
            font-size: 7px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 0;
                font-size: 9px;
                line-height: 1.0;
            }
            
            .workload-container {
                max-width: none;
                margin: 0;
                padding: 10px;
                page-break-inside: avoid;
            }
            
            .header-section {
                margin-bottom: 8px;
                padding-bottom: 5px;
            }
            
            .faculty-info-section {
                margin: 5px 0;
            }
            
            .info-row {
                margin-bottom: 1px;
            }
            
            .teaching-schedule {
                margin: 5px 0;
            }
            
            .consultation-section {
                margin: 5px 0;
            }
            
            .summary-section {
                margin: 5px 0;
                width: 220px;
            }
            
            .approval-section {
                margin-top: 10px;
            }
            
            .approval-row {
                margin-bottom: 20px;
            }
            
            .approval-title {
                margin-bottom: 15px;
            }
            
            .table th,
            .table td {
                padding: 1px !important;
                font-size: 7px;
            }
            
            .summary-table th,
            .summary-table td {
                padding: 1px !important;
                font-size: 7px;
            }
            
            /* Force single page */
            html, body {
                height: auto !important;
            }
            
            .workload-container {
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }
        }
    </style>
</head>
<body>
    <div class="workload-container">
        <!-- Print Button (hidden when printing) -->
        <div class="text-center mb-3 no-print">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print Workload
            </button>
            <a href="my-workload.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Workload
            </a>
        </div>
        
        <!-- Header Section -->
        <div class="header-section">
            <img src="../asc no bg.png" alt="College Logo" class="college-logo">
            <?php if (!empty($workload['photo'])): ?>
                <img src="../uploads/faculty/<?php echo $workload['photo']; ?>" alt="Faculty Photo" class="faculty-photo">
            <?php endif; ?>
            <div class="college-info">
                <div class="college-name">Republic of the Philippines</div>
                <div class="college-name">APAYAO STATE COLLEGE</div>
                <div class="college-address">San Isidro Sur, Luna, Apayao 3813 Philippines</div>
            </div>
            
            <div class="document-title">INDIVIDUAL FACULTY WORKLOAD</div>
            <div class="semester-info"><?php echo strtoupper($workload['semester'] . ', ' . $workload['school_year']); ?></div>
            
            <?php if ($workload['program']): ?>
                <div class="program-title"><?php echo strtoupper($workload['program']); ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Faculty Information -->
        <div class="faculty-info-section">
            <div class="info-row">
                <div class="info-label">Name:</div>
                <div class="info-value"><?php echo strtoupper($workload['faculty_name']); ?></div>
                <div class="info-label length-service">Length of Service (ASC):</div>
                <div class="info-value" style="width: 100px;"><?php echo $workload['length_of_service'] ?? ''; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Faculty Rank:</div>
                <div class="info-value"><?php echo $workload['faculty_rank'] ?? ''; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Eligibility:</div>
                <div class="info-value"><?php echo $workload['eligibility'] ?? ''; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Bachelor's Degree:</div>
                <div class="info-value"><?php echo $workload['bachelor_degree'] ?? ''; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Master's Degree:</div>
                <div class="info-value"><?php echo $workload['master_degree'] ?? ''; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Doctorate Degree:</div>
                <div class="info-value"><?php echo $workload['doctorate_degree'] ?? ''; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Scholarship:</div>
                <div class="info-value"><?php echo $workload['scholarship'] ?? ''; ?></div>
            </div>
        </div>
        
        <!-- Teaching Schedule -->
        <div class="teaching-schedule">
            <div class="section-title">A. TEACHING SCHEDULE</div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th rowspan="2">Course<br>Code</th>
                        <th rowspan="2">Course Title</th>
                        <th colspan="4">Days/Time/Room/Course/Year/Section</th>
                        <th rowspan="2">Unit/s</th>
                        <th colspan="2">Class Type</th>
                        <th rowspan="2">Contact<br>Hours</th>
                        <th rowspan="2">No. of<br>Students</th>
                        <th rowspan="2">ETL</th>
                    </tr>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Section</th>
                        <th>Lec</th>
                        <th>Lab</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $teaching_loads->data_seek(0);
                    if ($teaching_loads->num_rows > 0):
                        while ($load = $teaching_loads->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?php echo $load['course_code']; ?></td>
                            <td style="text-align: left;"><?php echo $load['course_title']; ?></td>
                            <td><?php echo $load['day']; ?></td>
                            <td><?php echo date('H:i', strtotime($load['time_start'])) . '-' . date('H:i', strtotime($load['time_end'])); ?></td>
                            <td><?php echo $load['room']; ?></td>
                            <td><?php echo $load['section']; ?></td>
                            <td><?php echo $load['units']; ?></td>
                            <td><?php echo $load['class_type'] === 'Lec' ? $load['units'] : '0'; ?></td>
                            <td><?php echo $load['class_type'] === 'Lab' ? $load['units'] : '0'; ?></td>
                            <td><?php echo $load['units']; ?></td>
                            <td><?php echo $load['students']; ?></td>
                            <td><?php echo $load['units']; ?></td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 20px;">No teaching loads assigned</td>
                        </tr>
                    <?php endif; ?>
                    
                    <!-- Totals Row -->
                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                        <td colspan="6">Actual Teaching Load:</td>
                        <td><?php echo number_format($teaching_hours, 2); ?></td>
                        <td colspan="2">Equivalent Excess Number of Preparation:</td>
                        <td><?php echo number_format($teaching_hours, 2); ?></td>
                        <td><?php echo $total_students; ?></td>
                        <td><?php echo number_format($teaching_hours, 2); ?></td>
                    </tr>
                    <tr style="background-color: #f8f9fa;">
                        <td colspan="9"></td>
                        <td><strong>Total Teaching Load:</strong></td>
                        <td colspan="2"><strong><?php echo number_format($teaching_hours, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Consultation Schedule -->
            <div class="consultation-section">
                <div style="background-color: #e9ecef; padding: 8px; font-weight: bold; border: 1px solid #000; margin-top: 15px;">
                    Consultation Schedule:
                </div>
                
                <table class="table" style="width: 50%;">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Room</th>
                            <th>Number of Preparation:</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $consultation_hours->data_seek(0);
                        if ($consultation_hours->num_rows > 0):
                            while ($consultation = $consultation_hours->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $consultation['day']; ?></td>
                                <td><?php echo date('H:i', strtotime($consultation['time_start'])) . '-' . date('H:i', strtotime($consultation['time_end'])); ?></td>
                                <td><?php echo $consultation['room']; ?></td>
                                <td>1</td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No consultation hours assigned</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Research and Extension -->
        <div class="section-title">B. RESEARCH AND EXTENSION</div>
        <div style="border: 1px solid #000; padding: 10px; min-height: 50px; margin-bottom: 15px;">
            <?php 
            $functions->data_seek(0);
            $research_functions = [];
            while ($function = $functions->fetch_assoc()) {
                if ($function['type'] === 'research') {
                    $research_functions[] = $function['description'] . ' (' . $function['hours'] . ' hours)';
                }
            }
            echo implode('<br>', $research_functions);
            ?>
        </div>
        <div style="text-align: right; margin-bottom: 15px;">
            <strong>ETL for Research and Extension: <?php echo number_format($research_hours, 2); ?></strong>
        </div>
        
        <!-- Administrative Function -->
        <div class="section-title">C. ADMINISTRATIVE FUNCTION</div>
        <div style="margin-bottom: 15px;">
            <div class="info-row">
                <div class="info-label">Designation/s:</div>
                <div class="info-value">
                    <?php 
                    $functions->data_seek(0);
                    $admin_functions = [];
                    while ($function = $functions->fetch_assoc()) {
                        if ($function['type'] === 'admin') {
                            $admin_functions[] = $function['description'];
                        }
                    }
                    echo implode(', ', $admin_functions);
                    ?>
                </div>
            </div>
        </div>
        <div style="text-align: right; margin-bottom: 15px;">
            <strong>ETL: <?php echo number_format($admin_hours, 2); ?></strong><br>
            <strong>ETL for Administrative Function: <?php echo number_format($admin_hours, 2); ?></strong>
        </div>
        
        <!-- Summary Section -->
        <div class="summary-section">
            <table class="summary-table table">
                <thead>
                    <tr>
                        <th colspan="2" style="background-color: #e9ecef;">SUMMARY OF CONTACT HOURS/WEEK:</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: left;">Teaching:</td>
                        <td><?php echo number_format($teaching_hours); ?></td>
                    </tr>
                    <tr>
                        <td style="text-align: left;">Consultation:</td>
                        <td><?php echo number_format($consultation_total_hours); ?></td>
                    </tr>
                    <tr>
                        <td style="text-align: left;">Research and Extension:</td>
                        <td><?php echo number_format($research_hours); ?></td>
                    </tr>
                    <tr>
                        <td style="text-align: left;">Administrative Function:</td>
                        <td><?php echo number_format($admin_hours); ?></td>
                    </tr>
                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                        <td style="text-align: left;">Total Contact Hours:</td>
                        <td><?php echo number_format($total_contact_hours); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div style="margin-top: 15px;">
                <div style="border: 1px solid #000; padding: 5px; text-align: center; font-weight: bold;">
                    Total ETL: <?php echo number_format($teaching_hours + $research_hours + $admin_hours, 2); ?><br>
                    OVERLOAD: <?php echo number_format(max(0, ($teaching_hours + $research_hours + $admin_hours) - 21), 2); ?>
                </div>
            </div>
        </div>
        
        <!-- Approval Section -->
        <div class="approval-section">
            <div class="approval-row">
                <div class="approval-box">
                    <div class="approval-title">Prepared by:</div>
                    <div class="approval-name"><?php echo strtoupper($workload['prepared_by'] ?? ''); ?></div>
                    <div class="approval-position"><?php echo $workload['prepared_by_title'] ?? ''; ?></div>
                </div>
                
                <div class="approval-box">
                    <div class="approval-title">Reviewed by:</div>
                    <div class="approval-name"><?php echo strtoupper($workload['reviewed_by'] ?? ''); ?></div>
                    <div class="approval-position"><?php echo $workload['reviewed_by_title'] ?? ''; ?></div>
                </div>
                
                <div class="approval-box">
                    <div class="approval-title">Approved by:</div>
                    <div class="approval-name"><?php echo strtoupper($workload['approved_by'] ?? ''); ?></div>
                    <div class="approval-position"><?php echo $workload['approved_by_title'] ?? ''; ?></div>
                </div>
            </div>
            
            <div style="text-align: right; margin-top: 40px;">
                <div style="display: inline-block; text-align: center;">
                    <div style="border: 1px solid #000; width: 200px; height: 60px; margin-bottom: 5px;"></div>
                    <div><strong>Conformed by:</strong></div>
                    <div style="border-bottom: 1px solid #000; padding: 5px; margin: 10px 0; font-weight: bold;">
                        <?php echo strtoupper($workload['faculty_name']); ?>
                    </div>
                    <div style="font-style: italic;">Faculty</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</body>
</html> 
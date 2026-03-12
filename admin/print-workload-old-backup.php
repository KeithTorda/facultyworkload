<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$workload_id = $_GET['id'] ?? 0;

// Get workload with faculty information
$workload_query = "
    SELECT w.*, u.name as faculty_name, u.faculty_rank, u.eligibility, 
           u.bachelor_degree, u.master_degree, u.doctorate_degree, 
           u.scholarship, u.length_of_service, u.photo
    FROM workloads w 
    JOIN users u ON w.faculty_id = u.id 
    WHERE w.id = ?
";
$stmt = $conn->prepare($workload_query);
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$workload = $stmt->get_result()->fetch_assoc();

if (!$workload) {
    die('Workload not found');
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
    
    <!-- html2pdf.js for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
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
            margin-top: 20px;
            clear: both;
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
                margin-top: 20px;
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
            <button onclick="downloadPDF()" class="btn btn-success">
                <i class="bi bi-file-pdf"></i> Save as PDF
            </button>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print Workload
            </button>
            <a href="view-workloads.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
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
        <div style="margin-bottom: 5px; min-height: 40px;">
            <?php 
            $functions->data_seek(0);
            $research_functions = [];
            while ($function = $functions->fetch_assoc()) {
                if ($function['type'] === 'research') {
                    $research_functions[] = $function['description'];
                }
            }
            echo !empty($research_functions) ? implode('<br>', $research_functions) : '&nbsp;';
            ?>
        </div>
        <div style="text-align: right; margin-bottom: 15px; padding-right: 150px;">
            <strong>ETL for Research and Extension: <span style="margin-left: 20px;"><?php echo number_format($research_hours, 2); ?></span></strong>
        </div>
        
        <!-- Administrative Function -->
        <div class="section-title">C. ADMINISTRATIVE FUNCTION</div>
        <div style="margin-bottom: 5px;">
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
                    echo !empty($admin_functions) ? implode(', ', $admin_functions) : '';
                    ?>
                </div>
            </div>
        </div>
        <div style="text-align: right; margin-bottom: 15px; padding-right: 150px;">
            <table style="display: inline-table; font-size: 9px;" cellspacing="0">
                <tr>
                    <td style="text-align: right; padding-right: 20px;"><strong>ETL:</strong></td>
                    <td style="border-bottom: 1px solid #000; min-width: 50px; text-align: center;"><?php echo number_format($admin_hours, 2); ?></td>
                </tr>
            </table><br>
            <strong>ETL for Administrative Function: <span style="margin-left: 20px;"><?php echo number_format($admin_hours, 2); ?></span></strong>
        </div>
        
        <!-- Summary Section -->
        <div class="summary-section">
            <div style="font-weight: bold; font-size: 9px; margin-bottom: 5px; text-decoration: underline;">
                SUMMARY OF CONTACT HOURS/WEEK:
            </div>
            <table style="width: 100%; font-size: 9px; margin-bottom: 10px;" cellspacing="0">
                <tr>
                    <td style="text-align: right; padding: 2px;">Teaching:</td>
                    <td style="width: 50px; text-align: right; padding: 2px;"><?php echo number_format($teaching_hours); ?></td>
                </tr>
                <tr>
                    <td style="text-align: right; padding: 2px;">Consultation:</td>
                    <td style="text-align: right; padding: 2px;"><?php echo number_format($consultation_total_hours); ?></td>
                </tr>
                <tr>
                    <td style="text-align: right; padding: 2px;">Research and Extension:</td>
                    <td style="text-align: right; padding: 2px;"><?php echo number_format($research_hours); ?></td>
                </tr>
                <tr>
                    <td style="text-align: right; padding: 2px;">Administrative Function:</td>
                    <td style="text-align: right; padding: 2px;"><?php echo number_format($admin_hours); ?></td>
                </tr>
                <tr style="border-top: 1px solid #000;">
                    <td style="text-align: right; padding: 2px; font-weight: bold;">Total Contact Hours:</td>
                    <td style="text-align: right; padding: 2px; font-weight: bold;"><?php echo number_format($total_contact_hours); ?></td>
                </tr>
            </table>
            
            <div style="margin-top: 5px; text-align: right;">
                <div style="display: inline-block; text-align: right;">
                    <div style="margin-bottom: 2px;">
                        <strong>Total ETL: <span style="margin-left: 20px;"><?php echo number_format($teaching_hours + $research_hours + $admin_hours, 2); ?></span></strong>
                    </div>
                    <div>
                        <strong>OVERLOAD: <span style="margin-left: 20px;"><?php echo number_format(max(0, ($teaching_hours + $research_hours + $admin_hours) - 21), 2); ?></span></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Approval Section -->
        <div class="approval-section" style="margin-top: 30px;">
            <table style="width: 100%; font-size: 9px; margin-bottom: 10px;" cellspacing="0">
                <tr>
                    <td style="width: 33%; vertical-align: top; padding-right: 10px;">
                        <div style="margin-bottom: 5px;"><strong>Prepared by:</strong></div>
                        <div style="margin-top: 40px; margin-bottom: 5px;">
                            <div style="border-bottom: 1px solid #000; text-align: center; padding: 3px; font-weight: bold;">
                                <?php echo strtoupper($workload['prepared_by'] ?? ''); ?>
                            </div>
                            <div style="text-align: center; font-style: italic; margin-top: 2px;">
                                <?php echo $workload['prepared_by_title'] ?? ''; ?>
                            </div>
                        </div>
                    </td>
                    <td style="width: 33%; vertical-align: top; padding: 0 10px;">
                        <div style="margin-bottom: 5px;"><strong>Reviewed by:</strong></div>
                        <div style="margin-top: 40px; margin-bottom: 5px;">
                            <div style="border-bottom: 1px solid #000; text-align: center; padding: 3px; font-weight: bold;">
                                <?php echo strtoupper($workload['reviewed_by'] ?? ''); ?>
                            </div>
                            <div style="text-align: center; font-style: italic; margin-top: 2px;">
                                <?php echo $workload['reviewed_by_title'] ?? ''; ?>
                            </div>
                        </div>
                    </td>
                    <td style="width: 34%; vertical-align: top; padding-left: 10px;">
                        <div style="margin-bottom: 5px;"><strong>Approved by:</strong></div>
                        <div style="margin-top: 40px; margin-bottom: 5px;">
                            <div style="border-bottom: 1px solid #000; text-align: center; padding: 3px; font-weight: bold;">
                                <?php echo strtoupper($workload['approved_by'] ?? ''); ?>
                            </div>
                            <div style="text-align: center; font-style: italic; margin-top: 2px;">
                                <?php echo $workload['approved_by_title'] ?? ''; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <div style="text-align: right; margin-top: 30px;">
                <div style="display: inline-block; text-align: center; width: 250px;">
                    <div style="margin-bottom: 40px;">&nbsp;</div>
                    <div><strong>Conformed by:</strong></div>
                    <div style="border-bottom: 1px solid #000; padding: 3px; margin: 5px 0; font-weight: bold;">
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
    
    <script>
        function downloadPDF() {
            // Get the element to convert
            const element = document.querySelector('.workload-container');
            
            // Clone the element
            const clone = element.cloneNode(true);
            
            // Remove no-print elements from clone
            const noPrintElements = clone.querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.remove());
            
            // PDF options
            const opt = {
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: 'Workload_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $workload['faculty_name']); ?>_<?php echo $workload['semester'] . '_' . $workload['school_year']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    letterRendering: true
                },
                jsPDF: { 
                    unit: 'in', 
                    format: 'letter', 
                    orientation: 'portrait' 
                },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };
            
            // Generate PDF
            html2pdf().set(opt).from(clone).save();
        }
    </script>
</body>
</html> 
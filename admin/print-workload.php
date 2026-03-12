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
$total_etl = $teaching_hours + $research_hours + $admin_hours;
$overload = max(0, $total_etl - 21);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Faculty Workload - <?php echo $workload['faculty_name']; ?></title>
    <!-- Include html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            /* Hide date/time when printing */
            @page { margin: 0.4in 0.5in; size: A4; }
            body::after { display: none !important; }
        }
        @page {
            size: A4;
            margin: 0.4in 0.5in;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.2;
            color: #000;
            margin: 0;
            padding: 0;
        }
        
        * {
            box-sizing: border-box;
        }
        
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 8px;
            position: relative;
            min-height: 85px;
        }
        
        .logo {
            position: absolute;
            left: 0;
            top: 0;
            width: 70px;
            height: 70px;
        }
        
        .faculty-photo {
            position: absolute;
            right: 0;
            top: 0;
            width: 70px;
            height: 90px;
            border: 1px solid #000;
            object-fit: cover;
        }
        
        .header-text {
            padding: 0 75px;
        }
        
        .republic {
            font-size: 8pt;
            color: #4472C4;
            margin-bottom: 1px;
        }
        
        .college-name {
            font-size: 10pt;
            font-weight: bold;
            color: #4472C4;
            margin-bottom: 1px;
        }
        
        .address {
            font-size: 7pt;
            margin-bottom: 3px;
        }
        
        .doc-title {
            font-size: 10pt;
            font-weight: bold;
            text-decoration: underline;
            margin: 3px 0;
        }
        
        .semester {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        
        .program {
            font-size: 9pt;
            font-weight: bold;
            text-decoration: underline;
            margin-top: 2px;
        }
        
        /* Faculty Info */
        .faculty-info {
            margin: 8px 0;
        }
        
        .info-line {
            margin-bottom: 2px;
            font-size: 8pt;
        }
        
        .info-line strong {
            display: inline-block;
            width: 130px;
        }
        
        .info-line span {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 380px;
            padding: 1px 5px;
        }
        
        .info-line-split {
            display: flex;
            margin-bottom: 2px;
            font-size: 8pt;
        }
        
        .info-line-split strong {
            width: 130px;
        }
        
        .info-line-split span {
            border-bottom: 1px solid #000;
            flex: 1;
            padding: 1px 5px;
        }
        
        .info-line-split .service {
            width: 200px;
            margin-left: 10px;
            display: flex;
        }
        
        .info-line-split .service strong {
            width: 140px;
            flex-shrink: 0;
        }
        
        .info-line-split .service span {
            min-width: 60px;
            flex: 1;
        }
        
        /* Teaching Schedule */
        .section-header {
            background-color: #D9E1F2;
            border: 1px solid #000;
            padding: 3px;
            font-weight: bold;
            text-align: center;
            margin-top: 6px;
            margin-bottom: 3px;
            font-size: 9pt;
        }
        
        /* Plain section title (no box) */
        .section-title-plain {
            font-weight: bold;
            text-align: center;
            margin-top: 6px;
            margin-bottom: 3px;
            font-size: 9pt;
        }
        
        /* Line with text on both sides */
        .dual-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            border-bottom: 1px solid #000;
        }
        
        /* Right aligned text */
        .text-right {
            text-align: right;
        }
        
        table.schedule {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
            margin-bottom: 5px;
        }
        
        table.schedule th,
        table.schedule td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
        }
        
        table.schedule th {
            background-color: #F2F2F2;
            font-weight: bold;
            font-size: 6pt;
        }
        
        table.schedule td.left {
            text-align: left;
            padding-left: 3px;
        }
        
        table.schedule tr.totals {
            background-color: #F2F2F2;
            font-weight: bold;
            font-size: 6.5pt;
        }
        
        /* Consultation */
        .consultation-header {
            background-color: #E7E6E6;
            padding: 3px;
            font-weight: bold;
            margin-top: 5px;
            margin-bottom: 3px;
            font-size: 8pt;
        }
        
        table.consultation {
            width: 60%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-bottom: 5px;
        }
        
        table.consultation th,
        table.consultation td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
            font-size: 6.5pt;
        }
        
        /* Summary Box */
        .summary-box {
            width: 250px;
            float: right;
            margin-top: 0;
            margin-bottom: 30px;
            margin-left: 20px;
            font-size: 8pt;
        }
        
        .summary-title {
            font-weight: bold;
            text-decoration: underline;
            font-size: 8pt;
            margin-bottom: 3px;
        }
        
        .summary-line {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            font-size: 8pt;
        }
        
        .summary-line.total {
            border-top: 1px solid #000;
            font-weight: bold;
            margin-top: 2px;
            padding-top: 3px;
        }
        
        .etl-box {
            text-align: right;
            margin-top: 5px;
            font-weight: bold;
            font-size: 8pt;
        }
        
        .content-wrapper {
            position: relative;
            min-height: 200px;
        }
        
        .left-content {
            width: 100%;
        }
        
        /* Signatures */
        .signatures {
            clear: both;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        
        .sig-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .sig-col {
            width: 30%;
            text-align: center;
        }
        
        .sig-label {
            font-size: 8pt;
            margin-bottom: 35px;
        }
        
        .sig-name {
            border-bottom: 1px solid #000;
            padding: 3px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
            font-size: 8pt;
        }
        
        .sig-position {
            font-style: italic;
            font-size: 7pt;
        }
        
        .conformed {
            text-align: right;
            margin-top: 15px;
        }
        
        .conformed-box {
            display: inline-block;
            width: 220px;
            text-align: center;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            html, body {
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100%;
            }
            
            body {
                font-size: 8pt;
            }
            
            .header {
                margin-bottom: 5px;
                min-height: 75px;
            }
            
            .logo, .faculty-photo {
                width: 65px;
                height: 65px;
            }
            
            .faculty-info {
                margin: 5px 0;
            }
            
            .info-line {
                margin-bottom: 1px;
                font-size: 7pt;
            }
            
            .info-line-split {
                margin-bottom: 1px;
                font-size: 7pt;
            }
            
            .section-header {
                margin-top: 4px;
                margin-bottom: 2px;
                padding: 2px;
                font-size: 8pt;
            }
            
            table.schedule {
                font-size: 6pt;
                margin-bottom: 3px;
            }
            
            table.schedule th {
                padding: 1px;
                font-size: 5.5pt;
            }
            
            table.schedule td {
                padding: 1px;
            }
            
            table.consultation {
                font-size: 6.5pt;
                margin-bottom: 3px;
            }
            
            .consultation-header {
                margin-top: 3px;
                margin-bottom: 2px;
                font-size: 7pt;
            }
            
            .summary-box {
                margin-top: 0;
                width: 250px;
                font-size: 7pt;
            }
            
            .summary-title {
                font-size: 7pt;
            }
            
            .summary-line {
                font-size: 7pt;
                padding: 0.5px 0;
            }
            
            .etl-box {
                margin-top: 3px;
                font-size: 7pt;
            }
            
            .signatures {
                margin-top: 10px;
            }
            
            .sig-row {
                margin-bottom: 20px;
            }
            
            .sig-label {
                font-size: 7pt;
                margin-bottom: 25px;
            }
            
            .sig-name {
                font-size: 7pt;
                padding: 2px;
            }
            
            .sig-position {
                font-size: 6pt;
            }
            
            .conformed {
                margin-top: 10px;
            }
            
            .conformed-box {
                width: 200px;
            }
            
            /* Ensure single page */
            body {
                max-height: 100vh;
                overflow: hidden;
            }
            
            .signatures {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Print Buttons -->
    <div class="no-print" style="text-align: center; margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
        <button onclick="window.print()" class="btn btn-primary" style="margin-right: 10px; padding: 5px 15px;">🖨️ Print (Browser)</button>
        <button onclick="generatePDF()" class="btn btn-success" style="margin-right: 10px; padding: 5px 15px;">📄 Print to A4 PDF</button>
        <a href="view-workloads.php" class="btn btn-secondary" style="padding: 5px 15px;">← Back</a>
        </div>
        
    <!-- Header -->
    <div class="header">
        <img src="../asc no bg.png" alt="Logo" class="logo">
            <?php if (!empty($workload['photo'])): ?>
            <img src="../uploads/faculty/<?php echo $workload['photo']; ?>" alt="Photo" class="faculty-photo">
            <?php endif; ?>
        
        <div class="header-text">
            <div class="republic">Republic of the Philippines</div>
                <div class="college-name">APAYAO STATE COLLEGE</div>
            <div class="address">San Isidro Sur, Luna, Apayao 3813 Philippines</div>
            <div class="doc-title">INDIVIDUAL FACULTY WORKLOAD</div>
            <div class="program" style="margin: 3px 0; font-weight: bold;">BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY</div>
            <div class="semester"><?php echo strtoupper($workload['semester'] . ', ' . $workload['school_year']); ?></div>
            <?php if ($workload['program'] && $workload['program'] != 'Bachelor of Science in Information Technology'): ?>
                <div class="program"><?php echo strtoupper($workload['program']); ?></div>
            <?php endif; ?>
        </div>
        </div>
        
        <!-- Faculty Information -->
    <div class="faculty-info">
        <div class="info-line-split">
            <strong>Name:</strong>
            <span><?php echo strtoupper($workload['faculty_name']); ?></span>
            <div class="service">
                <strong>Length of Service (ASC):</strong>
                <span><?php echo $workload['length_of_service'] ?? ''; ?></span>
            </div>
            </div>
        <div class="info-line">
            <strong>Faculty Rank:</strong>
            <span><?php echo $workload['faculty_rank'] ?? ''; ?></span>
            </div>
        <div class="info-line">
            <strong>Eligibility:</strong>
            <span><?php echo $workload['eligibility'] ?? ''; ?></span>
            </div>
        <div class="info-line">
            <strong>Bachelor's Degree:</strong>
            <span><?php echo $workload['bachelor_degree'] ?? ''; ?></span>
            </div>
        <div class="info-line">
            <strong>Master's Degree:</strong>
            <span><?php echo $workload['master_degree'] ?? ''; ?></span>
            </div>
        <div class="info-line">
            <strong>Doctorate Degree:</strong>
            <span><?php echo $workload['doctorate_degree'] ?? ''; ?></span>
            </div>
        <div class="info-line">
            <strong>Scholarship:</strong>
            <span><?php echo $workload['scholarship'] ?? ''; ?></span>
            </div>
        </div>
        
    <!-- A. TEACHING SCHEDULE -->
    <div class="section-header">A. TEACHING SCHEDULE</div>
    <table class="schedule">
                <thead>
                    <tr>
                        <th>Course<br>Code</th>
                        <th>Course Title</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Section</th>
                        <th>Lecture Units</th>
                        <th>Laboratory Units</th>
                        <th>Contact<br>Hours</th>
                        <th>No. of<br>Students</th>
                        <th>ETL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Check if lecture_units and lab_units columns exist
                    $check_units_columns = $conn->query("SHOW COLUMNS FROM teaching_loads LIKE 'lecture_units'");
                    $has_separate_units = $check_units_columns->num_rows > 0;
                    
                    $teaching_loads->data_seek(0);
                    if ($teaching_loads->num_rows > 0):
                        while ($load = $teaching_loads->fetch_assoc()): 
                            if ($has_separate_units) {
                                $lec_units = isset($load['lecture_units']) ? (float)$load['lecture_units'] : 0;
                                $lab_units = isset($load['lab_units']) ? (float)$load['lab_units'] : 0;
                            } else {
                                // Fallback to old units column
                                $lec_units = $load['class_type'] === 'Lec' ? (float)$load['units'] : 0;
                                $lab_units = $load['class_type'] === 'Lab' ? (float)$load['units'] : 0;
                            }
                            $contact_hours = $lec_units + $lab_units;
                    ?>
                        <tr>
                            <td><?php echo $load['course_code']; ?></td>
                    <td class="left"><?php echo $load['course_title']; ?></td>
                            <td><?php echo $load['day']; ?></td>
                    <td><?php echo date('g:i A', strtotime($load['time_start'])) . ' - ' . date('g:i A', strtotime($load['time_end'])); ?></td>
                            <td><?php echo $load['room']; ?></td>
                            <td><?php echo $load['section']; ?></td>
                            <td><?php echo $lec_units; ?></td>
                            <td><?php echo $lab_units; ?></td>
                            <td><?php echo $contact_hours; ?></td>
                            <td><?php echo $load['students']; ?></td>
                            <td><?php echo $contact_hours; ?></td>
                        </tr>
                    <?php 
                        endwhile;
            endif; 
                    ?>
                    
            <tr class="totals">
                <td colspan="6" class="left">Actual Teaching Load:</td>
                        <td><?php echo number_format($teaching_hours, 2); ?></td>
                        <td colspan="2">Equivalent Excess Number of Preparation:</td>
                        <td><?php echo number_format($teaching_hours, 2); ?></td>
                        <td><?php echo $total_students; ?></td>
                        <td><?php echo number_format($teaching_hours, 2); ?></td>
                    </tr>
            <tr class="totals">
                        <td colspan="9"></td>
                <td>Total Teaching Load:</td>
                <td colspan="2"><?php echo number_format($teaching_hours, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Consultation Schedule -->
    <div class="consultation-header">Consultation Schedule:</div>
    <table class="consultation">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Room</th>
                <th>Number of Preparation</th>
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
                    <td><?php echo date('g:i A', strtotime($consultation['time_start'])) . ' - ' . date('g:i A', strtotime($consultation['time_end'])); ?></td>
                    <td><?php echo $consultation['room'] ?? ''; ?></td>
                                <td>1</td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                    <td colspan="4">No consultation hours assigned</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

        <!-- Content Wrapper for Summary Positioning -->
    <div class="content-wrapper" style="clear: both;">
        <!-- Summary Box (Now at top) -->
        <div class="summary-box">
            <div class="etl-box" style="text-align: left; margin-bottom: 6px;">
                <div><strong>Total ETL:</strong> <?php echo number_format($total_etl, 2); ?></div>
                <div><strong>OVERLOAD:</strong> <?php echo number_format($overload, 2); ?></div>
            </div>
            <div class="summary-title">SUMMARY OF CONTACT HOURS/WEEK:</div>
            <div class="summary-line">
                <span>Teaching:</span>
                <span><?php echo number_format($teaching_hours); ?></span>
            </div>
            <div class="summary-line">
                <span>Consultation:</span>
                <span><?php echo number_format($consultation_total_hours); ?></span>
            </div>
            <div class="summary-line">
                <span>Research and Extension:</span>
                <span><?php echo number_format($research_hours); ?></span>
            </div>
            <div class="summary-line">
                <span>Administrative Function:</span>
                <span><?php echo number_format($admin_hours); ?></span>
            </div>
            <div class="summary-line total">
                <span>Total Contact Hours:</span>
                <span><?php echo number_format($total_contact_hours); ?></span>
            </div>
        </div>
        
        <div class="left-content">
            <!-- B. RESEARCH AND EXTENSION -->
            <div class="dual-line">
                <div><strong>B. RESEARCH AND EXTENSION</strong></div>
                <div class="text-right"><strong>ETL for Research and Extension: <?php echo number_format($research_hours, 2); ?></strong></div>
            </div>
            <div style="min-height: 30px; margin-bottom: 5px; font-size: 8pt;">
            <?php 
            $functions->data_seek(0);
                $research_list = [];
            while ($function = $functions->fetch_assoc()) {
                if ($function['type'] === 'research') {
                        $research_list[] = $function['description'];
                }
            }
                echo !empty($research_list) ? implode('<br>', $research_list) : '&nbsp;';
            ?>
        </div>
        
            <!-- C. ADMINISTRATIVE FUNCTION -->
            <div class="dual-line">
                <div><strong>C. ADMINISTRATIVE FUNCTION</strong></div>
        </div>
            <div style="margin-bottom: 5px;">
                <div class="info-line">
                    <strong>Designation/s:</strong>
                    <span><?php 
                    $functions->data_seek(0);
                        $admin_list = [];
                    while ($function = $functions->fetch_assoc()) {
                        if ($function['type'] === 'admin') {
                                $admin_list[] = $function['description'];
                        }
                    }
                        echo !empty($admin_list) ? implode(', ', $admin_list) : '';
                    ?></span>
                </div>
            </div>
            <div style="margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <div style="display: flex; align-items: center;">
                        <strong style="margin-right: 6px;">ETL:</strong>
                        <span style="border-bottom: 1px solid #000; min-width: 60px; text-align: center; padding: 0 8px;">
                            <?php echo number_format($admin_hours, 2); ?>
                        </span>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <strong>ETL for Administrative Function: <?php echo number_format($admin_hours, 2); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        </div>
        
        
        </div>
        
    <!-- Approval Signatures -->
    <div class="signatures">
        <div class="sig-row">
            <div class="sig-col">
                <div class="sig-label">Prepared by:</div>
                <div class="sig-name"><?php echo $workload['prepared_by'] ?? ''; ?></div>
                <div class="sig-position"><?php echo $workload['prepared_by_title'] ?? ''; ?></div>
                </div>
            <div class="sig-col">
                <div class="sig-label">Reviewed by:</div>
                <div class="sig-name"><?php echo $workload['reviewed_by'] ?? ''; ?></div>
                <div class="sig-position"><?php echo $workload['reviewed_by_title'] ?? ''; ?></div>
                </div>
            <div class="sig-col">
                <div class="sig-label">Approved by:</div>
                <div class="sig-name"><?php echo $workload['approved_by'] ?? ''; ?></div>
                <div class="sig-position"><?php echo $workload['approved_by_title'] ?? ''; ?></div>
                </div>
            </div>
            
        <div class="conformed">
            <div class="conformed-box">
                <div style="margin-bottom: 50px;">&nbsp;</div>
                <div class="sig-label">Conformed by:</div>
                <div class="sig-name"><?php echo strtoupper($workload['faculty_name']); ?></div>
                <div class="sig-position">Faculty</div>
                </div>
            </div>
        </div>
    
    <script>
        // Remove date/time from print
        window.onbeforeprint = function() {
            // Find and remove any date/time elements that might be added by the browser
            var dateElements = document.querySelectorAll('date, time');
            for (var i = 0; i < dateElements.length; i++) {
                dateElements[i].style.display = 'none';
            }
        };
        
        // Generate PDF with proper A4 sizing
        function generatePDF() {
            // Hide print buttons
            document.querySelector('.no-print').style.display = 'none';
            
            // Set options for PDF generation
            var options = {
                filename: 'Faculty_Workload_<?php echo str_replace(" ", "_", $workload["faculty_name"]); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, letterRendering: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all'] }
            };
            
            // Get the element to convert
            var element = document.body;
            
            // Generate PDF
            html2pdf().set(options).from(element).save().then(function() {
                // Show print buttons again after PDF is generated
                setTimeout(function() {
                    document.querySelector('.no-print').style.display = 'block';
                }, 1000);
            });
        }
    </script>
</body>
</html> 


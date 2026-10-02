<?php
/**
 * Refactored payroll functions with prepared statements.
 * Place at: hrgetafee-simple/includes/payroll_functions.php
 *
 * Note: this file expects a function get_employee_info($conn, $employee_id) to exist in includes/functions.php
 * If it doesn't, implement a simple wrapper that returns at least ['salary' => '0', 'date_hired' => 'YYYY-MM-DD'].
 */

function calculate_ams($conn, $employee_id) {
    $employee = function_exists('get_employee_info') ? get_employee_info($conn, $employee_id) : null;
    if (!$employee || empty($employee['date_hired'])) {
        return 0.0;
    }
    $date_hired = $employee['date_hired'];
    $hired = new DateTime($date_hired);
    $current = new DateTime(date('Y-m-d'));
    $interval = $hired->diff($current);
    $years = $interval->y;
    $months = $interval->m;
    $days = $interval->d;
    $ams = $years + ($months / 12) + ($days / 30);
    return round($ams, 1);
}

function get_leave_compensation_factor($conn, $ams) {
    $stmt = $conn->prepare("SELECT leave_earned_amount FROM leave_compensation WHERE ams_value <= ? ORDER BY ams_value DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("d", $ams);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $val = $res->fetch_assoc()['leave_earned_amount'];
            $stmt->close();
            return floatval($val);
        }
        $stmt->close();
    }
    return 1.0;
}

function calculate_basic_deductions($gross_salary) {
    $deductions = [
        'sss' => $gross_salary * 0.0363,
        'philhealth' => $gross_salary * 0.0275,
        'pagibig' => $gross_salary * 0.02,
        'bir' => 0
    ];
    return $deductions;
}

function get_fixed_deductions($conn, $employee_id) {
    $fixed_deductions = [
        'cpf' => 971.75,
        'cbif' => 0.00,
        'mpl' => 0.00
    ];

    $q = "SELECT ed.amount, dt.deduction_code FROM employee_deductions ed
          JOIN deduction_types dt ON ed.deduction_id = dt.deduction_id
          WHERE ed.employee_id = ? AND ed.status = 'active'
          AND dt.deduction_code IN ('CPF','CBIF','MPL')";
    $stmt = $conn->prepare($q);
    if ($stmt) {
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $code = strtolower($row['deduction_code']);
            $fixed_deductions[$code] = floatval($row['amount']);
        }
        $stmt->close();
    }

    return $fixed_deductions;
}

function calculate_enhanced_payroll($conn, $employee_id, $month, $year) {
    $employee = function_exists('get_employee_info') ? get_employee_info($conn, $employee_id) : null;
    $gross_salary = $employee ? floatval($employee['salary']) : 0.0;

    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = date('Y-m-t', strtotime($start_date));

    // Attendance (prepared)
    $attendance = [];
    $stmt = $conn->prepare("SELECT status FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    $absences = 0;
    $lates = 0;
    $present_days = 0;
    foreach ($attendance as $record) {
        $st = $record['status'] ?? '';
        if ($st === 'absent') $absences++;
        elseif ($st === 'late') $lates++;
        elseif ($st === 'present') $present_days++;
    }

    // Approved leaves (prepared)
    $leaves = 0;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(number_of_days), 0) as leave_days FROM leave_requests WHERE employee_id = ? AND status = 'approved' AND start_date <= ? AND end_date >= ?");
    if ($stmt) {
        $stmt->bind_param("iss", $employee_id, $end_date, $start_date);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $leaves = isset($res['leave_days']) ? floatval($res['leave_days']) : 0;
        $stmt->close();
    }

    $ams = calculate_ams($conn, $employee_id);
    $leave_compensation_factor = get_leave_compensation_factor($conn, $ams);

    $basic_deductions = calculate_basic_deductions($gross_salary);
    $fixed_deductions = get_fixed_deductions($conn, $employee_id);

    $absence_deduction = $absences * 500;
    $late_deduction = $lates * 100;

    $subtotal_deductions =
        $basic_deductions['sss'] +
        $basic_deductions['philhealth'] +
        $basic_deductions['pagibig'] +
        $fixed_deductions['cpf'] +
        $fixed_deductions['cbif'] +
        $fixed_deductions['mpl'] +
        $absence_deduction +
        $late_deduction;

    $taxable_income = $gross_salary - $subtotal_deductions;
    $bir = max(0, $taxable_income * 0.12);

    $total_deductions = $subtotal_deductions + $bir;
    $net_salary = max(0, $gross_salary - $total_deductions);

    return [
        'gross_salary' => $gross_salary,
        'ams' => $ams,
        'leave_compensation_factor' => $leave_compensation_factor,
        'absences' => $absences,
        'lates' => $lates,
        'approved_leaves' => $leaves,
        'present_days' => $present_days,
        'sss_deduction' => $basic_deductions['sss'],
        'philhealth_deduction' => $basic_deductions['philhealth'],
        'pagibig_deduction' => $basic_deductions['pagibig'],
        'cpf_deduction' => $fixed_deductions['cpf'],
        'cbif_deduction' => $fixed_deductions['cbif'],
        'mpl_deduction' => $fixed_deductions['mpl'],
        'absence_deduction' => $absence_deduction,
        'late_deduction' => $late_deduction,
        'bir_deduction' => $bir,
        'total_deductions' => $total_deductions,
        'net_salary' => $net_salary
    ];
}

function calculate_leave_earned($conn, $employee_id, $month, $year) {
    $ams = calculate_ams($conn, $employee_id);
    $compensation_factor = get_leave_compensation_factor($conn, $ams);
    $employee = function_exists('get_employee_info') ? get_employee_info($conn, $employee_id) : null;
    $monthly_salary = $employee ? floatval($employee['salary']) : 0.0;
    $leave_earned_days = $compensation_factor;
    $leave_earned_amount = $monthly_salary * $compensation_factor;
    return [
        'ams' => $ams,
        'compensation_factor' => $compensation_factor,
        'leave_earned_days' => $leave_earned_days,
        'leave_earned_amount' => $leave_earned_amount,
        'monthly_salary' => $monthly_salary
    ];
}

function save_payroll_details($conn, $payroll_id, $payroll_data) {
    $details = [
        ['earning', 'Gross Salary', $payroll_data['gross_salary']],
        ['deduction', 'SSS Contribution', $payroll_data['sss_deduction']],
        ['deduction', 'PhilHealth Premium', $payroll_data['philhealth_deduction']],
        ['deduction', 'Pag-IBIG Contribution', $payroll_data['pagibig_deduction']],
        ['deduction', 'CPF (Consolidated Police Fund)', $payroll_data['cpf_deduction']],
        ['deduction', 'CBIF (Comprehensive Benefit Insurance)', $payroll_data['cbif_deduction']],
        ['deduction', 'MPL (Magna Carta for Public Employees)', $payroll_data['mpl_deduction']],
        ['deduction', 'Absence Deduction (' . $payroll_data['absences'] . ' × ₱500)', $payroll_data['absence_deduction']],
        ['deduction', 'Late Deduction (' . $payroll_data['lates'] . ' × ₱100)', $payroll_data['late_deduction']],
        ['deduction', 'BIR Income Tax (12%)', $payroll_data['bir_deduction']],
    ];

    $stmt = $conn->prepare("INSERT INTO payroll_details (payroll_id, detail_type, detail_name, amount) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;

    foreach ($details as $detail) {
        $type = $detail[0];
        $name = $detail[1];
        $amount = $detail[2];
        $stmt->bind_param("issd", $payroll_id, $type, $name, $amount);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
    }
    $stmt->close();
    return true;
}

function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

function get_payroll_details($conn, $payroll_id) {
    $stmt = $conn->prepare("SELECT detail_type, detail_name, amount FROM payroll_details WHERE payroll_id = ? ORDER BY detail_type DESC, detail_name");
    if (!$stmt) return [];
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}
?>
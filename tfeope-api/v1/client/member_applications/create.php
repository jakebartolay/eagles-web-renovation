<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

if (api_post_too_large()) {
    api_error('Upload is too large. Please choose smaller image files.', 413);
}

const MEMBER_APPLICATION_MAX_UPLOAD_BYTES = 5242880;

if (!function_exists('member_application_input')) {
    function member_application_input(array $payload, string|array $keys): string
    {
        foreach ((array) $keys as $key) {
            if (array_key_exists($key, $payload)) {
                return trim((string) $payload[$key]);
            }
        }

        return '';
    }
}

if (!function_exists('member_application_normalize_spaces')) {
    function member_application_normalize_spaces(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}

if (!function_exists('member_application_upper')) {
    function member_application_upper(string $value): string
    {
        return strtoupper(member_application_normalize_spaces($value));
    }
}

if (!function_exists('member_application_bool')) {
    function member_application_bool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('member_application_no')) {
    function member_application_no(): string
    {
        return 'APP' . date('Ymd') . strtoupper(bin2hex(random_bytes(4)));
    }
}

if (!function_exists('member_application_upload_error')) {
    function member_application_upload_error(?array $file, string $label): ?string
    {
        if (!is_array($file)) {
            return $label . ' is required.';
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return $label . ' is required.';
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return api_upload_error_message($errorCode);
        }

        if ((int) ($file['size'] ?? 0) > MEMBER_APPLICATION_MAX_UPLOAD_BYTES) {
            return $label . ' must be 5MB or smaller.';
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, api_image_extensions(), true)) {
            return $label . ' must be an image file.';
        }

        return null;
    }
}

if (!function_exists('member_application_ensure_table')) {
    function member_application_ensure_table(PDO $db): void
    {
        api_execute($db, '
            CREATE TABLE IF NOT EXISTS membership_id_applications (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                application_no VARCHAR(32) NOT NULL UNIQUE,
                last_name VARCHAR(120) NOT NULL,
                first_name VARCHAR(160) NOT NULL,
                address VARCHAR(255) NOT NULL,
                mobile VARCHAR(30) NOT NULL,
                facebook VARCHAR(255) DEFAULT NULL,
                tiktok VARCHAR(120) DEFAULT NULL,
                region VARCHAR(180) NOT NULL,
                governor VARCHAR(180) DEFAULT NULL,
                regional_position VARCHAR(120) NOT NULL,
                club_name VARCHAR(220) NOT NULL,
                club_position VARCHAR(120) NOT NULL,
                id_number VARCHAR(80) NOT NULL,
                payment_status ENUM(\'Paid\', \'Unpaid\') NOT NULL DEFAULT \'Unpaid\',
                emergency_name VARCHAR(160) NOT NULL,
                emergency_contact VARCHAR(30) NOT NULL,
                photo_filename VARCHAR(255) NOT NULL,
                signature_filename VARCHAR(255) NOT NULL,
                certification TEXT NULL,
                status ENUM(\'Pending\', \'Processing\', \'Approved\', \'Rejected\') NOT NULL DEFAULT \'Pending\',
                ip_address VARCHAR(60) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_membership_id_applications_id_number (id_number),
                INDEX idx_membership_id_applications_status (status),
                INDEX idx_membership_id_applications_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }
}

$storedPhoto = null;
$storedSignature = null;

try {
    $db = api_db();
    member_application_ensure_table($db);

    $payload = api_request_data();
    $lastName = member_application_upper(member_application_input($payload, ['lastName', 'last_name']));
    $firstName = member_application_upper(member_application_input($payload, ['firstName', 'first_name']));
    $address = member_application_normalize_spaces(member_application_input($payload, 'address'));
    $mobile = member_application_normalize_spaces(member_application_input($payload, 'mobile'));
    $facebook = member_application_normalize_spaces(member_application_input($payload, 'facebook'));
    $tiktok = member_application_normalize_spaces(member_application_input($payload, 'tiktok'));
    $region = member_application_normalize_spaces(member_application_input($payload, 'region'));
    $governor = member_application_normalize_spaces(member_application_input($payload, 'governor'));
    $regionalPosition = member_application_upper(member_application_input($payload, ['regionalPosition', 'regional_position']));
    $clubName = member_application_normalize_spaces(member_application_input($payload, ['clubName', 'club_name']));
    $clubPosition = member_application_upper(member_application_input($payload, ['clubPosition', 'club_position']));
    $idNumber = member_application_upper(member_application_input($payload, ['idNumber', 'id_number']));
    $emergencyName = member_application_upper(member_application_input($payload, ['emergencyName', 'emergency_name']));
    $emergencyContact = member_application_normalize_spaces(member_application_input($payload, ['emergencyContact', 'emergency_contact']));
    $certification = member_application_normalize_spaces(member_application_input($payload, 'certification'));
    $consent = member_application_bool(member_application_input($payload, 'consent'));

    $paymentInput = strtolower(member_application_input($payload, ['paymentStatus', 'payment_status']));
    $paymentStatus = match ($paymentInput) {
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        default => '',
    };

    $errors = [];
    $required = [
        'lastName' => $lastName,
        'firstName' => $firstName,
        'address' => $address,
        'mobile' => $mobile,
        'region' => $region,
        'regionalPosition' => $regionalPosition,
        'clubName' => $clubName,
        'clubPosition' => $clubPosition,
        'idNumber' => $idNumber,
        'paymentStatus' => $paymentStatus,
        'emergencyName' => $emergencyName,
        'emergencyContact' => $emergencyContact,
    ];

    foreach ($required as $field => $value) {
        if ($value === '') {
            $errors[$field] = 'This field is required.';
        }
    }

    $mobileDigits = preg_replace('/\D+/', '', $mobile) ?? '';
    if ($mobileDigits === '' || strlen($mobileDigits) !== 11 || !str_starts_with($mobileDigits, '09')) {
        $errors['mobile'] = 'Enter a valid 11-digit mobile number starting with 09.';
    }

    $emergencyDigits = preg_replace('/\D+/', '', $emergencyContact) ?? '';
    if ($emergencyDigits === '' || strlen($emergencyDigits) < 7) {
        $errors['emergencyContact'] = 'Enter a valid emergency contact number.';
    }

    if (!$consent) {
        $errors['consent'] = 'Consent is required.';
    }

    $photoUpload = $_FILES['photo'] ?? null;
    $signatureUpload = $_FILES['signature'] ?? null;
    $photoError = member_application_upload_error(is_array($photoUpload) ? $photoUpload : null, '2x2 ID Photo');
    $signatureError = member_application_upload_error(is_array($signatureUpload) ? $signatureUpload : null, 'Signature');

    if ($photoError !== null) {
        $errors['photo'] = $photoError;
    }
    if ($signatureError !== null) {
        $errors['signature'] = $signatureError;
    }

    if ($errors !== []) {
        api_json([
            'success' => false,
            'message' => 'Please review the highlighted fields.',
            'errors' => $errors,
        ], 422);
    }

    $applicationNo = member_application_no();
    $storedPhoto = api_store_uploaded_file_as(
        $photoUpload,
        'member_applications',
        $applicationNo . '-photo',
        api_image_extensions()
    );
    $storedSignature = api_store_uploaded_file_as(
        $signatureUpload,
        'member_applications',
        $applicationNo . '-signature',
        api_image_extensions()
    );

    api_execute($db, '
        INSERT INTO membership_id_applications (
            application_no,
            last_name,
            first_name,
            address,
            mobile,
            facebook,
            tiktok,
            region,
            governor,
            regional_position,
            club_name,
            club_position,
            id_number,
            payment_status,
            emergency_name,
            emergency_contact,
            photo_filename,
            signature_filename,
            certification,
            status,
            ip_address,
            user_agent
        ) VALUES (
            :application_no,
            :last_name,
            :first_name,
            :address,
            :mobile,
            :facebook,
            :tiktok,
            :region,
            :governor,
            :regional_position,
            :club_name,
            :club_position,
            :id_number,
            :payment_status,
            :emergency_name,
            :emergency_contact,
            :photo_filename,
            :signature_filename,
            :certification,
            \'Pending\',
            :ip_address,
            :user_agent
        )
    ', [
        ':application_no' => $applicationNo,
        ':last_name' => $lastName,
        ':first_name' => $firstName,
        ':address' => $address,
        ':mobile' => $mobile,
        ':facebook' => $facebook !== '' ? $facebook : null,
        ':tiktok' => $tiktok !== '' ? $tiktok : null,
        ':region' => $region,
        ':governor' => $governor !== '' ? $governor : null,
        ':regional_position' => $regionalPosition,
        ':club_name' => $clubName,
        ':club_position' => $clubPosition,
        ':id_number' => $idNumber,
        ':payment_status' => $paymentStatus,
        ':emergency_name' => $emergencyName,
        ':emergency_contact' => $emergencyContact,
        ':photo_filename' => (string) ($storedPhoto['filename'] ?? ''),
        ':signature_filename' => (string) ($storedSignature['filename'] ?? ''),
        ':certification' => $certification !== '' ? $certification : null,
        ':ip_address' => api_request_ip(),
        ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    api_json([
        'success' => true,
        'message' => 'Application submitted successfully.',
        'data' => [
            'applicationNo' => $applicationNo,
            'application_no' => $applicationNo,
            'idNumber' => $idNumber,
            'id_number' => $idNumber,
            'status' => 'Pending',
            'photoUrl' => $storedPhoto['url'] ?? null,
            'signatureUrl' => $storedSignature['url'] ?? null,
        ],
    ], 201);
} catch (Throwable $error) {
    if (is_array($storedPhoto) && !empty($storedPhoto['filename'])) {
        api_delete_uploaded_file('member_applications', (string) $storedPhoto['filename']);
    }
    if (is_array($storedSignature) && !empty($storedSignature['filename'])) {
        api_delete_uploaded_file('member_applications', (string) $storedSignature['filename']);
    }

    api_handle_exception(
        $error,
        'Client member application create API error',
        'Unable to submit application right now.'
    );
}

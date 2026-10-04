<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant Data Sheet</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 40px;
            color: #222;
            background-color: #fff;
        }

        h1, h2, h3 {
            color: #0d6efd;
            text-align: center;
        }

        h1 {
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        hr {
            border: none;
            border-top: 2px solid #0d6efd;
            margin: 15px 0;
        }

        p {
            margin: 4px 0;
            line-height: 1.5;
        }

        b {
            color: #333;
        }

        .section {
            margin-top: 25px;
        }

        .section-title {
            text-transform: uppercase;
            text-decoration: underline;
            color: #0d6efd;
            font-weight: bold;
        }

        .id-section {
            text-align: center;
            margin-top: 20px;
        }

        .id-section img {
            width: 260px;
            height: auto;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin: 10px;
        }

        .status {
            font-weight: bold;
            text-transform: capitalize;
        }

        .status.pending {
            color: #856404;
        }

        .status.approved {
            color: #0f5132;
        }

        .status.rejected {
            color: #842029;
        }

        .footer {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-top: 40px;
        }
    </style>
</head>
<body>

    <h1>Tenant Bio Data</h1>
    <hr>

    <div class="section">
        <h3 class="section-title">Personal Details</h3>
        <p><b>Full Name:</b> {{ $tenantApp->full_name }}</p>
        <p><b>Email:</b> {{ $tenantApp->email }}</p>
        <p><b>Contact Number:</b> {{ $tenantApp->contact_number }}</p>
        <p><b>Current Address:</b> {{ $tenantApp->current_address }}</p>
        <p><b>Birthdate:</b> {{ \Carbon\Carbon::parse($tenantApp->birthdate)->format('F d, Y') }}</p>
    </div>

    <hr>

    <div class="section">
        <h3 class="section-title">Application Information</h3>
        @php
            $unitType = $tenantApp->unit_type ?? 'N/A';
            $roomNo = $tenantApp->room_no ?? 'N/A';
            $bedNumber = $tenantApp->bed_number ?? null;

            // Format unit type with room number and bed number (if Bed-Spacer)
            if ($unitType === 'Bed-Spacer' && $bedNumber) {
                $unitTypeDisplay = $unitType . ' - ' . $roomNo . ' - Bed ' . $bedNumber;
            } elseif ($roomNo !== 'N/A') {
                $unitTypeDisplay = $unitType . ' - ' . $roomNo;
            } else {
                $unitTypeDisplay = $unitType;
            }
        @endphp
        <p><b>Unit Type:</b> {{ $unitTypeDisplay }}</p>
        <p><b>Move-In Date:</b> {{ \Carbon\Carbon::parse($tenantApp->move_in_date)->format('F d, Y') }}</p>
        <p><b>Reason for Moving:</b> {{ $tenantApp->reason }}</p>
    </div>

    <hr>

    <div class="section">
        <h3 class="section-title">Employment & Income</h3>
        <p><b>Employment Status:</b> {{ $tenantApp->employment_status }}</p>
        <p><b>Employer / School:</b> {{ $tenantApp->employer_school }}</p>
        <p><b>Source of Income:</b> {{ $tenantApp->source_of_income }}</p>
    </div>

    <hr>

    <div class="section">
        <h3 class="section-title">Emergency Contact</h3>
        <p><b>Contact Name:</b> {{ $tenantApp->emergency_name }}</p>
        <p><b>Relationship:</b> {{ $tenantApp->emergency_relationship }}</p>
        <p><b>Contact Number:</b> {{ $tenantApp->emergency_number }}</p>
    </div>

    <hr>

    <div class="section id-section">
        <h3 class="section-title">Uploaded Identification</h3>
        @if($tenantApp->valid_id_path)
            <p><b>Valid ID:</b></p>
            @php
                $validIdPath = storage_path('app/public/' . $tenantApp->valid_id_path);
                if (file_exists($validIdPath)) {
                    $validIdData = base64_encode(file_get_contents($validIdPath));
                    $extension = strtolower(pathinfo($validIdPath, PATHINFO_EXTENSION));
                    $mimeTypes = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp'
                    ];
                    $validIdMime = $mimeTypes[$extension] ?? 'image/jpeg';
                    $validIdBase64 = 'data:' . $validIdMime . ';base64,' . $validIdData;
                } else {
                    $validIdBase64 = null;
                }
            @endphp
            @if($validIdBase64)
                <img src="{{ $validIdBase64 }}" alt="Valid ID">
            @else
                <p style="color: #999;">Image not found</p>
            @endif
        @endif
        @if($tenantApp->id_picture_path)
            <p><b>ID Picture:</b></p>
            @php
                $idPicturePath = storage_path('app/public/' . $tenantApp->id_picture_path);
                if (file_exists($idPicturePath)) {
                    $idPictureData = base64_encode(file_get_contents($idPicturePath));
                    $extension = strtolower(pathinfo($idPicturePath, PATHINFO_EXTENSION));
                    $mimeTypes = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp'
                    ];
                    $idPictureMime = $mimeTypes[$extension] ?? 'image/jpeg';
                    $idPictureBase64 = 'data:' . $idPictureMime . ';base64,' . $idPictureData;
                } else {
                    $idPictureBase64 = null;
                }
            @endphp
            @if($idPictureBase64)
                <img src="{{ $idPictureBase64 }}" alt="ID Picture">
            @else
                <p style="color: #999;">Image not found</p>
            @endif
        @endif
    </div>

    <hr>

    <div class="section">
        <h3 class="section-title">Application Status</h3>
        <p><b>Status:</b>
            <span class="status {{ $tenant->status }}">
                {{ ucfirst($tenant->status) }}
            </span>
        </p>
        @if($tenant->rejection_reason)
            <p><b>Rejection Reason:</b> {{ $tenant->rejection_reason }}</p>
        @endif
    </div>

    <hr>
    <div class="footer">
        Generated on {{ now()->format('F d, Y') }} | Tenant Management System
    </div>

</body>
</html>

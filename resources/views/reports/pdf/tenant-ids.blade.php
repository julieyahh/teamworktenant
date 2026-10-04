<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tenant IDs</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            padding: 20px;
            text-align: center;
        }
        h2 { color: #0d6efd; }
        img {
            width: 300px;
            height: auto;
            margin: 15px;
            border: 2px solid #ccc;
            border-radius: 8px;
        }
        .id-section {
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
    <h2>Tenant ID Verification</h2>
    <p><strong>Name:</strong> {{ $tenant->name }}</p>
    <p><strong>Email:</strong> {{ $tenant->email }}</p>

    <div class="id-section">
        <h3>Valid ID</h3>
        @php
            $validIdPath = storage_path('app/public/' . $validId);
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
    </div>

    <div class="id-section">
        <h3>ID Picture</h3>
        @php
            $idPicturePath = storage_path('app/public/' . $idPicture);
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
    </div>
</body>
</html>

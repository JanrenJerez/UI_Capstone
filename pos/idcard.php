<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gym ID Card Generator</title>
    
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #eeeeee;
        }

        .page-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .generator {
            max-width: 1000px;
            margin: auto;
        }

        .form-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }

        .form-container h2 {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .generate-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #111111;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .generate-btn:hover {
            background: #333333;
        }

        .cards-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 40px;
        }

        .card-wrapper {
            text-align: center;
        }

        .card-label {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .id-card {
            width: 85.60mm;
            height: 53.98mm;
            position: relative;
            overflow: hidden;
            border-radius: 3.4mm;
            background-image:
                linear-gradient(
                    rgba(255,255,255,0.90),
                    rgba(255,255,255,0.90)
                ),
                url("assets/gym-logo.jpg");

            background-size: cover;
            background-position: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        /*
        ========================================
        FRONT
        ========================================
        */

        .front-card {
            padding: 5mm;
        }

        .front-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2mm;
        }

        .gym-logo-small {
            width: 13mm;
            height: 13mm;
            object-fit: contain;
            border-radius: 50%;
            background: white;
            padding: 1mm;
            border: 1px solid #ddd;
        }

        .gym-name {
            margin-left: 3mm;
            font-size: 5mm;
            font-weight: bold;
            text-transform: uppercase;
        }

        .member-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 3mm;
        }

        .member-info {
            width: 57mm;
            text-align: left;
        }

        .member-label {
            font-size: 2.4mm;
            font-weight: bold;
            color: #555;
            text-transform: uppercase;
        }

        .member-name {
            font-size: 5mm;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2mm;
            word-wrap: break-word;
        }

        .member-detail {
            font-size: 2.8mm;
            margin-bottom: 1.5mm;
            line-height: 1.25;
        }

        .qr-container {
            width: 20mm;
            height: 20mm;
            background: white;
            padding: 1mm;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #qrcode img,
        #qrcode canvas {
            width: 18mm !important;
            height: 18mm !important;
        }

        .id-footer {
            position: absolute;
            bottom: 3mm;
            left: 5mm;
            right: 5mm;
            text-align: center;
            font-size: 2.3mm;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .back-card {
            padding: 5mm;
            text-align: center;
        }

        .back-title {
            font-size: 4mm;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .etiquette {
            text-align: left;
            font-size: 2.45mm;
            line-height: 1.35;
            margin: 0;
            padding-left: 4mm;
        }

        .etiquette li {
            margin-bottom: 1mm;
        }

        .back-footer {
            position: absolute;
            bottom: 2.5mm;
            left: 5mm;
            right: 5mm;
            font-size: 2.2mm;
            font-weight: bold;
        }

        .print-btn {
            display: block;
            margin: 30px auto;
            padding: 12px 35px;
            border: none;
            border-radius: 7px;
            background: #198754;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .print-btn:hover {
            background: #146c43;
        }

        @media print {

            @page {
                size: A4;
                margin: 10mm;
            }

            body {
                background: white;
                padding: 0;
            }

            .page-title,
            .form-container,
            .print-btn,
            .card-label {
                display: none !important;
            }

            .cards-container {
                display: flex;
                gap: 10mm;
                justify-content: flex-start;
                align-items: flex-start;
            }

            .id-card {
                box-shadow: none;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

<div class="generator">

    <h1 class="page-title">
        Gym ID Card Generator
    </h1>

    <div class="form-container">

        <h2>Member Information</h2>

        <div class="form-group">
            <label for="memberName">
                Member Name
            </label>

            <input
                type="text"
                id="memberName"
                placeholder="Juan Dela Cruz"
            >
        </div>

        <div class="form-group">
            <label for="memberAddress">
                Address
            </label>

            <input
                type="text"
                id="memberAddress"
                placeholder="Iloilo City, Philippines"
            >
        </div>

        <div class="form-group">
            <label for="memberNumber">
                Contact Number
            </label>

            <input
                type="text"
                id="memberNumber"
                placeholder="09123456789"
            >
        </div>

        <button
            class="generate-btn"
            onclick="generateCard()"
        >
            Generate ID Card
        </button>

    </div>

    <div class="cards-container">


        <div class="card-wrapper">

            <div class="card-label">
                FRONT
            </div>

            <div class="id-card front-card">

                <div class="front-header">

                    <img
                        src="assets/gym-logo.jpg"
                        class="gym-logo-small"
                        alt="Gym Logo"
                    >

                    <div class="gym-name">
                        YOUR GYM NAME
                    </div>

                </div>


                <div class="member-section">

                    <div class="member-info">

                        <div class="member-label">
                            Member Name
                        </div>

                        <div
                            class="member-name"
                            id="displayName"
                        >
                            MEMBER NAME
                        </div>

                        <div class="member-detail">
                            <strong>Address:</strong>
                            <span id="displayAddress">
                                Member Address
                            </span>
                        </div>

                        <div class="member-detail">
                            <strong>Contact:</strong>
                            <span id="displayNumber">
                                09XXXXXXXXX
                            </span>
                        </div>

                    </div>


                    <div class="qr-container">

                        <div id="qrcode"></div>

                    </div>

                </div>


                <div class="id-footer">
                    GYM MEMBERSHIP ID • PRESENT THIS CARD FOR ATTENDANCE
                </div>

            </div>

        </div>


        <div class="card-wrapper">

            <div class="card-label">
                BACK
            </div>

            <div class="id-card back-card">

                <div class="back-title">
                    Gym Etiquette
                </div>

                <ul class="etiquette">

                    <li>
                        Wipe down equipment after use.
                    </li>

                    <li>
                        Return weights and equipment to their proper place.
                    </li>

                    <li>
                        Respect other members and gym staff.
                    </li>

                    <li>
                        Do not occupy equipment unnecessarily.
                    </li>

                    <li>
                        Use equipment properly and safely.
                    </li>

                    <li>
                        Keep the gym clean and organized.
                    </li>

                    <li>
                        Follow all gym rules and staff instructions.
                    </li>

                    <li>
                        Report damaged equipment immediately.
                    </li>

                </ul>


                <div class="back-footer">
                    THIS CARD IS PROPERTY OF THE GYM
                </div>

            </div>

        </div>

    </div>


    <button
        class="print-btn"
        onclick="window.print()"
    >
        🖨 Print ID Card
    </button>

</div>


<script>

function generateCard() {

    const name =
        document.getElementById("memberName").value.trim();

    const address =
        document.getElementById("memberAddress").value.trim();

    const number =
        document.getElementById("memberNumber").value.trim();


    if (!name || !address || !number) {

        alert("Please enter the member name, address, and contact number.");

        return;
    }


    document.getElementById("displayName").textContent =
        name;

    document.getElementById("displayAddress").textContent =
        address;

    document.getElementById("displayNumber").textContent =
        number;

    const qrContainer =
        document.getElementById("qrcode");

    qrContainer.innerHTML = "";


    const qrData =
        "GYM MEMBER\n" +
        "Name: " + name + "\n" +
        "Address: " + address + "\n" +
        "Contact: " + number;


    new QRCode(qrContainer, {

        text: qrData,

        width: 120,

        height: 120,

        colorDark: "#000000",

        colorLight: "#ffffff",

        correctLevel:
            QRCode.CorrectLevel.H

    });

}

</script>

</body>
</html>